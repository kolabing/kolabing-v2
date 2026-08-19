<?php

declare(strict_types=1);

return [

    'signal' => [
        'category_fit' => 'Afinitat de categoria',
        'location_fit' => 'Ubicació',
        'scale_fit' => 'Ajust de mida',
        'offer_need_fit' => 'Ajust de l\'oferta',
        'delivery_proof' => 'Lliuraments demostrats',
        'momentum' => 'Activitat recent',
    ],

    'reason' => [
        'category_fit' => 'Les comunitats de :community_type i els negocis de :business_category col·laboren sovint.',
        'location_distance' => 'A uns :km km de distància.',
        'location_same_city' => 'Mateixa ciutat.',
        'location_other_city' => 'Ciutat diferent.',
        'scale_fit' => 'S\'esperen unes :expected persones; l\'espai admet :capacity.',
        'offer_need_none' => 'Encara no hi ha coincidències entre el que ofereixes i el que necessiten.',
        'offer_need_overlap' => 'Ja ofereixes el que demanen: :items.',
        'delivery_proof_community' => 'Ha publicat :content continguts en Kolabs anteriors, amb una valoració de :rating.',
        'delivery_proof_business' => ':reviews valoracions de col·laboradors anteriors, amb una nota de :rating.',
        'delivery_proof_content' => 'Ha publicat :content continguts en Kolabs anteriors.',
        'delivery_proof_reviews' => ':reviews valoracions de col·laboradors anteriors.',
        'delivery_proof_rating' => 'Amb una nota de :rating de col·laboradors anteriors.',
        'momentum' => ':count esdeveniments en els últims :days dies.',
        'no_history' => 'Encara no hi ha esdeveniments anteriors: la coincidència és per perfil.',
    ],

    /*
    |--------------------------------------------------------------------
    | Vocabulari
    |--------------------------------------------------------------------
    |
    | Etiquetes llegibles per als slugs de tipus de comunitat i categoria de
    | negoci amb què treballa CategoryFitMatrix, perquè el motiu es llegeixi
    | com una frase en cada idioma en lloc de mostrar un slug en anglès. El
    | que no hi consti es mostra com el slug sense guions baixos.
    |
    */
    'vocabulary' => [
        'community_type' => [
            'food_community' => 'gastronomia',
            'run_club' => 'running',
            'fitness_community' => 'fitness',
            'wellness_community' => 'benestar',
            'tech_startup_community' => 'startups tecnològiques',
            'professional_networking_community' => 'networking professional',
            'student_community' => 'estudiants',
        ],
        'business_category' => [
            'cafe' => 'cafeteria',
            'restaurant' => 'restaurant',
            'food_truck' => 'food truck',
            'bakery' => 'fleca',
            'bar' => 'bar',
            'bar_lounge' => 'bar i lounge',
            'beverage' => 'begudes',
            'food_product' => 'producte alimentari',
            'coworking' => 'coworking',
            'sports_facility' => 'instal·lació esportiva',
            'gym' => 'gimnàs',
            'hotel' => 'hotel',
            'retail' => 'botiga',
            'health_beauty' => 'salut i bellesa',
            'salon' => 'perruqueria',
            'tech_gadget' => 'tecnologia i gadgets',
        ],
    ],

];
