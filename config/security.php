<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy for app pages
    |--------------------------------------------------------------------------
    |
    | enforce     - the browser blocks anything the policy doesn't allow.
    | report-only - the browser only logs violations to its console. Use this
    |               to try a change to the policy (or a new third-party script
    |               or image host) before enforcing it.
    | off         - no header.
    |
    | Scripts are allowed only from this site and from inline tags carrying
    | the per-request nonce, so an injected <script> does not run.
    |
    */
    'csp' => env('CSP_MODE', 'enforce'),

    // Where the browser reaches the Reverb WebSocket server (the same values
    // the front end is built with), so the job board's live updates connect.
    'reverb' => [
        'host' => env('VITE_REVERB_HOST', env('REVERB_HOST')),
        'port' => env('VITE_REVERB_PORT', env('REVERB_PORT')),
        'scheme' => env('VITE_REVERB_SCHEME', env('REVERB_SCHEME', 'https')),
    ],

];
