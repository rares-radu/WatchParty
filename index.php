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
        <?php if ( $user ): ?><p class="muted">You're logged in to Watch Party.</p><?php endif; ?>
    </section>
</main>
</body>
</html>
