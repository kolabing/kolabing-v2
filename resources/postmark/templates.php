<?php

declare(strict_types=1);

/**
 * Transactional email template definitions (Phase 2).
 *
 * This is the BOOTSTRAP seed for Postmark templates. Postmark remains the
 * editable source of truth once templates exist — `email:sync-templates`
 * creates missing aliases by default and only overwrites with --force.
 *
 * Content is expressed as structured blocks so HTML + text render consistently
 * from one definition (see App\Console\Commands\SyncPostmarkTemplates). Merge
 * variables use Postmark mustache syntax: {{var}}.
 *
 * Block types:
 *   ['type' => 'para', 'text' => '...']
 *   ['type' => 'head', 'text' => '...']            // bold subheading
 *   ['type' => 'list', 'ordered' => true|false, 'items' => ['...', '...']]
 *   ['type' => 'button', 'label' => '...', 'url' => '...']
 *   ['type' => 'signature']                         // Maria, Co-founder
 *   ['type' => 'ps', 'text' => '...']
 *
 * 'sample' supplies merge values for --test sends.
 *
 * business-welcome-01 / community-welcome-01 are defined here so a fresh
 * environment is self-sufficient (the onboarding drip's T+0 welcome depends on
 * them). Sync without --force only creates missing aliases, so this never
 * clobbers copy already edited in the Postmark dashboard.
 */
$app = 'https://kolabing.com';

return [

    // ───────────────────────── Account & security (always send) ─────────────────────────

    [
        'alias' => 'attendee-welcome-01',
        'name' => 'Welcome (attendee)',
        'subject' => 'Your first points are one event away',
        'sample' => ['first_name' => 'Daniel'],
        'content' => [
            ['type' => 'para', 'text' => 'Hi {{first_name}},'],
            ['type' => 'para', 'text' => "Welcome to Kolabing. You're here for the events, the challenges, and the rewards you earn just for showing up."],
            ['type' => 'head', 'text' => 'How it works (2 min):'],
            ['type' => 'list', 'ordered' => true, 'items' => [
                "Open the app and join a community you're into. Run clubs, art collectives, wellness groups, food crews. New ones are added every week in Barcelona.",
                'Find an event or challenge, show up, and snap your proof.',
                'Earn points. Every challenge and event earns points that unlock badges and move you up the community leaderboard.',
            ]],
            ['type' => 'head', 'text' => 'This week:'],
            ['type' => 'para', 'text' => "Join one community and complete one challenge. That's it. The first one is the hardest; after that it's a habit."],
            ['type' => 'signature'],
            ['type' => 'ps', 'text' => "Not sure which community fits you? Reply with what you're into (running, art, food, wellness...) and I'll point you to the most active ones tonight."],
        ],
    ],

    [
        'alias' => 'business-welcome-01',
        'name' => 'Welcome (business)',
        'subject' => 'Welcome to Kolabing',
        'sample' => ['first_name' => "Joe's Cafe"],
        'content' => [
            ['type' => 'para', 'text' => 'Hi {{first_name}},'],
            ['type' => 'para', 'text' => "Welcome to Kolabing. You're here to partner with local communities, run clubs, art collectives, wellness groups, food crews, and get your venue in front of the people who'll fill it."],
            ['type' => 'head', 'text' => 'How it works:'],
            ['type' => 'list', 'ordered' => true, 'items' => [
                'Finish your profile so communities can find you.',
                "Post a Kolab: what you're offering (a venue, a discount, a private space) and what you'd like back.",
                'Review the applications and pick the communities that fit.',
            ]],
            ['type' => 'para', 'text' => 'You can post your first Kolab in about 3 minutes.'],
            ['type' => 'button', 'label' => 'Open Kolabing', 'url' => $app],
            ['type' => 'signature'],
        ],
    ],

    [
        'alias' => 'community-welcome-01',
        'name' => 'Welcome (community)',
        'subject' => 'Welcome to Kolabing',
        'sample' => ['first_name' => 'Barcelona Run Club'],
        'content' => [
            ['type' => 'para', 'text' => 'Hi {{first_name}},'],
            ['type' => 'para', 'text' => "Welcome to Kolabing. You're here to find local businesses to partner with, venues, discounts, private spaces, and perks for your members."],
            ['type' => 'head', 'text' => 'How it works:'],
            ['type' => 'list', 'ordered' => true, 'items' => [
                'Finish your community profile so businesses can find you.',
                'Browse the open Kolabs and apply to the ones that fit your members.',
                'Team up, run the event, and build your reputation for the next one.',
            ]],
            ['type' => 'para', 'text' => 'You can apply to your first Kolab in about 2 minutes.'],
            ['type' => 'button', 'label' => 'Browse Kolabs', 'url' => $app],
            ['type' => 'signature'],
        ],
    ],

    [
        'alias' => 'password-reset',
        'name' => 'Password reset',
        'subject' => 'Reset your Kolabing password',
        'sample' => ['first_name' => 'Daniel', 'reset_url' => $app.'/reset-password?token=sample', 'expires_minutes' => '60'],
        'content' => [
            ['type' => 'para', 'text' => 'Hi {{first_name}},'],
            ['type' => 'para', 'text' => 'We got a request to reset your Kolabing password. Tap the button below to choose a new one. This link expires in {{expires_minutes}} minutes.'],
            ['type' => 'button', 'label' => 'Reset password', 'url' => '{{reset_url}}'],
            ['type' => 'para', 'text' => "If you didn't request this, you can ignore this email, your password won't change."],
        ],
    ],

    [
        'alias' => 'password-changed',
        'name' => 'Password changed',
        'subject' => 'Your Kolabing password was changed',
        'sample' => ['first_name' => 'Daniel', 'support_email' => 'info@kolabing.com'],
        'content' => [
            ['type' => 'para', 'text' => 'Hi {{first_name}},'],
            ['type' => 'para', 'text' => "This is a confirmation that your Kolabing password was just changed. For security, you've been signed out on all devices."],
            ['type' => 'para', 'text' => "If this was you, you're all set. If it wasn't, reset your password right away and email us at {{support_email}}."],
        ],
    ],

    // ───────────────────────── Onboarding / activation nudges ─────────────────────────

    [
        'alias' => 'complete-profile-business',
        'name' => 'Complete profile (business)',
        'subject' => 'Your profile is almost ready',
        'sample' => ['first_name' => "Joe's Cafe"],
        'content' => [
            ['type' => 'para', 'text' => 'Hi {{first_name}},'],
            ['type' => 'para', 'text' => "You signed up but your business profile isn't finished yet, and communities can't find you until it is."],
            ['type' => 'head', 'text' => 'Two minutes to go live:'],
            ['type' => 'list', 'ordered' => true, 'items' => [
                'Add 3 photos of your venue.',
                'Pick your category and write one specific line about your place.',
                'Add one thing you can offer a community.',
            ]],
            ['type' => 'para', 'text' => "Done. You'll start showing up in front of communities looking for partners."],
            ['type' => 'signature'],
        ],
    ],

    [
        'alias' => 'complete-profile-community',
        'name' => 'Complete profile (community)',
        'subject' => 'Your community profile is almost ready',
        'sample' => ['first_name' => 'Barcelona Run Club'],
        'content' => [
            ['type' => 'para', 'text' => 'Hi {{first_name}},'],
            ['type' => 'para', 'text' => "You signed up but your community profile isn't finished, and businesses can't find you until it is."],
            ['type' => 'head', 'text' => 'Two minutes to go live:'],
            ['type' => 'list', 'ordered' => true, 'items' => [
                'Add your logo and a couple of photos.',
                'Pick your community type and write one line about who you are.',
                'Add your city and socials.',
            ]],
            ['type' => 'para', 'text' => "Done. You'll start showing up to businesses looking to collaborate."],
            ['type' => 'signature'],
        ],
    ],

    [
        'alias' => 'activation-business',
        'name' => 'Activation nudge (business)',
        'subject' => 'Post your first Kolab',
        'sample' => ['first_name' => "Joe's Cafe"],
        'content' => [
            ['type' => 'para', 'text' => 'Hi {{first_name}},'],
            ['type' => 'para', 'text' => "Your profile's ready. Now post your first Kolab so communities can apply."],
            ['type' => 'para', 'text' => "A Kolab is just what you're offering and what you'd like back. A discount night for a shoutout. A private space for an event. It takes 3 minutes."],
            ['type' => 'button', 'label' => 'Post a Kolab', 'url' => $app],
            ['type' => 'signature'],
        ],
    ],

    [
        'alias' => 'activation-community',
        'name' => 'Activation nudge (community)',
        'subject' => 'Apply to your first Kolab',
        'sample' => ['first_name' => 'Barcelona Run Club'],
        'content' => [
            ['type' => 'para', 'text' => 'Hi {{first_name}},'],
            ['type' => 'para', 'text' => "Your profile's ready. Now browse what businesses are offering and apply to your first Kolab."],
            ['type' => 'para', 'text' => "Venues, discounts, private spaces, sponsored slots, there's a lot up for grabs for your members. It takes 2 minutes to apply."],
            ['type' => 'button', 'label' => 'Browse Kolabs', 'url' => $app],
            ['type' => 'signature'],
        ],
    ],

    [
        'alias' => 'attendee-activation-01',
        'name' => 'Activation nudge (attendee)',
        'subject' => 'Your first challenge is waiting',
        'sample' => ['first_name' => 'Daniel'],
        'content' => [
            ['type' => 'para', 'text' => 'Hi {{first_name}},'],
            ['type' => 'para', 'text' => "You're in, but you haven't joined a community yet. That's where the events, challenges, and points are."],
            ['type' => 'para', 'text' => "Pick one community, complete one challenge. The first one is the hardest; after that it's a habit."],
            ['type' => 'button', 'label' => 'Find a community', 'url' => $app],
            ['type' => 'signature'],
        ],
    ],

    // ───────────────────────── Collaboration lifecycle ─────────────────────────

    [
        'alias' => 'application-received',
        'name' => 'Application received',
        'subject' => 'New application for {{opportunity_title}}',
        'sample' => ['first_name' => "Joe's Cafe", 'applicant_name' => 'Barcelona Run Club', 'opportunity_title' => 'Saturday morning coffee partner'],
        'content' => [
            ['type' => 'para', 'text' => 'Hi {{first_name}},'],
            ['type' => 'para', 'text' => '{{applicant_name}} just applied to your Kolab "{{opportunity_title}}".'],
            ['type' => 'para', 'text' => "Take a look and accept if it's a fit. The sooner you reply, the more likely it happens."],
            ['type' => 'button', 'label' => 'Review application', 'url' => $app],
        ],
    ],

    [
        'alias' => 'application-accepted',
        'name' => 'Application accepted',
        'subject' => "You're in. {{opportunity_title}} is a match",
        'sample' => ['first_name' => 'Barcelona Run Club', 'partner_name' => "Joe's Cafe", 'opportunity_title' => 'Saturday morning coffee partner'],
        'content' => [
            ['type' => 'para', 'text' => 'Hi {{first_name}},'],
            ['type' => 'para', 'text' => 'Good news, {{partner_name}} accepted your application for "{{opportunity_title}}".'],
            ['type' => 'para', 'text' => 'Open the app to agree on a date and sort the details in chat.'],
            ['type' => 'button', 'label' => 'Open the collaboration', 'url' => $app],
        ],
    ],

    [
        'alias' => 'application-declined',
        'name' => 'Application declined',
        'subject' => 'About your application for {{opportunity_title}}',
        'sample' => ['first_name' => 'Barcelona Run Club', 'partner_name' => "Joe's Cafe", 'opportunity_title' => 'Saturday morning coffee partner'],
        'content' => [
            ['type' => 'para', 'text' => 'Hi {{first_name}},'],
            ['type' => 'para', 'text' => "{{partner_name}} won't be moving forward with your application for \"{{opportunity_title}}\" this time."],
            ['type' => 'para', 'text' => 'It happens, fit matters. There are more Kolabs posted every week, and the next one might be a better match.'],
            ['type' => 'button', 'label' => 'Browse Kolabs', 'url' => $app],
        ],
    ],

    [
        'alias' => 'first-message',
        'name' => 'First message',
        'subject' => '{{sender_name}} sent you a message',
        'sample' => ['first_name' => "Joe's Cafe", 'sender_name' => 'Barcelona Run Club'],
        'content' => [
            ['type' => 'para', 'text' => 'Hi {{first_name}},'],
            ['type' => 'para', 'text' => '{{sender_name}} started a conversation with you on Kolabing. Open the app to reply and keep things moving.'],
            ['type' => 'button', 'label' => 'Open chat', 'url' => $app],
        ],
    ],

    [
        'alias' => 'collab-confirmed',
        'name' => 'Collaboration confirmed',
        'subject' => 'Your collaboration with {{partner_name}} is confirmed',
        'sample' => ['first_name' => "Joe's Cafe", 'partner_name' => 'Barcelona Run Club', 'scheduled_date' => 'Saturday, 14 June'],
        'content' => [
            ['type' => 'para', 'text' => 'Hi {{first_name}},'],
            ['type' => 'para', 'text' => "It's official, your collaboration with {{partner_name}} is confirmed for {{scheduled_date}}."],
            ['type' => 'head', 'text' => 'Before the day:'],
            ['type' => 'list', 'ordered' => false, 'items' => [
                'Agree on the final details in chat.',
                'Make sure everyone knows where and when.',
            ]],
            ['type' => 'para', 'text' => "We'll remind you the day it happens."],
            ['type' => 'button', 'label' => 'View collaboration', 'url' => $app],
        ],
    ],

    [
        'alias' => 'feedback-request',
        'name' => 'Feedback request',
        'subject' => 'How did it go with {{partner_name}}?',
        'sample' => ['first_name' => "Joe's Cafe", 'partner_name' => 'Barcelona Run Club'],
        'content' => [
            ['type' => 'para', 'text' => 'Hi {{first_name}},'],
            ['type' => 'para', 'text' => 'Your collaboration with {{partner_name}} should be done by now. Tell us how it went, it takes a minute and helps both sides build trust for the next one.'],
            ['type' => 'button', 'label' => 'Give feedback', 'url' => $app],
            ['type' => 'para', 'text' => 'Your feedback (including any revenue it drove) stays private and helps us match you better next time.'],
        ],
    ],

    // ───────────────────────── Gamification ─────────────────────────

    [
        'alias' => 'badge-earned',
        'name' => 'Badge earned',
        'subject' => 'You earned the {{badge_name}} badge',
        'sample' => ['first_name' => 'Daniel', 'badge_name' => 'Early Bird'],
        'content' => [
            ['type' => 'para', 'text' => 'Hi {{first_name}},'],
            ['type' => 'para', 'text' => 'Nice work, you just earned the {{badge_name}} badge on Kolabing.'],
            ['type' => 'para', 'text' => 'Keep showing up to climb the leaderboard and unlock the next one.'],
            ['type' => 'button', 'label' => 'See your badges', 'url' => $app],
        ],
    ],

    [
        'alias' => 'reward-won',
        'name' => 'Reward won (community leader)',
        'subject' => 'You won {{reward_name}}',
        'sample' => ['first_name' => 'Barcelona Run Club', 'reward_name' => 'a 50 EUR bonus'],
        'content' => [
            ['type' => 'para', 'text' => 'Hi {{first_name}},'],
            ['type' => 'para', 'text' => 'Congratulations, you just won {{reward_name}}.'],
            ['type' => 'para', 'text' => 'Open the app to see the details and claim it.'],
            ['type' => 'button', 'label' => 'Claim your reward', 'url' => $app],
        ],
    ],

    [
        'alias' => 'withdrawal-processed',
        'name' => 'Withdrawal processed (community leader)',
        'subject' => 'Your cash-out of {{amount_eur}} is on its way',
        'sample' => ['first_name' => 'Barcelona Run Club', 'amount_eur' => '€75.00'],
        'content' => [
            ['type' => 'para', 'text' => 'Hi {{first_name}},'],
            ['type' => 'para', 'text' => 'Your withdrawal of {{amount_eur}} has been processed and is on its way to your account.'],
            ['type' => 'para', 'text' => 'Thanks for being an active part of Kolabing.'],
        ],
    ],

    [
        'alias' => 'tier-promotion',
        'name' => 'Tier promotion',
        'subject' => "You've reached {{tier_name}}",
        'sample' => ['first_name' => 'Barcelona Run Club', 'tier_name' => 'Gold'],
        'content' => [
            ['type' => 'para', 'text' => 'Hi {{first_name}},'],
            ['type' => 'para', 'text' => "You've been promoted to {{tier_name}}. Your activity on Kolabing is paying off."],
            ['type' => 'para', 'text' => 'Higher tiers mean more visibility and more perks. Keep it up.'],
            ['type' => 'button', 'label' => 'See your status', 'url' => $app],
        ],
    ],

    // ───────────────────────── Attendee gamification ─────────────────────────

    [
        'alias' => 'attendee-challenge-verified',
        'name' => 'Challenge verified (attendee)',
        'subject' => 'Challenge complete, +{{points}} points',
        'sample' => ['first_name' => 'Daniel', 'challenge_name' => '5K Morning Run', 'points' => '50'],
        'content' => [
            ['type' => 'para', 'text' => 'Hi {{first_name}},'],
            ['type' => 'para', 'text' => 'Your "{{challenge_name}}" challenge was verified. You just earned {{points}} points.'],
            ['type' => 'para', 'text' => 'Keep going to unlock badges and climb your community leaderboard.'],
            ['type' => 'button', 'label' => 'See your points', 'url' => $app],
        ],
    ],

    // ───────────────────────── Onboarding drip (new, 2026-07-21) ─────────────────────────
    // Copy drafted by Jace (Serra CMO), vault deliverable
    // _deliverables/to-review/jace/2026-07-20 - kolabing-launch-emails-and-onboarding.md.
    // Content verbatim from that draft; not yet brand-voice-audited/approved by Daniel.

    [
        'alias' => 'inactive-nudge',
        'name' => 'Inactive nudge',
        'subject' => 'Still worth a look, new collabs near you',
        'sample' => ['first_name' => 'Daniel'],
        'content' => [
            ['type' => 'para', 'text' => 'Hi {{first_name}},'],
            ['type' => 'para', 'text' => "You joined Kolabing but haven't started a collab yet. No pressure."],
            ['type' => 'para', 'text' => 'There are new opportunities posted since you signed up that match what you\'re after. It takes two minutes to apply to the first one.'],
            ['type' => 'button', 'label' => 'See what\'s open', 'url' => $app],
            ['type' => 'signature'],
        ],
    ],

];
