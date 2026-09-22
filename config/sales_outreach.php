<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Sales outreach (BE-NF-65)
    |--------------------------------------------------------------------------
    |
    | The admin sales-mailing surface pitches a business on hosting one specific
    | community, and leads with what that night is plausibly worth to them.
    |
    | The revenue figure is an ESTIMATE and the email says so. Kolabing never sees
    | a venue's till, so there is no real spend data to derive it from — which is
    | exactly why the assumptions live here, are shown in the email alongside the
    | total, and are editable per pitch on the form. A number a salesperson cannot
    | explain when the business asks "where did you get that?" is worse than no
    | number at all.
    |
    */

    'revenue' => [
        /*
         * Share of a community's stated membership that actually turns up to one
         * event. Deliberately conservative: a pitch that overstates turnout is
         * discovered on the night, and that is the one meeting where being wrong
         * costs the relationship.
         */
        'attendance_rate' => (float) env('SALES_ATTENDANCE_RATE', 0.15),

        /*
         * Average spend per attendee, in cents, in the venue's currency. A café and
         * a cocktail bar are nowhere near each other, so this is a starting point
         * the salesperson is expected to override per pitch, not a fact.
         */
        'avg_spend_cents' => (int) env('SALES_AVG_SPEND_CENTS', 1800),

        /*
         * Floor and ceiling for the turnout prefill, so a community that reports
         * 40,000 members does not produce a pitch promising 6,000 people through
         * the door of a 40-seat café.
         */
        'min_attendees' => (int) env('SALES_MIN_ATTENDEES', 8),
        'max_attendees' => (int) env('SALES_MAX_ATTENDEES', 250),

        /*
         * Display currency for the estimate. One setting, not a per-country map:
         * the beachhead is the euro zone, and inventing an exchange rate for the
         * non-euro cities would put a made-up conversion inside a number the
         * business is invited to check.
         */
        'currency' => env('SALES_CURRENCY', 'EUR'),
    ],

    /*
     * How many Kolab alternatives to generate per pitch. Three is enough to give a
     * maintainer a real choice without turning the review screen into a reading task.
     */
    'idea_count' => (int) env('SALES_IDEA_COUNT', 3),

    /*
    |--------------------------------------------------------------------------
    | Prospect research (BE-NF-67)
    |--------------------------------------------------------------------------
    |
    | What the generator is allowed to look up about a business before pitching it.
    | Each source is independently switchable, because each has a different failure
    | mode: the stored Google data cannot fail (it is already in our database), a
    | website fetch reaches an address a stranger supplied, and the weather call
    | leaves our network for a third party.
    |
    | Instagram is deliberately not here. The official Graph API needs the business
    | to authorise us and scraping breaks Meta's terms, so there is no version of it
    | that keeps working.
    |
    */

    'intel' => [
        // Fetch the business's own website and read it. Guarded against private and
        // reserved addresses — see ProspectIntel::isFetchable().
        'website' => (bool) env('SALES_INTEL_WEBSITE', true),
        'website_timeout' => (int) env('SALES_INTEL_WEBSITE_TIMEOUT', 8),

        // Open-Meteo forecast at the venue's coordinates. No key, no account.
        'weather' => (bool) env('SALES_INTEL_WEATHER', true),
        'weather_timeout' => (int) env('SALES_INTEL_WEATHER_TIMEOUT', 8),
    ],

    /*
     * Languages the pitch can be written in. English and Spanish only for now —
     * the beachhead cities are Spanish-speaking and the rest of the admin email
     * surface (admin_welcome_email_templates) already proved that adding a locale
     * is a data change, not a code one.
     */
    'locales' => ['en', 'es'],

];
