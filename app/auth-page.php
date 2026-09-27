<?php
global $mode;
require __DIR__ . "/bootstrap.php";

if ( current_user() )
{
    // Already logged in
    redirect_home();
}

$register = $mode === "register";
$title    = $register ? "Register" : "Log in";

$error = $email = $name = "";

if ( $_SERVER[ "REQUEST_METHOD" ] === "POST" )
{
    prevent_cross_site_request();

    $email    = strtolower( trim( get_form_input( "email" ) ) );
    $name     = trim( get_form_input( "name" ) );
    $password = get_form_input( "password" );

    if ( !allow_authentication_attempt() )
    {
        http_response_code( 429 );
        header( "Retry-After: 900" );
        $error = "Too many attempts. Please try again in 15 minutes.";
    }
    else if ( !filter_var( $email, FILTER_VALIDATE_EMAIL ) || strlen( $email ) > 254 )
    {
        $error = "Enter a valid email address.";
    }
    else if ( $register && ( $name === "" || strlen( $name ) > 100 ) )
    {
        $error = "Enter a name of up to 100 bytes.";
    }
    else if ( str_contains( $password, "\0" ) || strlen( $password ) > 72 || ( $register && strlen( $password ) < 12 ) )
    {
        $error = "Use a password between 12 and 72 bytes without null characters.";
    }
    else if ( $register && $password !== get_form_input( "password_confirmation" ) )
    {
        $error = "The passwords do not match.";
    }
    else if ( $register )
    {
        $hash = password_hash( $password, PASSWORD_BCRYPT, [ "cost" => PASSWORD_BCRYPT_DEFAULT_COST ] );
        try
        {
            $query = db()->prepare( "INSERT INTO users ( name, email, password_hash ) VALUES ( ?, ?, ? )" );
            $query->execute( [ $name, $email, $hash ] );
            log_in( ( int )db()->lastInsertId() );
        }
        catch ( PDOException $exception )
        {
            if ( ( $exception->errorInfo[ 1 ] ?? null ) !== 1062 )
            {
                throw $exception;
            }
            $error = "Unable to register with these details. Try logging in or use another email.";
        }
    }
    else
    {
        $query = db()->prepare( "SELECT id, password_hash FROM users WHERE email = ?" );
        $query->execute( [ $email ] );
        $user = $query->fetch( PDO::FETCH_ASSOC );

        // Cost-matched dummy hash avoids skipping expensive verification for unknown accounts
        $hash = $user[ "password_hash" ] ?? "$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.";
        if ( $user && password_verify( $password, $hash ) )
        {
            if ( password_needs_rehash( $hash, PASSWORD_BCRYPT, [ "cost" => PASSWORD_BCRYPT_DEFAULT_COST ] ) )
            {
                $query = db()->prepare( "UPDATE users SET password_hash = ? WHERE id = ?" );
                $query->execute( [ password_hash( $password, PASSWORD_BCRYPT, [ "cost" => PASSWORD_BCRYPT_DEFAULT_COST ] ), $user[ "id" ] ] );
            }
            log_in( ( int )$user[ "id" ] );
        }
        $error = "The email or password is incorrect.";
    }
}
?>
<!doctype html>
<html lang="en-US">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $title ?> | 🎉Watch Party🎉</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/nodes.js" defer></script>
</head>
<body>
<canvas id="nodes" aria-hidden="true"></canvas>
<header><a class="login" href="index.php">Home</a></header>
<main>
    <section class="watch-card auth-card" aria-labelledby="auth-title">
        <h1 id="auth-title"><?= $register ? "Create your account" : "Welcome back" ?></h1>
        <p class="muted"><?= $register ? "Join the party. Watch together." : "Log in to your Watch Party account." ?></p>
        <?php if ( $error ): ?><p class="form-error" role="alert"><?= htmlspecialchars( $error, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" ) ?></p><?php endif; ?>
        <form method="post" class="auth-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars( $_SESSION[ "csrf" ], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" ) ?>">
            <?php if ( $register ): ?>
                <label for="name">Name</label>
                <input id="name" name="name" autocomplete="name" maxlength="100" value="<?= htmlspecialchars( $name, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" ) ?>"
                       required>
            <?php endif; ?>
            <label for="email">Email</label>
            <input id="email" type="email" name="email" autocomplete="email" maxlength="254"
                   value="<?= htmlspecialchars( $email, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" ) ?>" required>
            <label for="password">Password</label>
            <input id="password" type="password" name="password"
                   autocomplete="<?= $register ? "new-password" : "current-password" ?>" <?= $register ? "minlength=\"12\" aria-describedby=\"password-help\"" : "" ?>
                   required>
            <?php if ( $register ): ?>
                <small id="password-help" class="muted">Use 12&ndash;72 bytes. An ASCII character is one byte; some symbols use more.</small>
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
            <?php endif; ?>
            <button class="login primary" type="submit"><?= $title ?></button>
        </form>
        <p class="muted"><?= $register ? "Already have an account?" : "New to Watch Party?" ?> <a href="<?= $register ? "login.php" : "register.php" ?>"><?= $register ? "Log in" : "Register" ?></a>
        </p>
    </section>
</main>
</body>
</html>
