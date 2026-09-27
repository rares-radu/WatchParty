<?php
require __DIR__ . "/app/bootstrap.php";

if ( $_SERVER[ "REQUEST_METHOD" ] !== "POST" )
{
    http_response_code( 405 );
    header( "Allow: POST" );
    exit( "Use the Log out button to sign out." );
}

prevent_cross_site_request();

$_SESSION = [];
$params   = session_get_cookie_params();
setcookie(
          session_name(),
          "",
          [
              "expires"  => time() - 3600,
              "path"     => $params[ "path" ],
              "secure"   => $params[ "secure" ],
              "httponly" => true,
              "samesite" => "Lax"
          ]
         );
session_destroy();
redirect_home();
