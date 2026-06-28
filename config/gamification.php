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
];
