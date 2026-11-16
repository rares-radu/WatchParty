<?php
declare( strict_types=1 );

use Illuminate\Encryption\Encrypter;
use RemoteMerge\Totp\TotpFactory;

/*F+F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F
  Function: get_totp_encrypter

  Summary:  This function creates a new encrypter instance based on
            the `WATCHPARTY_TOTP_KEY` environment variable.

  Returns:  Encrypter
              A new encrypter instance.
F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F-F*/
function get_totp_encrypter(): Encrypter
{
    $key = base64_decode( $_ENV[ "WATCHPARTY_TOTP_KEY" ], true );
    if ( $key === false || strlen( $key ) !== 32 )
    {
        throw new RuntimeException( "Configure WATCHPARTY_TOTP_KEY with a Base64-encoded 32-byte key." );
    }

    return new Encrypter( $key, "aes-256-gcm" );
}

/*F+F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F
  Function: totp_record

  Summary:  This function retrieves a user's encrypted secret and
            last slice from the database.

  Args:     int $userId
              User ID.

  Returns:  ?array
              If the user has TOTP 2FA enabled, the return value is
              an array with the following keys: `encrypted_secret`
              and `last_slice`.

              If the user has TOTP 2FA disabled, the return value is
              `null`.
F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F-F*/
function totp_record( int $userId ): ?array
{
    $query = db()->prepare( "SELECT encrypted_secret, last_slice FROM user_totp WHERE user_id = ?" );
    $query->execute( [ $userId ] );
    return $query->fetch( PDO::FETCH_ASSOC ) ?: null;
}

/*F+F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F
  Function: submitted_totp_slice

  Summary:  This function determines whether a TOTP code from a POST
            request is valid.

  Args:     string $secret
              Secret key in base32 format.
            int $lastAcceptedSlice
              The last time slice that was successfully accepted.

  Returns:  ?int
              If the code is valid, the return value is the matched
              time slice.

              If the code is invalid, the return value is `null`.
F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F-F*/
function submitted_totp_slice( string $secret, int $lastAcceptedSlice ): ?int
{
    $code = trim( get_query_parameter( "totp_code" ) );
    if ( !preg_match( "/^[0-9]{6}$/D", $code ) )
    {
        return null;
    }

    return TotpFactory::create()->verifyCodeOnce( $secret, $code, $lastAcceptedSlice );
}

/*F+F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F
  Function: consume_totp_code

  Summary:  This function processes tests a TOTP code received in a
            POST request and, if valid, either disables 2FA on the
            user's account or updates the last accepted slice
            database record.

  Args:     int $userId
              User ID.
            array $record
              User's database record.
            bool $disable
              If this variable is non-zero, the function will disable
              2FA on the user's account.

  Returns:  bool
              If the TOTP code is valid, the return value is `true`.

              If the TOTP code is invalid, the return value is `false`.
F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F-F*/
function consume_totp_code( int $userId, array $record, bool $disable ): bool
{
    $secret = get_totp_encrypter()->decryptString( $record[ "encrypted_secret" ] );
    $slice  = submitted_totp_slice( $secret, ( int )$record[ "last_slice" ] );
    if ( $slice === null )
    {
        return false;
    }

    $where  = " WHERE user_id = ? AND encrypted_secret = ? AND last_slice < ?";
    $params = [ $userId, $record[ "encrypted_secret" ], $slice ];
    if ( $disable )
    {
        $query = db()->prepare( "DELETE FROM user_totp" . $where );
    }
    else
    {
        $query = db()->prepare( "UPDATE user_totp SET last_slice = ?" . $where );
        array_unshift( $params, $slice );
    }

    $query->execute( $params );
    return ( $query->rowCount() === 1 );
}

/*F+F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F
  Function: pending_totp_secret

  Summary:  This function retrieves a pending TOTP code's secret.

  Args:     int $userId
              ID of user the TOTP code is meant for.

  Returns:  ?string
              If the function succeeds, the return value is the TOTP
              secret.

              If the function fails, the return value is `null`.
F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F-F*/
function pending_totp_secret( int $userId ): ?string
{
    $pending = $_SESSION[ "totp_setup" ] ?? null;
    if ( !$pending || $pending[ "user_id" ] !== $userId || $pending[ "expires" ] <= time() )
    {
        unset( $_SESSION[ "totp_setup" ] );
        return null;
    }

    return $pending[ "secret" ];
}

/*F+F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F
  Function: update_totp_settings

  Summary:  This function manipulates a user's 2FA TOTP settings.

  Args:     int $userId
              User ID.
            string $action
              If this variable is `"totp_start"`, the function will
              generate a secret code and display it on the page.

              If this variable is `"totp_enable"` and the submitted
              TOTP code matches the pending secret, the function will
              enable TOTP on the user's account.

              If this variable is `"totp_disable"` and the submitted
              TOTP code is valid, the function will disable TOTP on
              the user's account

  Returns:  string
              If the function succeeds, the return value is an empty
              string.

              If the function fails, the return value is a
              descriptive message of the issue.
F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F-F*/
function update_totp_settings( int $userId, string $action ): string
{
    if ( !allow_authentication_attempt() )
    {
        http_response_code( 429 );
        header( "Retry-After: 900" );
        return "Too many attempts. Please try again in 15 minutes.";
    }

    $record = totp_record( $userId );
    if ( $action === "totp_disable" )
    {
        if ( !$record || !consume_totp_code( $userId, $record, true ) )
        {
            return "Enter a fresh code from your authenticator app.";
        }

        unset( $_SESSION[ "totp_setup" ] );
        $_SESSION[ "settings_notice" ] = "Two-factor authentication disabled.";
        return "";
    }

    if ( $record )
    {
        return "Two-factor authentication is already enabled.";
    }

    if ( $action === "totp_start" )
    {
        $query = db()->prepare( "SELECT password_hash FROM users WHERE id = ?" );
        $query->execute( [ $userId ] );
        $password = get_query_parameter( "current_password" );
        if ( strlen( $password ) > 72 || str_contains( $password, "\0" ) || !password_verify( $password, $query->fetchColumn() ) )
        {
            return "Your current password is incorrect.";
        }

        get_totp_encrypter(); // Check configuration before generating a new secret key
        $_SESSION[ "totp_setup" ] = [
                                     "user_id" => $userId,
                                     "expires" => time() + 600,
                                     "secret"  => TotpFactory::create()->generateSecret()
                                    ];

        $_SESSION[ "settings_notice" ] = "Finish setup within 10 minutes.";
        return "";
    }

    $secret = pending_totp_secret( $userId );
    if ( $secret === null )
    {
        return "Setup expired. Start again to get a new key.";
    }

    $slice = submitted_totp_slice( $secret, 0 );
    if ( $slice === null )
    {
        return "Enter the six-digit code for this setup key.";
    }

    try
    {
        $query = db()->prepare( "INSERT INTO user_totp ( user_id, encrypted_secret, last_slice ) VALUES ( ?, ?, ? )" );
        $query->execute( [ $userId, get_totp_encrypter()->encryptString( $secret ), $slice ] );
    }
    catch ( PDOException $exception )
    {
        if ( ( $exception->errorInfo[ 1 ] ?? null ) !== 1062 )
        {
            throw $exception;
        }
        return "Two-factor authentication is already enabled.";
    }

    unset( $_SESSION[ "totp_setup" ] );
    $_SESSION[ "settings_notice" ] = "Two-factor authentication enabled.";
    return "";
}
