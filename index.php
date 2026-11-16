<?php
require __DIR__ . "/app/bootstrap.php";
require __DIR__ . "/app/totp.php";

$user = current_user();

$notice = $_SESSION[ "settings_notice" ] ?? "";
unset( $_SESSION[ "settings_notice" ] );
$error = "";
if ( $_SERVER[ "REQUEST_METHOD" ] === "POST" )
{
    if ( !$user )
    {
        redirect_home();
    }

    prevent_cross_site_request();

    require __DIR__ . "/app/account-settings.php";
    $error = update_account_settings( ( int )$user[ "id" ] );
    if ( $error === "" )
    {
        redirect_home();
    }
}

$totpEnabled = $user && ( totp_record( ( int )$user[ "id" ] ) !== null );
$setupKey    = $user && !$totpEnabled ? pending_totp_secret( ( int )$user[ "id" ] ) : null;
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
    <?php if ( $user ): ?>
        <script src="assets/movie-search.js" defer></script>
        <script src="assets/account-settings.js" defer></script>
    <?php endif; ?>
</head>
<body>
<canvas id="nodes" aria-hidden="true"></canvas>
<header>
    <?php if ( $user ): ?>
        <?php $csrf = htmlspecialchars( $_SESSION[ "csrf" ], ENT_QUOTES, "UTF-8" ); ?>
        <button class="login account-toggle" id="account-toggle" popovertarget="account-menu" aria-label="Account menu">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                <circle cx="12" cy="8" r="3.5"/>
                <path d="M4.5 21v-2a7.5 7.5 0 0 1 15 0v2z"/>
            </svg>
        </button>
        <div id="account-menu" popover>
            <button id="open-settings" aria-haspopup="dialog">Settings</button>
            <form action="logout.php" method="post">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <button type="submit">Log out</button>
            </form>
        </div>
    <?php else: ?>
        <a class="login" href="login.php">Log in</a>
        <a class="login" href="register.php">Register</a>
    <?php endif; ?>
</header>
<?php if ( $user ): ?>
    <dialog id="account-settings" aria-labelledby="settings-title" <?= ( $error || $notice ) ? "data-reopen" : "" ?>>
        <div class="settings-heading">
            <h2 id="settings-title">Settings</h2>
            <form method="dialog"><button class="login" aria-label="Close settings">&times;</button></form>
        </div>
        <?php if ( $error ): ?><p role="alert" class="form-error"><?= htmlspecialchars( $error ) ?></p><?php endif; ?>
        <?php if ( $notice ): ?><p role="status"><?= htmlspecialchars( $notice ) ?></p><?php endif; ?>
        <form method="post" class="auth-form">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="username">
            <label for="settings-name">Username</label>
            <input id="settings-name" name="name" autocomplete="nickname" maxlength="100" value="<?= htmlspecialchars( $user[ "name" ], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" ) ?>" required>
            <button class="login">Save username</button>
        </form>
        <form method="post" class="auth-form">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="password">
            <label for="old-password">Current password</label>
            <input id="old-password" name="old_password" type="password" autocomplete="current-password" required>
            <label for="new-password">New password</label>
            <input id="new-password" name="password" type="password" autocomplete="new-password" aria-describedby="password-help" required>
            <small id="password-help" class="muted">Use 12&ndash;72 characters.</small>
            <label for="confirm-password">Confirm new password</label>
            <input id="confirm-password" name="password_confirmation" type="password" autocomplete="new-password" required>
            <button class="login">Change password</button>
        </form>
        <form method="post" class="auth-form" aria-labelledby="totp-title">
            <h3 id="totp-title">Two-factor authentication</h3>
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <?php if ( $totpEnabled || $setupKey ): ?>
                <?php if ( $setupKey ): ?>
                    <p class="muted">Add a time-based entry for Watch Party to your authenticator app using this setup key.</p>
                    <input aria-label="Authenticator setup key" readonly value="<?= htmlspecialchars( $setupKey ) ?>">
                <?php else: ?>
                    <p class="muted">Enabled. To disable it, enter a fresh code from your authenticator app.</p>
                <?php endif; ?>
                <label for="settings-code">Authenticator code</label>
                <input id="settings-code" name="totp_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
                <button class="login" name="action" value="<?= $totpEnabled ? "totp_disable" : "totp_enable" ?>"><?= $totpEnabled ? "Disable" : "Enable" ?> two-factor authentication</button>
            <?php else: ?>
                <p class="muted">Use an authenticator app to generate a six-digit code for each login.</p>
                <label for="setup-password">Current password</label>
                <input id="setup-password" type="password" name="current_password" autocomplete="current-password" required>
                <button class="login" name="action" value="totp_start">Set up two-factor authentication</button>
            <?php endif; ?>
        </form>
    </dialog>
<?php endif; ?>
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
