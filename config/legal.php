<?php

/*
|--------------------------------------------------------------------------
| Legal documents and consent
|--------------------------------------------------------------------------
|
| Who the Terms of Service, Privacy Policy and SMS Terms are *from*, plus
| the version of each document. Set the company details in .env before
| launch - until then the pages show bracketed placeholders so nothing is
| mistaken for final.
|
| Bump a version whenever the wording of that document (or the checkbox
| text in App\Support\Consent) materially changes. Every consent we record
| stores the version the person actually agreed to, which is what makes the
| records useful in an audit; old records are never rewritten.
|
*/

return [

    'company' => [
        'name' => env('LEGAL_COMPANY_NAME', '[Company legal name]'),
        'address' => env('LEGAL_COMPANY_ADDRESS', '[Company mailing address]'),
        'contact_email' => env('LEGAL_CONTACT_EMAIL', '[contact email]'),
        'privacy_email' => env('LEGAL_PRIVACY_EMAIL', env('LEGAL_CONTACT_EMAIL', '[privacy email]')),
        // State or country whose law governs the Terms, and where disputes are heard.
        'governing_law' => env('LEGAL_GOVERNING_LAW', '[State/Province, Country]'),
        'venue' => env('LEGAL_VENUE', '[County, State/Province]'),
    ],

    'sms' => [
        // The name shown in the opt-in language and the A2P 10DLC registration.
        'program_name' => env('LEGAL_SMS_PROGRAM_NAME', env('APP_NAME', 'Fab Shop').' Notifications'),
        'help_email' => env('LEGAL_SMS_HELP_EMAIL', env('LEGAL_CONTACT_EMAIL', '[support email]')),
        'help_phone' => env('LEGAL_SMS_HELP_PHONE', '[support phone number]'),
    ],

    // Effective date doubles as the version string recorded with each consent.
    'versions' => [
        'terms' => '2026-10-01',
        'privacy' => '2026-10-06',
        'sms' => '2026-10-01',
    ],

];
