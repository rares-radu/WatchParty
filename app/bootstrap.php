<?php
declare( strict_types=1 );

use Dotenv\Dotenv;

require_once __DIR__ . "/../vendor/autoload.php";

set_exception_handler(
                      function ( Throwable $exception ): void
                      {
                          error_log( ( string )$exception );
                          http_response_code( 503 );
                          echo "WatchParty is temporarily unavailable. Please try again later.";
                      }
                     );

$dotenv = Dotenv::createImmutable( __DIR__ . "/.." );
$dotenv->load();
$dotenv->required( [ "WATCHPARTY_HOST", "WATCHPARTY_PORT", "WATCHPARTY_DATABASE",
                     "WATCHPARTY_DATABASE_USERNAME", "WATCHPARTY_DATABASE_PASSWORD" ] )->notEmpty();
$dotenv->required( "WATCHPARTY_PORT" )->isInteger();

header( "Cache-Control: no-store" );
header( "X-Content-Type-Options: nosniff" );
header( "Referrer-Policy: same-origin" );

ini_set( "session.use_strict_mode", "1" );
ini_set( "session.use_only_cookies", "1" );

session_name( "watch_party_session" );
session_set_cookie_params( [ "lifetime" => 0,
                             "path"     => "/",
                             "secure"   => ( !empty( $_SERVER[ "HTTPS" ] ) && $_SERVER[ "HTTPS" ] !== "off" ),
                             "httponly" => true,
                             "samesite" => "Lax" ] );
session_start();
if ( isset( $_SESSION[ "last_activity" ] ) && ( time() - $_SESSION[ "last_activity" ] ) > 7200 )
{
    $_SESSION = [];
    session_regenerate_id( true );
}
$_SESSION[ "last_activity" ] = time();
$_SESSION[ "csrf" ]        ??= bin2hex( random_bytes( 32 ) );

/*F+F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F
  Function: db

  Summary:  This function retrieves the connection to our database.

  Returns:  PDO
              Database server connection.
F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F-F*/
function db(): PDO
{
    static $connection;
    if ( !$connection )
    {
        $connection = new PDO(
                              "mysql:host=" . $_ENV[ "WATCHPARTY_HOST" ] . ";port=" . $_ENV[ "WATCHPARTY_PORT" ] . ";dbname=" . $_ENV[ "WATCHPARTY_DATABASE" ] . ";charset=utf8mb4",
                              $_ENV[ "WATCHPARTY_DATABASE_USERNAME" ],
                              $_ENV[ "WATCHPARTY_DATABASE_PASSWORD" ],
                              [
                                  PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
                                  PDO::ATTR_EMULATE_PREPARES => false
                              ]
                             );
    }
    return $connection;
}

/*F+F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F
  Function: get_form_input

  Summary:  This function retrieves the string submitted in a form
            on the current page.

  Args:     string $name
              The value of the `name` attribute of the `<input>`
              element.

  Returns:  string
              The string contained by the `<input>` element when
              the form was submitted.
F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F-F*/
function get_form_input( string $name ): string
{
    return is_string( $_POST[ $name ] ?? null ) ? $_POST[ $name ] : "";
}

/*F+F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F
  Function: redirect_home

  Summary:  This function redirects the client to the landing page
            of the website.

  Returns:  never
              This function does not return.
F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F-F*/
function redirect_home(): never
{
    header( "Location: index.php", true, 303 );
    exit();
}

/*F+F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F
  Function: prevent_cross_site_request

  Summary:  This function prevents cross-site request forgery attacks.
            If the session's `csrf` does not match that of the form,
            function sends the client a `403 Forbidden` response.

  Returns:  void
              No return value.
F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F-F*/
function prevent_cross_site_request(): void
{
    if ( !hash_equals( $_SESSION[ "csrf" ], get_form_input( "csrf" ) ) )
    {
        http_response_code( 403 );
        exit( "Your form expired. Go back, refresh the page, and try again." );
    }
}

/*F+F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F
  Function: log_in

  Summary:  This function regenerates the current session and
            sets the session's necessary attributes.

  Args:     int $id
              User ID to log in as.

  Returns:  void
              No return value.
F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F-F*/
function log_in( int $id ): void
{
    session_regenerate_id( true );
    $_SESSION = [ "user_id" => $id, "csrf" => bin2hex( random_bytes( 32 ) ), "last_activity" => time() ];
    redirect_home();
}

/*F+F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F
  Function: current_user

  Summary:  This function retrieves the current user's id, name, and
            email from the database.

  Returns:  ?array
              If the session's user is logged in, the return value
              is an array with the user's `id`, `name`, and `email`
              attributes.

              If the session's user is not logged in, the return
              value is `null`.
F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F-F*/
function current_user(): ?array
{
    if ( !isset( $_SESSION[ "user_id" ] ) )
    {
        return null;
    }

    $query = db()->prepare( "SELECT id, name, email FROM users WHERE id = ?" );
    $query->execute( [ $_SESSION[ "user_id" ] ] );
    $user = $query->fetch( PDO::FETCH_ASSOC );
    if ( !$user )
    {
        // User was deleted from the database altogether, so log him out
        unset( $_SESSION[ "user_id" ] );
    }

    return $user ?: null;
}

/*F+F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F
  Function: allow_authentication_attempt

  Summary:  This function determines whether an authentication
            attempt should be allowed or denied. If the user tried
            to authenticate more than 20 times in the last 15
            minutes, the attempt is denied.

  Returns:  ?array
              If the authentication attempt is allowed, the return
              value is `true`.

              If the authentication attempt is denied, the return
              value is `false`.
F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F-F*/
function allow_authentication_attempt(): bool
{
    $key = hash( "sha256", $_SERVER[ "REMOTE_ADDR" ] ?? "unknown" );
    db()->exec( "DELETE FROM auth_attempts WHERE window_start < UNIX_TIMESTAMP() - 900" );
    $query = db()->prepare( "INSERT INTO auth_attempts ( bucket, attempts, window_start ) VALUES ( ?, 1, UNIX_TIMESTAMP() ) ON DUPLICATE KEY UPDATE attempts = attempts + 1" );
    $query->execute( [ $key ] );
    $query = db()->prepare( "SELECT attempts FROM auth_attempts WHERE bucket = ?" );
    $query->execute( [ $key ] );
    return ( int )$query->fetchColumn() <= 20;
}
