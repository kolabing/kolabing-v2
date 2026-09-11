<?php

declare(strict_types=1);

return [

    'signal' => [
        'category_fit' => 'Afinidad de categoría',
        'location_fit' => 'Ubicación',
        'scale_fit' => 'Ajuste de tamaño',
        'offer_need_fit' => 'Ajuste de la oferta',
        'delivery_proof' => 'Entregas demostradas',
        'momentum' => 'Actividad reciente',
    ],

    'reason' => [
        'category_fit' => 'Las comunidades de :community_type y los negocios de :business_category colaboran a menudo.',
        'location_distance' => 'A unos :km km de distancia.',
        'location_same_city' => 'Misma ciudad.',
        'location_other_city' => 'Ciudad distinta.',
        'scale_fit' => 'Se esperan unas :expected personas; el espacio admite :capacity.',
        'offer_need_none' => 'Todavía no hay coincidencias entre lo que ofreces y lo que necesitan.',
        'offer_need_overlap' => 'Ya ofreces lo que piden: :items.',
        'delivery_proof_community' => 'Ha publicado :content contenidos en Kolabs anteriores, con una valoración de :rating.',
        'delivery_proof_business' => ':reviews valoraciones de colaboradores anteriores, con una nota de :rating.',
        'delivery_proof_content' => 'Ha publicado :content contenidos en Kolabs anteriores.',
        'delivery_proof_reviews' => ':reviews valoraciones de colaboradores anteriores.',
        'delivery_proof_rating' => 'Con una nota de :rating de colaboradores anteriores.',
        'delivery_proof_collaborations' => 'Ha completado :collaborations Kolabs con colaboradores anteriores.',
        'momentum' => ':count eventos en los últimos :days días.',
        'momentum_business' => ':count Kolabs y colaboraciones en los últimos :days días.',
        'no_history' => 'Aún no hay eventos anteriores: la coincidencia es por perfil.',
    ],

    /*
    |--------------------------------------------------------------------
    | Formato propuesto
    |--------------------------------------------------------------------
    |
    | Títulos para el evento que propone una sugerencia, según el tipo de
    | comunidad que ha resuelto FormatSuggester, con `generic` como respaldo
    | cuando no hay plantilla para ese tipo (o no hay tipo). Se guardan como
    | clave más parámetros y se traducen aquí en el momento de leerlos, para
    | que el idioma del proceso nocturno no llegue a la tarjeta de quien lee.
    | Son títulos de Kolab: breves y ciertos para ese tipo de comunidad, sin
    | afirmar nada sobre el colaborador ni sobre las cifras.
    |
    */
    'format' => [
        'title' => [
            'run_club' => 'Salida en grupo con parada después de correr',
            'food_community' => 'Sesión de degustación para una comunidad gastronómica',
            'fitness_community' => 'Sesión de entrenamiento en grupo',
            'wellness_community' => 'Sesión de bienestar para una comunidad local',
            'tech_startup_community' => 'Encuentro para una comunidad tecnológica',
            'professional_networking_community' => 'Tarde de networking para profesionales',
            'student_community' => 'Encuentro estudiantil con un negocio local',
            'generic' => 'Kolab con una comunidad local',
        ],
    ],

    /*
    |--------------------------------------------------------------------
    | Vocabulario
    |--------------------------------------------------------------------
    |
    | Etiquetas legibles para los slugs de tipo de comunidad y categoría de
    | negocio con los que trabaja CategoryFitMatrix, para que el motivo se
    | lea como una frase en cada idioma en lugar de mostrar un slug en
    | inglés. Lo que no esté aquí se muestra como el slug sin guiones bajos.
    |
    */
    'vocabulary' => [
        'community_type' => [
            'food_community' => 'gastronomía',
            'run_club' => 'running',
            'fitness_community' => 'fitness',
            'wellness_community' => 'bienestar',
            'tech_startup_community' => 'startups tecnológicas',
            'professional_networking_community' => 'networking profesional',
            'student_community' => 'estudiantes',
        ],
        'business_category' => [
            'cafe' => 'cafetería',
            'restaurant' => 'restaurante',
            'food_truck' => 'food truck',
            'bakery' => 'panadería',
            'bar' => 'bar',
            'bar_lounge' => 'bar y lounge',
            'beverage' => 'bebidas',
            'food_product' => 'producto alimentario',
            'coworking' => 'coworking',
            'sports_facility' => 'instalación deportiva',
            'gym' => 'gimnasio',
            'hotel' => 'hotel',
            'retail' => 'tienda',
            'health_beauty' => 'salud y belleza',
            'salon' => 'peluquería',
            'tech_gadget' => 'tecnología y gadgets',
        ],
    ],

];
