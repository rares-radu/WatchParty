<?php
declare( strict_types=1 );

/*F+F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F+++F
  Function: update_account_settings

  Summary:  This function updates a user's username or password
            based on the values from a POST request.

  Args:     int $userId
              ID of the user whose settings to change.

  Returns:  string
              If changing the setting failed, the function returns
              a string describing what went wrong.

              If changing the setting succeeded, the function returns
              an empty string.
F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F---F-F*/
function update_account_settings( int $userId ): string
{
    $action = get_query_parameter( "action" );
    if ( $action === "username" )
    {
        $name = trim( get_query_parameter( "name" ) );
        if ( $name === "" || strlen( $name ) > 100 )
        {
            return "Enter a username of up to 100 characters.";
        }

        $query = db()->prepare( "UPDATE users SET name = ? WHERE id = ?" );
        $query->execute( [ $name, $userId ] );
        $_SESSION[ "settings_notice" ] = "Username updated.";
        return "";
    }

    if ( $action !== "password" )
    {
        return "Choose a setting to update.";
    }

    $password = get_query_parameter( "password" );
    if ( strlen( $password ) < 12 || strlen( $password ) > 72 || str_contains( $password, "\0" ) )
    {
        return "Use a password between 12 and 72 characters without null characters.";
    }
    if ( $password !== get_query_parameter( "password_confirmation" ) )
    {
        return "The new passwords do not match.";
    }

    if ( !allow_authentication_attempt() )
    {
        http_response_code( 429 );
        header( "Retry-After: 900" );
        return "Too many attempts. Please try again in 15 minutes.";
    }

    $query = db()->prepare( "SELECT password_hash FROM users WHERE id = ?" );
    $query->execute( [ $userId ] );
    $hash = $query->fetchColumn();
    $oldPassword = get_query_parameter( "old_password" );
    if ( !$hash || strlen( $oldPassword ) > 72 || str_contains( $oldPassword, "\0" ) || !password_verify( $oldPassword, $hash ) )
    {
        return "Your current password is incorrect.";
    }

    $query = db()->prepare( "UPDATE users SET password_hash = ? WHERE id = ? AND BINARY password_hash = ?" );
    $query->execute( [ password_hash( $password, PASSWORD_BCRYPT ), $userId, $hash ] );
    if ( !$query->rowCount() )
    {
        return "Your password changed in another session. Enter your current password and try again.";
    }
    $_SESSION[ "settings_notice" ] = "Password updated.";
    return "";
}
