<?php

declare(strict_types=1);

return [
    'new_message' => [
        'title' => 'Nuevo mensaje',
    ],

    'application' => [
        'received' => [
            'title' => 'Nueva solicitud',
            'body' => ':name se ha postulado a tu oportunidad ":kolab".',
        ],
        'accepted' => [
            'title' => 'Solicitud aceptada',
            'body' => '¡Tu solicitud para ":kolab" ha sido aceptada!',
        ],
        'declined' => [
            'title' => 'Solicitud rechazada',
            'body' => 'Tu solicitud para ":kolab" ha sido rechazada.',
        ],
        'withdrawn' => [
            'creator' => [
                'title' => 'Solicitud retirada',
                'body' => ':name ha retirado su solicitud para ":kolab".',
            ],
            'applicant' => [
                'title' => 'Solicitud retirada',
                'body' => 'Has retirado tu solicitud para ":kolab".',
            ],
        ],
    ],

    'challenge' => [
        'verified' => [
            'title' => '¡Reto verificado!',
            'body' => 'Tu reto ":challenge" ha sido verificado. ¡Has ganado :points puntos!',
        ],
    ],

    'collab' => [
        'day_reminder' => [
            'title' => '¡Hoy es tu Kolab! 🎉',
            'body' => 'Tu colaboración es hoy. ¡Que tengas un gran Kolab!',
        ],
        'follow_up_reminder' => [
            'title' => '¿Tuvo lugar tu Kolab?',
            'body' => 'Márcalo como completado para ganar tu XP y mantener tu historial al día.',
        ],
    ],

    'collaboration' => [
        'created' => [
            'title' => 'Colaboración iniciada',
            'body' => 'Tu colaboración para ":kolab" está lista. Toca para ver los detalles.',
        ],
        'activated' => [
            'title' => 'Colaboración activada',
            'actor_body' => 'Has marcado como activa la colaboración para ":kolab".',
            'counterpart_body' => ':name ha marcado como activa vuestra colaboración para ":kolab".',
        ],
        'feedback_received' => [
            'actor_title' => 'Valoración enviada',
            'actor_body' => 'Tu valoración para ":kolab" ha sido registrada.',
            'counterpart_title' => 'Nueva valoración',
            'counterpart_body' => ':name ha dejado una valoración de vuestra colaboración ":kolab".',
        ],
        'completed' => [
            'title' => 'Colaboración completada',
            'auto_body' => 'Tu colaboración para ":kolab" se ha marcado automáticamente como completada.',
            'actor_body' => 'Has marcado como completada la colaboración para ":kolab".',
            'counterpart_body' => ':name ha marcado como completada vuestra colaboración para ":kolab".',
        ],
        'cancelled' => [
            'title' => 'Colaboración cancelada',
            'actor_body' => 'Has cancelado la colaboración para ":kolab".',
            'counterpart_body' => ':name ha cancelado vuestra colaboración para ":kolab".',
        ],
    ],

    'reward' => [
        'won' => [
            'title' => '¡Has ganado una recompensa!',
            'body' => '¡Has ganado ":reward" en la ruleta!',
        ],
    ],

    'badge' => [
        'awarded' => [
            'title' => '¡Insignia conseguida!',
            'body' => '¡Has conseguido la insignia ":badge"!',
        ],
        'gamification_earned' => [
            'title' => '¡Insignia conseguida!',
            'body' => '¡Has conseguido la insignia ":badge"!',
        ],
    ],

    'waitlist' => [
        'promoted' => [
            'title' => '¡Estás dentro!',
            'body' => 'Se ha liberado una plaza para ":event": ya estás apuntado.',
        ],
    ],

    'community' => [
        'join_requested' => [
            'title' => 'Nueva solicitud para unirse',
            'body' => ':name ha solicitado unirse a :community.',
        ],
        'join_approved' => [
            'title' => 'Solicitud aprobada',
            'body' => 'Ya eres miembro de :community.',
        ],
        'join_declined' => [
            'title' => 'Solicitud rechazada',
            'body' => 'Tu solicitud para unirte a :community ha sido rechazada.',
        ],
        'verified' => [
            'title' => 'Te has verificado ✅',
            'body' => 'Tu comunidad ya está verificada. Las empresas verán tu insignia de verificación.',
        ],
        'verification_rejected' => [
            'title' => 'La verificación necesita cambios',
            'body' => 'Tu verificación de comunidad no fue aprobada: :reason',
        ],
    ],

    'account' => [
        'collaboration_cancelled' => [
            'title' => 'Colaboración cancelada',
            'body' => '":kolab" se canceló porque el otro participante eliminó su cuenta.',
        ],
    ],

    'multi_kolab' => [
        'application' => [
            'received' => [
                'title' => 'Nueva solicitud de rol',
                'body' => ':name solicitó el rol ":role" en ":event".',
            ],
            'accepted' => [
                'title' => 'Solicitud aceptada',
                'body' => '¡Tu solicitud para el rol ":role" en ":event" fue aceptada!',
            ],
            'declined' => [
                'title' => 'Solicitud rechazada',
                'body' => 'Tu solicitud para el rol ":role" en ":event" fue rechazada.',
            ],
            'withdrawn' => [
                'title' => 'Un socio se retiró',
                'body' => ':name se retiró del rol ":role" en ":event".',
            ],
        ],
        'role' => [
            'filled' => [
                'title' => 'Rol cubierto',
                'body' => 'El rol ":role" en ":event" ya está cubierto.',
            ],
        ],
        'event' => [
            'confirmed' => [
                'title' => 'Evento confirmado',
                'body' => '":event" ya está confirmado. ¡Nos vemos allí!',
            ],
            'cancelled' => [
                'title' => 'Evento cancelado',
                'body' => '":event" fue cancelado: :reason',
            ],
        ],
    ],
];
