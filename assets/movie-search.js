/*+===================================================================
  File:    MOVIE-SEARCH.JS

  Summary: This file contains the logic for the dropdown search
           on the landing page of the website.

  Origin:  Written by Rareș Radu on 06.10.2026.
===================================================================+*/

let g_elSearch;
let g_elInput;
let g_elDropdown;
let g_elStatus;
let g_elResults;

let g_nTimer;
let g_AbortController;
let g_nVersion    = 0;
let g_bDismissed  = false;

/*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  Function: OpenDropdown

  Summary:  This function opens the dropdown search menu.

  Returns:  void
              No return value.
-----------------------------------------------------------------F-F*/
function OpenDropdown()
{
    if ( g_bDismissed || document.activeElement !== g_elInput )
    {
        return;
    }
    g_elDropdown.hidden = false;
    g_elInput.setAttribute( "aria-expanded", "true" );
}

/*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  Function: CloseDropdown

  Summary:  This function closes the dropdown search menu.

  Returns:  void
              No return value.
-----------------------------------------------------------------F-F*/
function CloseDropdown()
{
    g_bDismissed        = true;
    g_elDropdown.hidden = true;
    g_elInput.setAttribute( "aria-expanded", "false" );
}

/*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  Function: CancelMovieFetch

  Summary:  This function cancels the movie fetching process.

  Returns:  void
              No return value.
-----------------------------------------------------------------F-F*/
function CancelMovieFetch()
{
    clearTimeout( g_nTimer );
    g_AbortController?.abort();
    g_nVersion++;
}

/*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  Function: ShowMessage

  Summary:  This function displays a message in the dropdown search
            result HTML element.

  Returns:  void
              No return value.
-----------------------------------------------------------------F-F*/
function ShowMessage( message )
{
    g_elResults.replaceChildren();
    g_elStatus.textContent = message;
    OpenDropdown();
}

/*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  Function: GetPosterFallback

  Summary:  This function returns a fallback `<span>` HTML element
            in case a movie doesn't have a poster.

  Returns:  void
              No return value.
-----------------------------------------------------------------F-F*/
function GetPosterFallback()
{
    const elFallback       = document.createElement( "span" );
    elFallback.className   = "movie-poster-fallback";
    elFallback.textContent = "No poster";
    return elFallback;
}

/*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  Function: FetchMovies

  Summary:  This function tries to fetch the list of movies from
            TMDB based on the movie's name.

  Args:     string sQuery
              Movie name.
            number nRequestVersion
              Request version number.

  Returns:  Promise< void >
              Async promise.
-----------------------------------------------------------------F-F*/
async function FetchMovies( sQuery, nRequestVersion )
{
    g_AbortController = new AbortController();
    try
    {
        const Response = await fetch(
                                     `movie-search.php?query=${ encodeURIComponent( sQuery ) }`,
                                     {
                                         signal:  g_AbortController.signal,
                                         headers: { Accept: "application/json" }
                                     }
                                    );
        const Data = await Response.json();
        if ( nRequestVersion !== g_nVersion )
        {
            return;
        }
        if ( !Response.ok )
        {
            throw new Error( Data.error || "Movie search is unavailable. Please try again." );
        }

        // Clear the results
        g_elResults.replaceChildren();

        // Display all the movies we got from TMDB based on the query
        for ( const Movie of Data.results )
        {
            const elRow  = document.createElement( "li" );
            const elForm = document.createElement( "form" );

            elForm.method = "post";
            elForm.action = "create-room.php";

            for ( const [ name, value ] of Object.entries(
                {
                    csrf:        g_elSearch.dataset.csrf,
                    tmdb_id:     Movie.id,
                    movie_title: Movie.title
                } ) )
            {
                const elField = document.createElement( "input" );
                elField.type  = "hidden";
                elField.name  = name;
                elField.value = value;
                elForm.append( elField );
            }

            const elButton = document.createElement( "button" );
            elButton.type      = "submit";
            elButton.className = "movie-search-result";
            elButton.setAttribute( "aria-label", `Create a room for ${ Movie.title }` );
            elForm.addEventListener( "submit", function () { elButton.disabled = true; } );

            if ( Movie.poster_url )
            {
                const elPoster     = document.createElement( "img" );
                elPoster.className = "movie-poster";
                elPoster.src       = Movie.poster_url;
                elPoster.alt       = "";
                elPoster.width     = 46;
                elPoster.height    = 69;
                elPoster.loading   = "lazy";
                elPoster.addEventListener(
                                          "error",
                                          function ()
                                          {
                                              elPoster.replaceWith( GetPosterFallback() )
                                          },
                                          { once: true }
                                         );
                elButton.append( elPoster );
            }
            else
            {
                elButton.append( GetPosterFallback() );
            }

            const elTitle = document.createElement( "span" );
            elTitle.textContent = Movie.title;
            elButton.append( elTitle );
            elForm.append( elButton );
            elRow.append( elForm );
            g_elResults.append( elRow );
        }
        g_elStatus.textContent = Data.results.length ? `${ Data.results.length } movies found.` : "No movies found. Try another name.";
        if ( document.activeElement === g_elInput )
        {
            OpenDropdown();
        }
    }
    catch ( error )
    {
        if ( error.name === "AbortError" || nRequestVersion !== g_nVersion )
        {
            return;
        }
        ShowMessage( error instanceof SyntaxError ? "Movie search is unavailable. Please try again." : error.message );
    }
}

/*F+F+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  Function: main

  Summary:  This function contains the main logic of this script and
            is what should run when the DOM has fully loaded. Its
            purpose is to set up all event listeners we use for
            the dropdown search box.

  Returns:  void
              No return value.
-----------------------------------------------------------------F-F*/
function main()
{
    g_elSearch   = document.getElementById( "movie-search" );
    g_elInput    = document.getElementById( "movie-query" );
    g_elDropdown = document.getElementById( "movie-search-dropdown" );
    g_elStatus   = document.getElementById( "movie-search-status" );
    g_elResults  = document.getElementById( "movie-search-results" );

    g_elInput.addEventListener(
                               "input",
                               function ()
                               {
                                   CancelMovieFetch();
                                   g_bDismissed = false;

                                   const sQuery = g_elInput.value.trim();
                                   if ( !sQuery )
                                   {
                                       g_elResults.replaceChildren();
                                       g_elStatus.textContent = "";
                                       CloseDropdown();
                                       return;
                                   }
                                   ShowMessage( "Searching movies..." );
                                   const nRequestVersion = g_nVersion;

                                   g_nTimer = setTimeout(
                                                         async function () { await FetchMovies( sQuery, nRequestVersion ) },
                                                         300
                                                        );
                               }
                              );
    g_elInput.addEventListener(
                               "focus",
                               function ()
                               {
                                   g_bDismissed = false;
                                   if ( g_elInput.value.trim() && g_elStatus.textContent )
                                   {
                                       OpenDropdown();
                                   }
                               }
                              );
    g_elSearch.addEventListener(
                                "keydown",
                                function ( Event )
                                {
                                    if ( Event.key === "Escape" )
                                    {
                                        CloseDropdown();
                                    }
                                }
                               );
    document.addEventListener(
                              "pointerdown",
                              function ( Event )
                              {
                                  if ( !g_elSearch.contains( Event.target ) )
                                  {
                                      CloseDropdown();
                                  }
                              }
                             );
    g_elSearch.addEventListener(
                                "focusout",
                                function ( Event )
                                {
                                    if ( !g_elSearch.contains( Event.relatedTarget ) )
                                    {
                                        CloseDropdown();
                                    }
                                }
                               );
}

document.addEventListener( "DOMContentLoaded", main );
