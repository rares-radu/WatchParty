/*+===================================================================
  File:      ROOM-SYNC.JS

  Summary:   This file contains the necessary code to allow us to
             synchronize video playback between room clients.

  Classes:   CPlaybackTracker.

  Origin:    Written by Rareș Radu on 21.10.2026.
===================================================================+*/

"use strict";

/*C+C+++C+++C+++C+++C+++C+++C+++C+++C+++C+++C+++C+++C+++C+++C+++C+++C
  Class:    CPlaybackTracker

  Summary:  This class is used to keep track of the video player
            and make sure it remains synchronized between all room
            members.

  Methods:  Sample
              Records a sample of the video player.
            Command
              Sets the tracker's member variables.
            Snapshot
              Checks if the video should be paused due to a difference
              in timestamps.
            constructor
              Constructor.
C---C---C---C---C---C---C---C---C---C---C---C---C---C---C---C---C-C*/
class CPlaybackTracker
{
    constructor()
    {
        this.nPosition   = 0;
        this.nSampleAt   = null;
        this.nProgressAt = null;
        this.nAdvances   = 0;
        this.bPlaying    = false;
    }

    /*M+M+++M+++M+++M+++M+++M+++M+++M+++M+++M+++M+++M+++M+++M+++M+++M+++M
      Method:   CPlaybackTracker.Sample

      Summary:  This function records the video player's events.

      Args:     number nPosition
                  Video timestamp, in seconds.
                number nNow
                  Time, in milliseconds, at which the event from the
                  player arrived.

      Modifies: [nAdvances, nProgressAt, bPlaying, nPosition, nSampleAt].

      Returns:  void
                  No return value.
    M---M---M---M---M---M---M---M---M---M---M---M---M---M---M---M---M-M*/
    Sample( nPosition, nNow )
    {
        if ( typeof nPosition !== "number" || !Number.isFinite( nPosition ) || nPosition < 0 )
        {
            return;
        }

        if ( this.nSampleAt !== null )
        {
            const nElapsed  = ( nNow - this.nSampleAt ) / 1000;
            const nDelta    = nPosition - this.nPosition;
            const bOrdinary = nElapsed > 0 && nElapsed < 2.5 && nDelta > 0.01 && nDelta <= nElapsed * 2 + 0.35;
            if ( bOrdinary )
            {
                this.nAdvances++;
                this.nProgressAt = nNow;
                if ( this.nAdvances >= 2 )
                {
                    this.bPlaying = true;
                }
            }
            else if ( Math.abs( nDelta ) > 0.01 )
            {
                // A jump updates position but preserves the existing play/pause state
                this.nAdvances = 0;
            }
        }

        this.nPosition = nPosition;
        this.nSampleAt = nNow;
    }

    /*M+M+++M+++M+++M+++M+++M+++M+++M+++M+++M+++M+++M+++M+++M+++M+++M+++M
      Method:   CPlaybackTracker.Command

      Summary:  This function sets the progress of the tracker to a
                certain value.

      Args:     boolean bPlaying
                  Whether the video should currently be playing.
                number nNow
                  Time, in milliseconds, at which the event from the
                  player arrived.

      Modifies: [nPosition, nSampleAt, nProgressAt, bPlaying, nAdvances].

      Returns:  void
                  No return value.
    M---M---M---M---M---M---M---M---M---M---M---M---M---M---M---M---M-M*/
    Command( bPlaying, nNow )
    {
        this.nPosition   = this.Snapshot( nNow ).position;
        this.nSampleAt   = nNow;
        this.nProgressAt = nNow;
        this.bPlaying    = bPlaying;
        this.nAdvances   = 0;
    }

    /*M+M+++M+++M+++M+++M+++M+++M+++M+++M+++M+++M+++M+++M+++M+++M+++M+++M
      Method:   CPlaybackTracker.Snapshot

      Summary:  This function takes a snapshot of the current video
                progress and determines whether the player should be
                stopped based on the difference in timestamps.

      Args:     number nNow
                  Time, in milliseconds, at which the snapshot was taken.

      Modifies: [bPlaying, nAdvances].

      Returns:  void
                  No return value.
    M---M---M---M---M---M---M---M---M---M---M---M---M---M---M---M---M-M*/
    Snapshot( nNow )
    {
        if ( this.nProgressAt === null || nNow - this.nProgressAt >= 2500 )
        {
            this.bPlaying  = false;
            this.nAdvances = 0;
        }

        return {
            // Naming must match what we use in `playback.php`!
            position: this.nPosition + ( this.bPlaying ? Math.min( 2.5, ( nNow - this.nSampleAt ) / 1000 ) : 0 ),
            playing:  this.bPlaying
        };
    }
}

/*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  Function: StartRoomSync

  Summary:  This function sets up and initializes the room
            synchronization.

  Returns:  void
              No return value.
-----------------------------------------------------------------F-F*/
function StartRoomSync()
{
    const elIframe = document.getElementById( "room-player" );
    if ( !elIframe )
    {
        return;
    }

    const bIsOwner = elIframe.dataset.owner === "1";

    const sEndpoint     = `playback.php?code=${ encodeURIComponent( elIframe.dataset.code ) }`;
    const elStatus      = document.getElementById( "sync-status" );

    const Tracker        = new CPlaybackTracker();
    let   Remote         = null;
    let   nReceivedAt    = 0;
    let   bReady         = false;
    let   bInitialized   = false;
    let   bStopped       = false;
    let   bInFlight      = false;
    let   sLastCommand   = null;
    let   nCommandAt     = -Infinity;
    let   nLastSeekAt    = -Infinity;
    let   Restore        = null;
    let   nRestoreSentAt = -Infinity;
    let   Timer;

    /*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
      Function: RestoreOwner

      Summary:  This function restores the video timestamp to that of
                what it was before the host lost connection.

      Returns:  void
                  No return value.
    -----------------------------------------------------------------F-F*/
    function RestoreOwner()
    {
        if ( !bReady || !Restore || performance.now() - nRestoreSentAt < 1000 )
        {
            return;
        }

        /*--------------------------------------------------------------------
          A returning host resumes from the saved position, paused, to avoid
          publishing the iframe's initial zero over an existing room state.
        --------------------------------------------------------------------*/
        elIframe.contentWindow.postMessage( { action: "pause" }, "*" );
        elIframe.contentWindow.postMessage( { action: "seek", time: Restore.position }, "*" );
        nRestoreSentAt = performance.now();
    }

    /*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
      Function: SyncPlayback

      Summary:  What MyLocalFunction is for and what it does.

      Args:     MYTYPE MyFunctionArgument1
                  Description.
                MYTYPE MyFunctionArgument1
                  Description.

      Returns:  MyReturnType
                  Description.
    -----------------------------------------------------------------F-F*/
    function SyncPlayback( bForce = false )
    {
        if ( bIsOwner || !bReady )
        {
            return;
        }

        const nNow     = performance.now();
        const bFresh   = Remote && ( Remote.age + ( nNow - nReceivedAt ) / 1000 ) < 8;
        const bPlaying = !!( bFresh && Remote.playing );

        const nTarget       = Remote ? Remote.position + ( bPlaying ? Remote.age + ( nNow - nReceivedAt ) / 1000 : 0 ) : 0;
        const LocalSnapshot = Tracker.Snapshot( nNow );
        if ( Remote && bForce || Math.abs( LocalSnapshot.position - nTarget ) > ( bPlaying ? 1.5 : 0.35 ) && nNow - nLastSeekAt >= 1000 )
        {
            elIframe.contentWindow.postMessage( { action: "seek", time: Math.max( 0, nTarget ) }, "*" );
            nLastSeekAt = nNow;
        }

        const sAction = bPlaying ? "play" : "pause";
        if ( bForce || sLastCommand !== sAction || ( nNow - nCommandAt ) >= 2000 )
        {
            elIframe.contentWindow.postMessage( { action: sAction }, "*" );
            sLastCommand = sAction;
            nCommandAt   = nNow;
        }

        if ( !Remote )
        {
            elStatus.textContent = "Waiting for the host to start the video.";
        }
        else if ( !bFresh )
        {
            elStatus.textContent = "Host connection lost. Playback is paused until the host returns.";
        }
        else if ( bPlaying )
        {
            elStatus.textContent = "Following the host's playback.";
        }
        else
        {
            elStatus.textContent = "Host paused or buffering.";
        }
    }

    window.addEventListener(
                             "message",
                             function ( Event )
                             {
                                 if ( Event.source !== elIframe.contentWindow )
                                 {
                                     return;
                                 }

                                 const Data = Event.data;
                                 if ( !Data || typeof Data !== "object" )
                                 {
                                     return;
                                 }

                                 if ( Data.event !== "frameXTV:ready" && Data.event !== "frameXTV:timeupdate" )
                                 {
                                     return;
                                 }

                                 const bFirstReady = !bReady;
                                 bReady = true;
                                 if ( bIsOwner && Restore )
                                 {
                                     // Ignore initial zero/stale telemetry until the restore seek is acknowledged
                                     if ( Data.event === "frameXTV:timeupdate" &&
                                          typeof Data.currentTime === "number" &&
                                          Number.isFinite( Data.currentTime )  &&
                                          Math.abs( Data.currentTime - Restore.position ) < 1 )
                                     {
                                         Tracker.Sample( Data.currentTime, performance.now() );
                                         Tracker.Command( false, performance.now() );
                                         Restore = null;
                                     }
                                     else
                                     {
                                         RestoreOwner();
                                     }

                                     return;
                                 }
                                 if ( Data.event === "frameXTV:timeupdate" && ( !bIsOwner || bInitialized ) )
                                 {
                                     Tracker.Sample( Data.currentTime, performance.now() );
                                 }

                                 if ( bFirstReady )
                                 {
                                     // The player is ready, meaning that commands can be sent
                                     SyncPlayback( true );
                                 }
                             }
                            );

    elIframe.addEventListener(
                              "load",
                              function ()
                              {
                                  bReady       = false;
                                  sLastCommand = null;
                                  if ( bIsOwner && Tracker.nSampleAt !== null )
                                  {
                                      Restore = Tracker.Snapshot( performance.now() );
                                  }
                              }
                             );

    /*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
      Function: ExchangePlayerData

      Summary:  This shares the video player's current timestamp and
                state with the `playback.php` backend code so that it
                can be synchronized between clients.

      Returns:  Promise< void >
                  Async promise.
    -----------------------------------------------------------------F-F*/
    async function ExchangePlayerData()
    {
        if ( bInFlight || bStopped )
        {
            return;
        }

        clearTimeout( Timer );
        bInFlight = true;

        const nStart = performance.now();

        try
        {
            let Request = { cache: "no-store", signal: AbortSignal.timeout( 5000 ) };

            const bPublishing = bIsOwner && bInitialized && !Restore && Tracker.nSampleAt !== null;
            if ( bPublishing )
            {
                const State = Tracker.Snapshot( performance.now() );
                Request = {
                    ...Request,
                    method: "POST",
                    body:   new URLSearchParams(
                        {
                            csrf:     elIframe.dataset.csrf,
                            position: String( State.position ),
                            playing:  State.playing ? "1" : "0"
                        }
                    )
                };
            }

            const Response = await fetch( sEndpoint, Request );
            const Data     = await Response.json();
            if ( !Response.ok )
            {
                if ( Response.status === 403 || Response.status === 404 )
                {
                    bStopped = true;
                }
                throw new Error( Data.error || "Playback sync is unavailable." );
            }

            if ( !bPublishing )
            {
                Remote      = Data.state;
                nReceivedAt = performance.now();

                if ( Remote )
                {
                    // Server age avoids client clock skew. Half the round trip estimates transit time.
                    Remote.age += ( nReceivedAt - nStart ) / 2000;
                }

                if ( bIsOwner && !bInitialized && Remote )
                {
                    Restore = Remote;
                }
            }

            bInitialized = true;
            if ( bIsOwner )
            {
                RestoreOwner();
                if ( Restore )
                {
                    elStatus.textContent = "Restoring the host's position. Start the video if the player needs interaction.";
                }
                else if ( Tracker.nSampleAt === null )
                {
                    elStatus.textContent = "Start the video to share playback.";
                }
                else if ( Tracker.Snapshot( performance.now() ).playing )
                {
                    elStatus.textContent = "Sharing playback with the room.";
                }
                else
                {
                    elStatus.textContent = "Room paused or buffering.";
                }
            }
            else
            {
                SyncPlayback();
            }
        }
        catch ( Error )
        {
            if ( !bIsOwner )
            {
                SyncPlayback();
            }

            if ( Error.name === "SyntaxError" || Error.name === "TimeoutError" || Error.name === "TypeError" )
            {
                elStatus.textContent = "Playback sync disconnected. Retrying...";
            }
            else
            {
                elStatus.textContent = Error.message;
            }
        }
        finally
        {
            bInFlight = false;
            if ( !bStopped )
            {
                Timer = setTimeout( ExchangePlayerData, 1000 );
            }
        }
    }

    if ( !bIsOwner )
    {
        document.getElementById( "room-resync" ).addEventListener(
                                                                           "click",
                                                                           function ()
                                                                           {
                                                                               SyncPlayback( true );
                                                                               ExchangePlayerData();
                                                                           }
                                                                          );
    }

    // We hold guests even if the network request is stalled
    setInterval(
                function ()
                {
                    if ( !bIsOwner && Remote && ( Remote.age + ( performance.now() - nReceivedAt ) / 1000 ) >= 8 )
                    {
                        SyncPlayback();
                    }
                },
                500
               );
    document.addEventListener(
                              "visibilitychange",
                              function ()
                              {
                                  if ( !document.hidden )
                                  {
                                      ExchangePlayerData();
                                  }
                              }
                             );

    // Synchronize right away
    ExchangePlayerData();
}

document.addEventListener( "DOMContentLoaded", StartRoomSync );
