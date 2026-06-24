<?php

declare(strict_types=1);

return [
    'new_message' => [
        'title' => 'New Message',
    ],

    'application' => [
        'received' => [
            'title' => 'New Application',
            'body' => ':name applied to your ":kolab" opportunity.',
        ],
        'accepted' => [
            'title' => 'Application Accepted',
            'body' => 'Your application for ":kolab" has been accepted!',
        ],
        'declined' => [
            'title' => 'Application Declined',
            'body' => 'Your application for ":kolab" was declined.',
        ],
        'withdrawn' => [
            'creator' => [
                'title' => 'Application Withdrawn',
                'body' => ':name withdrew their application for ":kolab".',
            ],
            'applicant' => [
                'title' => 'Application Withdrawn',
                'body' => 'You withdrew your application for ":kolab".',
            ],
        ],
    ],

    'challenge' => [
        'verified' => [
            'title' => 'Challenge Verified!',
            'body' => 'Your ":challenge" challenge was verified. You earned :points points!',
        ],
    ],

    'collab' => [
        'day_reminder' => [
            'title' => "Today's your Kolab! 🎉",
            'body' => 'Your collaboration is happening today. Have a great Kolab!',
        ],
        'follow_up_reminder' => [
            'title' => 'Did your Kolab happen?',
            'body' => 'Mark it complete to earn your XP and keep your history up to date.',
        ],
    ],

    'collaboration' => [
        'created' => [
            'title' => 'Collaboration started',
            'body' => 'Your collaboration for ":kolab" is set up. Tap to view the details.',
        ],
        'activated' => [
            'title' => 'Collaboration activated',
            'actor_body' => 'You marked the collaboration for ":kolab" as active.',
            'counterpart_body' => ':name marked your collaboration for ":kolab" as active.',
        ],
        'feedback_received' => [
            'actor_title' => 'Feedback submitted',
            'actor_body' => 'Your feedback for ":kolab" has been recorded.',
            'counterpart_title' => 'New feedback',
            'counterpart_body' => ':name left feedback for your collaboration ":kolab".',
        ],
        'completed' => [
            'title' => 'Collaboration completed',
            'auto_body' => 'Your collaboration for ":kolab" was automatically marked complete.',
            'actor_body' => 'You marked the collaboration for ":kolab" as complete.',
            'counterpart_body' => ':name marked your collaboration for ":kolab" as complete.',
        ],
        'cancelled' => [
            'title' => 'Collaboration cancelled',
            'actor_body' => 'You cancelled the collaboration for ":kolab".',
            'counterpart_body' => ':name cancelled your collaboration for ":kolab".',
        ],
    ],

    'reward' => [
        'won' => [
            'title' => 'You Won a Reward!',
            'body' => 'You won ":reward" from spin-the-wheel!',
        ],
    ],

    'badge' => [
        'awarded' => [
            'title' => 'Badge Earned!',
            'body' => 'You earned the ":badge" badge!',
        ],
        'gamification_earned' => [
            'title' => 'Badge Earned!',
            'body' => 'You earned the ":badge" badge!',
        ],
    ],

    'waitlist' => [
        'promoted' => [
            'title' => "You're in!",
            'body' => 'A spot opened up for ":event" — you\'re now going.',
        ],
    ],

    'community' => [
        'join_requested' => [
            'title' => 'New join request',
            'body' => ':name asked to join :community.',
        ],
        'join_approved' => [
            'title' => 'Request approved',
            'body' => 'You are now a member of :community.',
        ],
        'join_declined' => [
            'title' => 'Request declined',
            'body' => 'Your request to join :community was declined.',
        ],
        'verified' => [
            'title' => 'You got verified ✅',
            'body' => 'Your community is now verified. Businesses will see your verified badge.',
        ],
        'verification_rejected' => [
            'title' => 'Verification needs changes',
            'body' => 'Your community verification was not approved: :reason',
        ],
    ],

    'account' => [
        'collaboration_cancelled' => [
            'title' => 'Collaboration Cancelled',
            'body' => '":kolab" was cancelled because the other participant deleted their account.',
        ],
    ],
];
