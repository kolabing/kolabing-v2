<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | City aliases
    |--------------------------------------------------------------------------
    |
    | `kolabs.preferred_city` and `business_profiles.city_name` hold a free-text
    | city NAME (there is no `city_id` on a kolab), and that name is written from
    | whatever Google Places returns as the venue's `locality`. Google answers
    | with the local spelling ("Ciudad de México") and, in metro areas, with the
    | borough rather than the city ("Cuajimalpa de Morelos"), while the picker and
    | `GET /api/v1/cities` offer the canonical `cities.name` ("Mexico City").
    | Filtering compared the two byte-for-byte, so real listings were invisible to
    | the city filter (BE-FX-60).
    |
    | Each entry maps a canonical `cities.name` to every other spelling that means
    | the same city. Accents and case do not need a separate entry — the resolver
    | normalizes both — but the accented form is listed where it is what actually
    | sits in the database, because the read-side filter matches stored strings
    | literally (case-insensitively).
    |
    */

    'aliases' => [

        'Mexico City' => [
            'Ciudad de México',
            'Ciudad de Mexico',
            'CDMX',
            'México D.F.',
            'Mexico D.F.',
            'Distrito Federal',
            // The 16 alcaldías: Google returns these as the `locality` for an
            // address inside Mexico City. They ARE Mexico City.
            'Álvaro Obregón',
            'Azcapotzalco',
            'Benito Juárez',
            'Coyoacán',
            'Cuajimalpa de Morelos',
            'Cuajimalps', // Google's own spelling on some CDMX addresses.
            'Cuauhtémoc',
            'Gustavo A. Madero',
            'Iztacalco',
            'Iztapalapa',
            'La Magdalena Contreras',
            'Miguel Hidalgo',
            'Milpa Alta',
            'Tláhuac',
            'Tlalpan',
            'Venustiano Carranza',
            'Xochimilco',
        ],

        'Barcelona' => [
            'Barcelone',
        ],

        'Sevilla' => [
            'Seville',
            'Séville',
        ],

        'Valencia' => [
            'València',
        ],

        'Bilbao' => [
            'Bilbo',
        ],

        'Warsaw' => [
            'Warszawa',
            'Varsovia',
            'Warschau',
        ],

        'Berlin' => [
            'Berlín',
        ],

        'Paris' => [
            'París',
        ],

        'Tallinn' => [
            'Reval',
        ],

    ],

];
