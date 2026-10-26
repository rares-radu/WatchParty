<?php
declare( strict_types=1 );

require __DIR__ . "/app/bootstrap.php";

if ( $_SERVER[ "REQUEST_METHOD" ] !== "POST" )
{
    header( "Allow: POST" );
    http_response_code( 405 );
    exit( "Only POST requests are supported." );
}

$user = current_user();
if ( !$user )
{
    http_response_code( 401 );
    exit( 'Please <a href="login.php">log in</a> to create a room.' );
}
prevent_cross_site_request();

$tmdb_id = get_query_parameter( "tmdb_id" );
$title   = trim( get_query_parameter( "movie_title" ) );
if ( !preg_match( '/^[1-9][0-9]{0,9}$/D', $tmdb_id ) ||
     $title === ""                                   ||
     !mb_check_encoding( $title, "UTF-8" )           ||
     mb_strlen( $title ) > 255 )
{
    http_response_code( 400 );
    exit( 'Invalid movie. <a href="index.php">Search again</a>.' );
}

$query = db()->prepare( "INSERT INTO rooms ( invite_code, owner_id, tmdb_id, movie_title ) VALUES ( ?, ?, ?, ? )" );
// The unique database constraint protects against duplicate room codes
for ( $attempt = 0; $attempt < 5; $attempt++ )
{
    $code = strtoupper( bin2hex( random_bytes( 12 ) ) );
    try
    {
        $query->execute( [ $code, $user[ "id" ], $tmdb_id, $title ] );
        header( "Location: room.php?code=" . $code, true, 303 );
        exit();
    }
    catch ( PDOException $exception )
    {
        if ( ( $exception->errorInfo[ 1 ] ?? null ) !== 1062 || $attempt === 4 )
        {
            throw $exception;
        }
    }
}
