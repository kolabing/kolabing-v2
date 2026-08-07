<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Book-a-call URL
    |--------------------------------------------------------------------------
    |
    | External scheduling link used by the landing-page "Book a call" CTA
    | (Cal.com). Set KOLABING_BOOK_A_CALL_URL in the environment to the real
    | booking page once it exists; the default is a placeholder.
    |
    */

    'book_a_call_url' => env('KOLABING_BOOK_A_CALL_URL', 'https://cal.com/kolabing/intro'),

];
