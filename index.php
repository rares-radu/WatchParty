<?php
require __DIR__ . "/app/bootstrap.php";

$user = current_user();
?>
<!doctype html>
<html lang="en-US">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Watch Party &ndash; a place to watch movies together.">

    <title>🎉Watch Party🎉</title>

    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/nodes.js" defer></script>
    <?php if ( $user ): ?><script src="assets/movie-search.js" defer></script><?php endif; ?>
</head>
<body>
<canvas id="nodes" aria-hidden="true"></canvas>
<header>
    <?php if ( $user ): ?>
        <form action="logout.php" method="post">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars( $_SESSION[ "csrf" ], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" ) ?>">
            <button class="login" type="submit">Log out</button>
        </form>
    <?php else: ?>
        <a class="login" href="login.php">Log in</a>
        <a class="login" href="register.php">Register</a>
    <?php endif; ?>
</header>
<main>
    <section class="watch-card" aria-label="Watch Party">
        <img src="assets/play.svg" width="88" height="88" alt="">
        <h1><?= $user ? "Welcome, " . htmlspecialchars( $user[ "name" ], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" ) . "!" : "Log in to start<br>watching" ?></h1>
        <?php if ( $user ): ?>
            <div class="movie-search" id="movie-search" data-csrf="<?= htmlspecialchars( $_SESSION[ "csrf" ], ENT_QUOTES, "UTF-8" ) ?>">
                <label for="movie-query">Find a movie to watch</label>
                <input id="movie-query" type="search" placeholder="Search movies by name..." autocomplete="off" maxlength="200"
                       aria-controls="movie-search-dropdown" aria-expanded="false" aria-describedby="movie-search-status">
                <div class="movie-search-dropdown" id="movie-search-dropdown" hidden>
                    <p class="movie-search-status" id="movie-search-status" role="status" aria-live="polite"></p>
                    <ul class="movie-search-results" id="movie-search-results" aria-label="Movie results"></ul>
                    <p class="movie-search-credit">Movie data and images from <a href="https://www.themoviedb.org/" target="_blank" rel="noopener noreferrer">TMDB</a>.</p>
                </div>
            </div>
        <?php endif; ?>
        <form class="auth-form" action="room.php" method="get">
            <label for="invite-code">Have an invite code?</label>
            <input id="invite-code" name="code" placeholder="Enter your room code" required
                   maxlength="24" pattern="[A-Fa-f0-9]{24}" autocomplete="off" spellcheck="false"
                   aria-describedby="invite-help">
            <p class="muted" id="invite-help">Join a watch party &ndash; no account needed.</p>
            <button class="login" type="submit">Join room</button>
        </form>
    </section>
</main>
</body>
</html>
