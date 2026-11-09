<?php
declare( strict_types=1 );

require __DIR__ . "/app/bootstrap.php";
header( "Content-Type: application/json; charset=UTF-8" );

/*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  Function: chat_error

  Summary:  This function sets up an error response from the PHP page.

  Args:     int $response_code
              HTTP response code.
            string $message
              Error message.

  Returns:  never
              This function does not return.
-----------------------------------------------------------------F-F*/
function chat_error( int $response_code, string $message ): never
{
    http_response_code( $response_code );
    echo json_encode( [ "error" => $message ] );
    exit();
}

try
{
    if ( !in_array( $_SERVER[ "REQUEST_METHOD" ], [ "GET", "POST" ], true ) )
    {
        header( "Allow: GET, POST" );
        chat_error( 405, "Only GET and POST are supported." );
    }

    $userId = $_SESSION[ "user_id" ] ?? null;
    $csrf   = $_SESSION[ "csrf" ];
    session_write_close();

    $code = is_string( $_GET[ "code" ] ?? null ) ? strtoupper( trim( $_GET[ "code" ] ) ) : "";
    if ( !preg_match( "/^[A-F0-9]{24}$/D", $code ) )
    {
        chat_error( 404, "Room not found." );
    }

    $query = db()->prepare( "SELECT id FROM rooms WHERE invite_code = ?" );
    $query->execute( [ $code ] );
    $roomId = $query->fetchColumn();
    if ( !$roomId )
    {
        chat_error( 404, "Room not found." );
    }

    if ( $_SERVER[ "REQUEST_METHOD" ] === "POST" )
    {
        if ( !$userId )
        {
            chat_error( 401, "Log in to send messages" );
        }

        if ( !hash_equals( $csrf, get_query_parameter( "csrf" ) ) )
        {
            chat_error( 403, "Refresh the room to send messages." );
        }

        $message = trim( get_query_parameter( "message" ) );
        if ( !preg_match( "/\A.{1,1000}\z/us", $message ) || !preg_match( "/\S/u", $message ) )
        {
            chat_error( 400, "Enter a message of up to 1000 characters." );
        }
        $query = db()->prepare( "INSERT INTO room_messages ( room_id, user_id, message ) VALUES ( ?, ?, ? )" );
        $query->execute( [ $roomId, $userId, $message ] );
        http_response_code( 201 );
        echo '{"ok":true}';
        exit();
    }

    $query = db()->prepare( "SELECT CAST( m.id AS CHAR ) AS id, u.name, m.message
                             FROM room_messages m JOIN users u ON u.id = m.user_id
                             WHERE m.room_id = ? ORDER BY m.id DESC LIMIT 100" );
    $query->execute( [ $roomId ] );
    echo json_encode( array_reverse( $query->fetchAll( PDO::FETCH_ASSOC ) ), JSON_THROW_ON_ERROR );
}
catch ( Throwable $exception )
{
    error_log( "Room chat failed: " . get_class( $exception ) );
    chat_error( 503, "Room chat is temporarily unavailable." );
}
