<?php
declare( strict_types=1 );

require __DIR__ . "/app/bootstrap.php";
header( "Content-Type: application/json; charset=UTF-8" );

/*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  Function: playback_response

  Summary:  This function sets up the response of the PHP page.

  Args:     int $response_code
              HTTP response code.
            array $data
              Optional data to send along the response code.

  Returns:  never
              This function does not return.
-----------------------------------------------------------------F-F*/
function playback_response( int $response_code, array $data ): never
{
    http_response_code( $response_code );
    echo json_encode( $data, JSON_THROW_ON_ERROR );
    exit();
}

try
{
    $method = $_SERVER[ "REQUEST_METHOD" ];
    if ( !in_array( $method, [ "GET", "POST" ], true ) )
    {
        header( "Allow: GET, POST" );
        playback_response( 405, [ "error" => "Only GET and POST are supported." ] );
    }

    $code = is_string( $_GET[ "code" ] ?? null ) ? strtoupper( trim( $_GET[ "code" ] ) ) : "";
    if ( !preg_match( "/^[A-F0-9]{24}$/D", $code ) )
    {
        playback_response( 404, [ "error" => "Room not found." ] );
    }

    $query = db()->prepare( "SELECT id, owner_id FROM rooms WHERE invite_code = ?" );
    $query->execute( [ $code ] );
    $room = $query->fetch( PDO::FETCH_ASSOC );
    if ( !$room )
    {
        playback_response( 404, [ "error" => "Room not found." ] );
    }

    if ( $method === "POST" )
    {
        if ( ( int )( $_SESSION[ "user_id" ] ?? 0 ) !== ( int )$room[ "owner_id" ] )
        {
            playback_response( 403, [ "error" => "Only the room owner can control playback. Log in as the owner to continue." ] );
        }

        if ( !hash_equals( $_SESSION[ "csrf" ], get_query_parameter( "csrf" ) ) )
        {
            playback_response( 403, [ "error" => "Your session changed. Refresh the room to continue." ] );
        }

        $position = get_query_parameter( "position" );
        $playing  = get_query_parameter( "playing" );
        if ( !is_numeric( $position )         ||
             !is_finite( ( float )$position ) ||
             ( float )$position < 0           ||
             ( float )$position > 604800      ||
             !in_array( $playing, [ "0", "1" ], true ) )
        {
            playback_response( 400, [ "error" => "Invalid playback state." ] );
        }
    }

    // Do not hold the PHP session lock during database work or other room requests
    session_write_close();

    if ( $method === "POST" )
    {
        $query = db()->prepare( "INSERT INTO room_playback ( room_id, position, playing, updated_at ) VALUES ( ?, ?, ?, UNIX_TIMESTAMP( NOW( 3 ) ) ) ON DUPLICATE KEY UPDATE position = VALUES( position ), playing = VALUES( playing ), updated_at = VALUES( updated_at )" );
        $query->execute( [ $room[ "id" ], ( float )$position, ( int )$playing ] );
        playback_response( 200, [ "ok" => true ] );
    }
    $query = db()->prepare( "SELECT position, playing, GREATEST( 0, UNIX_TIMESTAMP( NOW( 3 ) ) - updated_at ) AS age FROM room_playback WHERE room_id = ?" );
    $query->execute( [ $room[ "id" ] ] );
    $state = $query->fetch( PDO::FETCH_ASSOC );
    playback_response( 200,
                       [
                           "state" => $state ?
                               [
                                   "position" => ( float )$state[ "position" ],
                                   "playing"  => ( bool )$state[ "playing" ],
                                   "age"      => ( float )$state[ "age" ]
                               ] : null
                       ] );
}
catch ( Throwable $exception )
{
    error_log( "Playback sync failed: " . get_class( $exception ) );
    playback_response( 503, [ "error" => "Playback sync is unavailable. Please try again shortly." ] );
}
