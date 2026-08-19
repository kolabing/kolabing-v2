<?php

declare(strict_types=1);

return [

    'signal' => [
        'category_fit' => 'Category fit',
        'location_fit' => 'Location',
        'scale_fit' => 'Size fit',
        'offer_need_fit' => 'Offer fit',
        'delivery_proof' => 'Proven delivery',
        'momentum' => 'Momentum',
    ],

    'reason' => [
        'category_fit' => ':community_type communities and :business_category businesses collaborate often.',
        'location_distance' => 'About :km km apart.',
        'location_same_city' => 'Same city.',
        'location_other_city' => 'Different city.',
        'scale_fit' => 'Expect around :expected people; the space holds :capacity.',
        'offer_need_none' => 'No overlap between what you offer and what they need yet.',
        'offer_need_overlap' => 'You already offer what they ask for: :items.',
        'delivery_proof_community' => 'Delivered :content posts across past Kolabs, rated :rating.',
        'delivery_proof_business' => ':reviews reviews from past partners, rated :rating.',
        'momentum' => ':count events in the last :days days.',
        'no_history' => 'No past events yet — matched on profile.',
    ],

    /*
    |--------------------------------------------------------------------
    | Vocabulary
    |--------------------------------------------------------------------
    |
    | Human labels for the community-type and business-category slugs that
    | CategoryFitMatrix keys on, so the reason line reads as a sentence in
    | every locale instead of leaking an English slug. Anything not listed
    | here degrades to the slug with its underscores removed.
    |
    */
    'vocabulary' => [
        'community_type' => [
            'food_community' => 'Food',
            'run_club' => 'Run club',
            'fitness_community' => 'Fitness',
            'wellness_community' => 'Wellness',
            'tech_startup_community' => 'Tech startup',
            'professional_networking_community' => 'Professional networking',
            'student_community' => 'Student',
        ],
        'business_category' => [
            'cafe' => 'café',
            'restaurant' => 'restaurant',
            'food_truck' => 'food truck',
            'bakery' => 'bakery',
            'bar' => 'bar',
            'bar_lounge' => 'bar and lounge',
            'beverage' => 'beverage',
            'food_product' => 'food product',
            'coworking' => 'coworking',
            'sports_facility' => 'sports facility',
            'gym' => 'gym',
            'hotel' => 'hotel',
            'retail' => 'retail',
            'health_beauty' => 'health and beauty',
            'salon' => 'salon',
            'tech_gadget' => 'tech and gadget',
        ],
    ],

];
