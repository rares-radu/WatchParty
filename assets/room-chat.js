/*+===================================================================
  File:      ROOM-CHAT.JS

  Summary:   This file contains the frontend code responsible for
             fetching and displaying a room's chat messages.

  Origin:    Written by Rareș Radu on 03.11.2026.
===================================================================+*/

"use strict";

const LOAD_ERROR = "Could not load messages. Retrying...";

let g_sEndpoint, g_elLog, g_elStatus, g_elForm;
let g_bLoading = false;

/*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  Function: RefreshChat

  Summary:  This function pulls all the chat messages of a room and
            populates the chat box with them.

  Returns:  Promise< void >
              Async promise.
-----------------------------------------------------------------F-F*/
async function RefreshChat()
{
    if ( g_bLoading )
    {
        // We're still fetching messages
        return;
    }

    g_bLoading = true;

    try
    {
        const Response = await fetch( g_sEndpoint );
        if ( !Response.ok )
        {
            throw new Error( "Could not reach chat endpoint." );
        }

        const Messages = await Response.json();
        const bBottom  = g_elLog.scrollHeight - g_elLog.scrollTop - ( g_elLog.clientHeight < 4 );
        const Rows     = new Map( Array.from( g_elLog.children, function ( Row ) { return [ Row.dataset.id, Row ]; } ) );

        for ( const Msg of Messages )
        {
            if ( Rows.delete( Msg.id ) )
            {
                continue;
            }

            const elRow      = document.createElement( "p" );
            elRow.dataset.id = Msg.id;

            const elName       = document.createElement( "b" );
            elName.textContent = Msg.name;

            elRow.append( elName, ": " + Msg.message );

            g_elLog.append( elRow );
        }

        for ( const Row of Rows.values() )
        {
            Row.remove();
        }

        if ( bBottom )
        {
            g_elLog.scrollTop = g_elLog.scrollHeight;
        }

        if ( g_elStatus.textContent === LOAD_ERROR )
        {
            g_elStatus.textContent = "";
        }
    }
    catch
    {
        if ( !g_elStatus.textContent )
        {
            g_elStatus.textContent = LOAD_ERROR;
        }
    }
    finally
    {
        g_bLoading = false;
    }
}

/*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  Function: main

  Summary:  This function contains the main logic of the script and
            should should run only when the DOM has fully loaded. Its
            purpose is to set up all event listeners and global
            variables we use for displaying the chat messages.

  Returns:  void
              No return value.
-----------------------------------------------------------------F-F*/
function main()
{
    g_sEndpoint = "chat.php?code=" + encodeURIComponent( document.querySelector( "#room-player" ).dataset.code );
    g_elLog     = document.querySelector( "#chat-messages" );
    g_elStatus  = document.querySelector( "#chat-status" );
    g_elForm    = document.querySelector( "#chat-form" );

    // Guests don't have the form
    g_elForm?.addEventListener(
                               "submit",
                               async function ( Event )
                               {
                                   Event.preventDefault();

                                   const elButton = g_elForm.querySelector( "button" );
                                   if ( elButton.disabled )
                                   {
                                       return;
                                   }

                                   const Msg       = g_elForm.elements.message;
                                   const sOriginal = Msg.value;
                                   if ( !sOriginal.trim() )
                                   {
                                       g_elStatus.textContent = "Enter a message.";
                                       Msg.focus();
                                       return;
                                   }

                                   elButton.disabled = true;

                                   try
                                   {
                                       const Response = await fetch( g_sEndpoint, { method: "POST", body: new FormData( g_elForm ) } );
                                       if ( Response.status === 401 )
                                       {
                                           g_elForm.remove();
                                           document.querySelector( "#chat-login" ).hidden = false;
                                           g_elStatus.textContent                         = "";
                                           return;
                                       }

                                       const Result = await Response.json();
                                       if ( !Response.ok )
                                       {
                                           g_elStatus.textContent = Result.error;
                                           return;
                                       }

                                       if ( Msg.value === sOriginal )
                                       {
                                           Msg.value = "";
                                       }

                                       g_elStatus.textContent = "";
                                       await RefreshChat();
                                   }
                                   catch
                                   {
                                       g_elStatus.textContent = "Could not confirm delivery. Check the chat before sending again.";
                                   }
                                   finally
                                   {
                                       elButton.disabled = false;
                                   }
                               }
                              );

    RefreshChat(); // Start pulling immediately
    window.setInterval( RefreshChat, 2500 );
}

document.addEventListener( "DOMContentLoaded", main );
