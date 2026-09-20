/*+===================================================================
  File:    NODES.JS

  Summary: This file contains the implementation of the moving nodes
           background of the website.

  Origin:  Written by Rareș Radu on 11.09.2026.
===================================================================+*/

"use strict";

const NODE_SPEED  = 24;
const NODE_RADIUS = 2.5;

let g_elCanvas            = null;
let g_Ctx                 = null;
let g_aNodes              = [];
let g_nAnimationRequestId = 0;
let g_nLastTime           = 0;
let g_bCursorVisible      = false;

const g_CursorNode = { x: 0, y: 0, vx: 0, vy: 0 };

/*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  Function: OnWindowResize

  Summary:  This function is called every time a "resize" event is
            fired from the window. Its purpose is to make sure
            that the canvas covers the whole window.

  Returns:  void
              No return value.
-----------------------------------------------------------------F-F*/
function OnWindowResize()
{
    g_elCanvas.width  = Math.round( window.innerWidth * window.devicePixelRatio );
    g_elCanvas.height = Math.round( window.innerHeight * window.devicePixelRatio );
    g_Ctx.setTransform( window.devicePixelRatio, 0, 0, window.devicePixelRatio, 0, 0 );

    g_aNodes = Array.from(
        { length: Math.round( window.innerWidth * window.innerHeight / 11000 ) },
        function ()
        {
            return {
                x:  Math.random() * window.innerWidth,
                y:  Math.random() * window.innerHeight,
                vx: ( Math.random() < 0.5 ) ? NODE_SPEED : -NODE_SPEED,
                vy: ( Math.random() < 0.5 ) ? NODE_SPEED : -NODE_SPEED
            };
        } );
    DrawNodes( 0 );
}

/*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  Function: DrawNodes

  Summary:  This function draws the nodes and links between them on
            the canvas.

  Args:     Number nDelta
              The delta value with which to move each node on the
              canvas.

  Returns:  void
              No return value.
-----------------------------------------------------------------F-F*/
function DrawNodes( nDelta )
{
    g_Ctx.clearRect( 0, 0, window.innerWidth, window.innerHeight );

    const nMaxDist = Math.min( 185, window.innerWidth * 0.35 );

    for ( const pt of g_aNodes )
    {
        pt.x += pt.vx * nDelta;
        pt.y += pt.vy * nDelta;

        // Make sure we don't leave the screen space
        if ( pt.x < 0 || pt.x > window.innerWidth )
        {
            pt.x   = Math.max( 0, Math.min( window.innerWidth, pt.x ) );
            pt.vx *= -1;
        }

        if ( pt.y < 0 || pt.y > window.innerHeight )
        {
            pt.y   = Math.max( 0, Math.min( window.innerHeight, pt.y ) );
            pt.vy *= -1;
        }
    }

    // Add our cursor to the array of nodes
    const apt = g_bCursorVisible ? g_aNodes.concat( g_CursorNode ) : g_aNodes;

    for ( let i = 0; i < apt.length; i++ )
    {
        const pt1 = apt[ i ];

        // Draw the nodes
        g_Ctx.fillStyle = "rgba( 255, 255, 255, 0.58 )";
        g_Ctx.beginPath();
        g_Ctx.arc( pt1.x, pt1.y, NODE_RADIUS, 0, Math.PI * 2 );
        g_Ctx.fill();

        // Draw lines between nodes that are in reach of each other
        for ( let j = i + 1; j < apt.length; j++ )
        {
            const pt2   = apt[ j ];
            const nDist = Math.hypot( pt1.x - pt2.x, pt1.y - pt2.y );
            if ( nDist >= nMaxDist )
            {
                continue;
            }

            // Slowly fade out the line as the distance increases
            g_Ctx.strokeStyle = `rgba( 255, 255, 255, ${ ( 1 - nDist / nMaxDist ) * 0.24 } )`;
            g_Ctx.lineWidth   = 0.75;
            g_Ctx.beginPath();
            g_Ctx.moveTo( pt1.x, pt1.y );
            g_Ctx.lineTo( pt2.x, pt2.y );
            g_Ctx.stroke();
        }
    }
}

/*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  Function: AnimateNodes

  Summary:  This function is responsible for animating the nodes on
            the canvas.

  Args:     Number nTime
              Current timestamp.

  Returns:  void
              No return value.
-----------------------------------------------------------------F-F*/
function AnimateNodes( nTime )
{
    DrawNodes( ( g_nLastTime !== 0 ) ? Math.min( ( nTime - g_nLastTime ) / 1000, 0.05 ) : 0 );
    g_nLastTime           = nTime;
    g_nAnimationRequestId = requestAnimationFrame( AnimateNodes );
}

/*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  Function: OnPointerMove

  Summary:  This function is called every time the window sends a
            "pointermove" event. Its purpose is to record the client's
            current X and Y positions on the screen.

  Returns:  void
              No return value.
-----------------------------------------------------------------F-F*/
function OnPointerMove( event )
{
    g_CursorNode.x   = event.clientX;
    g_CursorNode.y   = event.clientY;
    g_bCursorVisible = true;

    DrawNodes( 0 );
}

/*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  Function: HideCursor

  Summary:  This function is called every time document sends a
            "pointerleave" event or the window sends a "blur" event.
            Its purpose is to tell the renderer to stop rendering the
            node following the user cursor.

  Returns:  void
              No return value.
-----------------------------------------------------------------F-F*/
function OnLoseFocus()
{
    g_bCursorVisible = false;
    DrawNodes( 0 );
}

/*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  Function: OnVisibilityChange

  Summary:  This function is called every time document sends a
            "visibilitychange" event. Its purpose is to stop and start
            the animation when the window loses or gains focus.

  Returns:  void
              No return value.
-----------------------------------------------------------------F-F*/
function OnVisibilityChange()
{
    cancelAnimationFrame( g_nAnimationRequestId );
    g_nLastTime = 0;
    if ( !document.hidden )
    {
        g_nAnimationRequestId = requestAnimationFrame( AnimateNodes );
    }
}

/*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  Function: main

  Summary:  This function contains the main logic of this script and
            is what should run when the DOM has fully loaded. Its
            purpose is to set up all event listeners we use for
            drawing the nodes on the background canvas.

  Returns:  void
              No return value.
-----------------------------------------------------------------F-F*/
function main()
{
    g_elCanvas = document.getElementById( "nodes" );
    g_Ctx      = g_elCanvas.getContext( "2d" );
    if ( !g_Ctx )
    {
        // Browser doesn't support drawing
        return;
    }

    window.addEventListener( "resize", OnWindowResize );
    window.addEventListener( "pointermove", OnPointerMove );
    document.documentElement.addEventListener( "pointerleave", OnLoseFocus );
    window.addEventListener( "blur", OnLoseFocus );
    document.addEventListener( "visibilitychange", OnVisibilityChange );

    // Fire these at least once
    OnWindowResize();
    OnVisibilityChange();
}

document.addEventListener( "DOMContentLoaded", main );
