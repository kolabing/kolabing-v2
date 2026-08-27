<?php

declare(strict_types=1);

use App\Enums\MissionTrigger;

return [
    /*
    |--------------------------------------------------------------------------
    | Live mission triggers
    |--------------------------------------------------------------------------
    |
    | Single source of truth for which mission triggers actually fire today (the
    | ones wired to a source action). A mission whose trigger is not in this set
    | can never progress, so `GET /me/missions` hides it. Listed via the enum so
    | the values stay type-safe; as later phases wire more sources, add the
    | matching cases here. MissionTrigger::isLive() reads off this list.
    |
    */
    'live_triggers' => [
        MissionTrigger::CollaborationComplete->value,
        MissionTrigger::ReviewPosted->value,
        MissionTrigger::ReviewReceived->value,
        MissionTrigger::BusinessReviewReceived->value,
        MissionTrigger::BusinessReferred->value,
        MissionTrigger::ProfileCompleted->value,
        MissionTrigger::BusinessProfileCompleted->value,
        MissionTrigger::CommunityProfileCompleted->value,
        MissionTrigger::BusinessPhotoUploaded->value,
        MissionTrigger::CommunityPhotoUploaded->value,
        MissionTrigger::EventCheckin->value,
        MissionTrigger::MemberCheckin->value,
        MissionTrigger::KolabPublished->value,
        MissionTrigger::KolabCreatedProduct->value,
        MissionTrigger::RecurringKolabCreated->value,
        MissionTrigger::RecurringKolabHosted->value,
        MissionTrigger::ApplicationSubmitted->value,
        MissionTrigger::ApplicationReceived->value,
        MissionTrigger::ApplicationAccepted->value,
        MissionTrigger::SubscriptionRenewed->value,
        MissionTrigger::CommunityJoined->value,
        MissionTrigger::MembersInvited->value,
        MissionTrigger::ChallengeCompleted->value,
    ],

    /*
    |--------------------------------------------------------------------------
    | Local timezone for mission period buckets
    |--------------------------------------------------------------------------
    |
    | Timestamps are stored in UTC, but recurring missions (daily / weekly /
    | monthly / seasonal) must roll over at local midnight. This is the single
    | timezone the product operates in; MissionService::periodKeyFor() converts
    | UTC timestamps to it before deriving the period_key, so both the write
    | path (record()) and the read path (GET /me/missions) bucket identically.
    |
    */
    'local_timezone' => env('GAMIFICATION_LOCAL_TIMEZONE', 'Europe/Madrid'),

    /*
    |--------------------------------------------------------------------------
    | Pair ladder — what repeat meetings are worth
    |--------------------------------------------------------------------------
    |
    | An `encounters.times_met` counts the number of DISTINCT EVENTS two people
    | have both turned up to and completed a challenge at. This ladder turns
    | that count into a rung, and pays a ONE-TIME bonus the first time a pair
    | crosses one. EncounterService reads it; nothing else decides a threshold,
    | and the mobile app decides none of it.
    |
    | Two deliberate absences:
    |
    | - **No decay.** A rung once reached is kept. Levels that fall turn showing
    |   up at a run club into an obligation, which is the opposite of the point.
    | - **No streak.** Same reason. A streak you can break creates guilt, not
    |   warmth — fine for a language app, wrong for this.
    |
    | Ordered ascending by `at`. The first rung is the meeting itself and pays
    | nothing: you already earned the challenge's points for that.
    |
    */
    'pair_ladder' => [
        ['at' => 1, 'key' => 'met', 'bonus' => 0],
        ['at' => 3, 'key' => 'regulars', 'bonus' => 10],
        ['at' => 5, 'key' => 'crew', 'bonus' => 25],
        ['at' => 10, 'key' => 'inner_circle', 'bonus' => 50],
    ],
];
