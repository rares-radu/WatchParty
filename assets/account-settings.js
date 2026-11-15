/*+===================================================================
  File:    ACCOUNT-SETTINGS.JS

  Summary: This file contains the logic for opening and closing the
           account settings dropdown menu on the landing page of
           the website.

  Origin:  Written by Rareș Radu on 13.11.2026.
===================================================================+*/

/*--------------------------------------------------------------------
  NOTE: This script must be deferred, otherwise the account settings
  dialog might not have been parsed yet!
--------------------------------------------------------------------*/

const elSettingsDialog = document.getElementById( "account-settings" );

document.getElementById( "open-settings" ).addEventListener(
    "click",
    function ()
    {
        document.getElementById( "account-menu" ).hidePopover();
        elSettingsDialog.showModal();
    }
);

elSettingsDialog.addEventListener(
    "close",
    function ()
    {
        for ( const elForm of elSettingsDialog.querySelectorAll( "form" ) )
        {
            elForm.reset();
        }

        elSettingsDialog.querySelector( "[role='status'], [role='alert']" )?.remove();
        document.getElementById( "account-toggle" ).focus();
    }
);

if ( elSettingsDialog.hasAttribute( "data-reopen" ) )
{
    elSettingsDialog.showModal();
}
