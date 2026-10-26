<?php
declare( strict_types=1 );

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tmdb\Client;
use Tmdb\Event\BeforeRequestEvent;
use Tmdb\Event\Listener\Request\AcceptJsonRequestListener;
use Tmdb\Event\Listener\Request\ApiTokenRequestListener;
use Tmdb\Event\Listener\Request\ContentTypeJsonRequestListener;
use Tmdb\Event\Listener\Request\UserAgentRequestListener;
use Tmdb\Event\Listener\RequestListener;
use Tmdb\Event\RequestEvent;
use Tmdb\Token\Api\ApiToken;

require __DIR__ . "/app/bootstrap.php";

header( "Content-Type: application/json; charset=UTF-8" );

/*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  Function: movie_search_response

  Summary:  This function sends the client the response from the
            movie search request.

  Args:     int $response_code
              HTML response code.
            array $data
              Data to send to the client.

  Returns:  never
              This function never returns.
-----------------------------------------------------------------F-F*/
function movie_search_response( int $response_code, array $data ): never
{
    http_response_code( $response_code );
    echo json_encode( $data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE );
    exit();
}

try
{
    if ( $_SERVER[ "REQUEST_METHOD" ] !== "GET" )
    {
        header( "Allow: GET" );
        movie_search_response( 405, [ "error" => "Only GET requests are supported." ] );
    }

    if ( !current_user() )
    {
        movie_search_response( 401, [ "error" => "Please log in to search for movies." ] );
    }

    // Release the session lock before waiting for TMDB
    session_write_close();

    $query = $_GET[ "query" ] ?? "";
    if ( !is_string( $query ) || !mb_check_encoding( $query, "UTF-8" ) || mb_strlen( $query ) > 200 )
    {
        movie_search_response( 400, [ "error" => "Enter a movie name of up to 200 characters." ] );
    }

    $query = trim( $query );
    if ( $query === "" )
    {
        movie_search_response( 200, [ "results" => [] ] );
    }

    $http_options = [ "timeout" => 8, "connect_timeout" => 3 ];
    /*--------------------------------------------------------------------
      Windows PHP builds may have no CA bundle configured. Use the OS
      trust store while retaining HTTPS certificate and hostname
      verification.
    --------------------------------------------------------------------*/
    if ( PHP_OS_FAMILY === "Windows" && extension_loaded( "curl" ) && defined( "CURLSSLOPT_NATIVE_CA" ) )
    {
        $http_options[ "curl" ] = [ CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA ];
    }

    $dispatcher = new EventDispatcher();
    $client     = new Client(
                             [
                                 "api_token"        => new ApiToken( $_ENV[ "TMDB_API_KEY" ] ),
                                 "event_dispatcher" => [ "adapter" => $dispatcher ],
                                 "http"             => [ "client" => new HttpClient( $http_options ) ]
                             ]
                            );
    $dispatcher->addListener( RequestEvent::class, new RequestListener( $client->getHttpClient(), $dispatcher ) );
    $dispatcher->addListener( BeforeRequestEvent::class, new ApiTokenRequestListener( $client->getToken() ) );
    $dispatcher->addListener( BeforeRequestEvent::class, new AcceptJsonRequestListener() );
    $dispatcher->addListener( BeforeRequestEvent::class, new ContentTypeJsonRequestListener() );
    $dispatcher->addListener( BeforeRequestEvent::class, new UserAgentRequestListener() );

    // TODO: Add a switch to include adult movies?
    $response = $client->getSearchApi()->searchMovies( $query, [ "include_adult" => false, "language" => "en-US", "page" => 1 ] );

    $results = [];
    foreach ( array_slice( $response[ "results" ] ?? [], 0, 10 ) as $movie )
    {
        $poster_path = $movie[ "poster_path" ] ?? null;
        $results[]   =
            [
                "id"         => ( int )$movie[ "id" ],
                "title"      => $movie[ "title" ] ?? "Untitled movie",
                "poster_url" => is_string( $poster_path ) ? "https://image.tmdb.org/t/p/w92" . $poster_path : null
            ];
    }
    movie_search_response( 200, [ "results" => $results ] );
}
catch ( Throwable $exception )
{
    // Avoid logging exception URLs, which can contain the API key
    $details = "Movie search failed: " . get_class( $exception );
    if ( $exception instanceof RequestException )
    {
        $context  = $exception->getHandlerContext();
        $details .= sprintf(
                            " (cURL error %d, TLS verification result %d, HTTP status %d)",
                            $context[ "errno" ] ?? 0,
                            $context[ "ssl_verify_result" ] ?? 0,
                            $exception->getResponse()?->getStatusCode() ?? 0
                           );
    }
    error_log( $details );
    movie_search_response( 503, [ "error" => "Movie search is temporarily unavailable. Please try again." ] );
}
