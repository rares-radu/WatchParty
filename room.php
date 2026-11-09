<?php
declare( strict_types=1 );

require __DIR__ . "/app/bootstrap.php";

if ( $_SERVER[ "REQUEST_METHOD" ] !== "GET" )
{
    header( "Allow: GET" );
    http_response_code( 405 );
    exit( "Only GET requests are supported." );
}

$code = is_string( $_GET[ "code" ] ?? null ) ? strtoupper( trim( $_GET[ "code" ] ) ) : "";
$room = false;
if ( preg_match( "/^[A-F0-9]{24}$/D", $code ) )
{
    $query = db()->prepare( "SELECT rooms.*, users.name AS owner_name FROM rooms JOIN users ON users.id = rooms.owner_id WHERE invite_code = ?" );
    $query->execute( [ $code ] );
    $room = $query->fetch( PDO::FETCH_ASSOC );
}
if ( !$room )
{
    http_response_code( 404 );
}

$is_owner = $room && ( int )( $_SESSION[ "user_id" ] ?? 0 ) === ( int )$room[ "owner_id" ];

session_write_close();
?>
<!doctype html>
<html lang="en-US">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $room ? htmlspecialchars( $room[ "movie_title" ], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" ) : "Room not found" ?> | 🎉Watch Party🎉</title>
    <link rel="stylesheet" href="assets/style.css">
    <?php if ( $room ): ?>
        <script src="assets/room-sync.js" defer></script>
        <script src="assets/room-chat.js" defer></script>
    <?php endif; ?>
</head>
<body>
<header><a class="login" href="index.php">Home</a></header>
<main>
    <section class="watch-card room-card">
        <?php if ( $room ): ?>
            <h1><?= htmlspecialchars( $room[ "movie_title" ], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" ) ?></h1>
            <p class="muted"><?= $is_owner ? "You own this room." : "Hosted by " . htmlspecialchars( $room[ "owner_name" ], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" ) ?></p>
            <div class="auth-form room-invite">
                <label for="room-code">Invite code &ndash; share this to watch together</label>
                <input id="room-code" readonly value="<?= htmlspecialchars( $code, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" ) ?>">
                <p class="muted">Friends can enter this code on the home page without signing up.</p>
            </div>
            <div class="room-view">
                <iframe id="room-player" class="movie-player" src="https://framextv.xyz/embed/<?= ( int )$room[ "tmdb_id" ] ?>"
                    data-code="<?= htmlspecialchars( $code, ENT_QUOTES, "UTF-8" ) ?>"
                    data-owner="<?= $is_owner ? "1" : "0" ?>"
                    data-csrf="<?= $is_owner ? htmlspecialchars( $_SESSION[ "csrf" ], ENT_QUOTES, "UTF-8" ) : "" ?>"
                    title="<?= htmlspecialchars( $room[ "movie_title" ], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" ) ?> player"
                    allow="autoplay; fullscreen; picture-in-picture; encrypted-media"
                    allowfullscreen referrerpolicy="no-referrer"></iframe>
                <aside class="room-chat" aria-labelledby="chat-heading">
                    <h2 id="chat-heading">Room chat</h2>
                    <div id="chat-messages" role="log" aria-label="Chat messages" tabindex="0"></div>
                    <p id="chat-status" class="muted" role="status"></p>
                    <?php if ( isset( $_SESSION[ "user_id" ] ) ): ?>
                        <form id="chat-form" class="auth-form" method="post" action="chat.php?code=<?= $code ?>">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars( $_SESSION[ "csrf" ], ENT_QUOTES, "UTF-8" ) ?>">
                            <input name="message" aria-label="Chat message" placeholder="Write a message" maxlength="1000" required>
                            <button class="login" type="submit">Send</button>
                        </form>
                    <?php endif; ?>
                    <p id="chat-login" class="muted" <?= isset( $_SESSION[ "user_id" ] ) ? "hidden" : "" ?>><a href="login.php">Log in</a> to send messages</p>
                </aside>
            </div>
            <?php if ( !$is_owner ): ?>
                <div class="playback-controls">
                    <button class="login" type="button" id="room-resync">Resync playback</button>
                </div>
            <?php endif; ?>
            <p class="muted" id="sync-status" role="status" aria-live="polite">Connecting playback sync...</p>
            <p class="muted"><?= $is_owner ? "Use the player to seek. Native pauses and buffering are detected after a short delay. Keep this room open in one tab." : "The host controls playback. If autoplay is blocked, press play inside the video, then resync." ?></p>
        <?php else: ?>
            <h1>Room not found</h1>
            <p class="muted">Check your invite code with the host and try again.</p>
            <a class="login" href="index.php">Enter another code</a>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
