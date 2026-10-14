<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Route groups
    |--------------------------------------------------------------------------
    |
    | `public` is every route name the server-rendered pages call `route()`
    | with (marketing page, legal pages and their shared components). The SSR
    | service has no window, so App\Support\PublicSsr hands it this list as a
    | page prop instead - kept small so it doesn't bloat the page.
    | PublicSsrTest fails if one of those pages uses a route missing here.
    |
    */

    'groups' => [
        'public' => ['marketing', 'login', 'register', 'dashboard', 'legal.*'],
    ],

];
