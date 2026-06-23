<?php

declare(strict_types=1);

return [
    'new_message' => [
        'title' => 'Nou missatge',
    ],

    'application' => [
        'received' => [
            'title' => 'Nova sol·licitud',
            'body' => ':name s\'ha postulat a la teva oportunitat ":kolab".',
        ],
        'accepted' => [
            'title' => 'Sol·licitud acceptada',
            'body' => 'La teva sol·licitud per a ":kolab" ha estat acceptada!',
        ],
        'declined' => [
            'title' => 'Sol·licitud rebutjada',
            'body' => 'La teva sol·licitud per a ":kolab" ha estat rebutjada.',
        ],
        'withdrawn' => [
            'creator' => [
                'title' => 'Sol·licitud retirada',
                'body' => ':name ha retirat la seva sol·licitud per a ":kolab".',
            ],
            'applicant' => [
                'title' => 'Sol·licitud retirada',
                'body' => 'Has retirat la teva sol·licitud per a ":kolab".',
            ],
        ],
    ],

    'challenge' => [
        'verified' => [
            'title' => 'Repte verificat!',
            'body' => 'El teu repte ":challenge" ha estat verificat. Has guanyat :points punts!',
        ],
    ],

    'collab' => [
        'day_reminder' => [
            'title' => 'Avui és el teu Kolab! 🎉',
            'body' => 'La teva col·laboració és avui. Que tinguis un gran Kolab!',
        ],
        'follow_up_reminder' => [
            'title' => 'Ha tingut lloc el teu Kolab?',
            'body' => 'Marca\'l com a completat per guanyar el teu XP i mantenir el teu historial al dia.',
        ],
    ],

    'collaboration' => [
        'created' => [
            'title' => 'Col·laboració iniciada',
            'body' => 'La teva col·laboració per a ":kolab" està a punt. Toca per veure\'n els detalls.',
        ],
        'activated' => [
            'title' => 'Col·laboració activada',
            'actor_body' => 'Has marcat com a activa la col·laboració per a ":kolab".',
            'counterpart_body' => ':name ha marcat com a activa la vostra col·laboració per a ":kolab".',
        ],
        'feedback_received' => [
            'actor_title' => 'Valoració enviada',
            'actor_body' => 'La teva valoració per a ":kolab" s\'ha registrat.',
            'counterpart_title' => 'Nova valoració',
            'counterpart_body' => ':name ha deixat una valoració de la vostra col·laboració ":kolab".',
        ],
        'completed' => [
            'title' => 'Col·laboració completada',
            'auto_body' => 'La teva col·laboració per a ":kolab" s\'ha marcat automàticament com a completada.',
            'actor_body' => 'Has marcat com a completada la col·laboració per a ":kolab".',
            'counterpart_body' => ':name ha marcat com a completada la vostra col·laboració per a ":kolab".',
        ],
        'cancelled' => [
            'title' => 'Col·laboració cancel·lada',
            'actor_body' => 'Has cancel·lat la col·laboració per a ":kolab".',
            'counterpart_body' => ':name ha cancel·lat la vostra col·laboració per a ":kolab".',
        ],
    ],

    'reward' => [
        'won' => [
            'title' => 'Has guanyat una recompensa!',
            'body' => 'Has guanyat ":reward" a la ruleta!',
        ],
    ],

    'badge' => [
        'awarded' => [
            'title' => 'Insígnia aconseguida!',
            'body' => 'Has aconseguit la insígnia ":badge"!',
        ],
        'gamification_earned' => [
            'title' => 'Insígnia aconseguida!',
            'body' => 'Has aconseguit la insígnia ":badge"!',
        ],
    ],

    'waitlist' => [
        'promoted' => [
            'title' => 'Ja hi ets!',
            'body' => 'S\'ha alliberat una plaça per a ":event": ara ja hi vas.',
        ],
    ],

    'community' => [
        'join_requested' => [
            'title' => 'Nova sol·licitud per unir-se',
            'body' => ':name ha sol·licitat unir-se a :community.',
        ],
        'join_approved' => [
            'title' => 'Sol·licitud aprovada',
            'body' => 'Ara ets membre de :community.',
        ],
        'join_declined' => [
            'title' => 'Sol·licitud rebutjada',
            'body' => 'La teva sol·licitud per unir-te a :community ha estat rebutjada.',
        ],
        'verified' => [
            'title' => 'T\'has verificat ✅',
            'body' => 'La teva comunitat ja està verificada. Les empreses veuran la teva insígnia de verificació.',
        ],
        'verification_rejected' => [
            'title' => 'La verificació necessita canvis',
            'body' => 'La verificació de la teva comunitat no s\'ha aprovat: :reason',
        ],
    ],

    'account' => [
        'collaboration_cancelled' => [
            'title' => 'Col·laboració cancel·lada',
            'body' => '":kolab" s\'ha cancel·lat perquè l\'altre participant ha eliminat el seu compte.',
        ],
    ],
];
