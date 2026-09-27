<?php
// Only serve public files!
$path = rawurldecode( parse_url( $_SERVER[ "REQUEST_URI" ], PHP_URL_PATH ) ?: "/" );
if ( preg_match( "~^/assets/[a-zA-Z0-9_-]+\.(css|js|svg)$~D", $path ) && is_file( __DIR__ . $path ) )
{
    return false;
}

$routes = [
    "/"             => "index.php",
    "/index.php"    => "index.php",
    "/login.php"    => "login.php",
    "/register.php" => "register.php",
    "/logout.php"   => "logout.php"
];
if ( !isset( $routes[ $path ] ) )
{
    http_response_code( 404 );

    // TODO: Make this prettier
    echo <<<HTML
    <!doctype html>
    <html lang="en-US">
    <head>
        <meta http-equiv="refresh" content="3;url=/">
        <title>Not found | 🎉Watch Party🎉</title>
    </head>
    <body>
        Not found
    </body>
    </html>
    HTML;

    exit();
}

require __DIR__ . "/" . $routes[ $path ];
