<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Current legal agreement version
    |--------------------------------------------------------------------------
    |
    | The version of the Terms of Service + Privacy Policy currently in effect.
    | It is recorded against a profile when the user accepts the agreements at
    | sign-up (or via POST /api/v1/me/consent). Bump this whenever the published
    | terms change materially — the mobile app compares it against the accepted
    | version returned by /auth/me and re-prompts when they differ. Date-based so
    | it is human-readable and always increasing.
    |
    */

    'terms_version' => env('LEGAL_TERMS_VERSION', '2026-07-12'),

    /*
    |--------------------------------------------------------------------------
    | Contact
    |--------------------------------------------------------------------------
    |
    | Privacy / data-protection contact address surfaced in the policy pages.
    |
    */

    'contact_email' => env('LEGAL_CONTACT_EMAIL', 'privacy@kolabing.com'),

];
