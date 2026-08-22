<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ApplicationStatus;
use App\Enums\ChallengeDifficulty;
use App\Enums\ChatThreadType;
use App\Enums\CollaborationStatus;
use App\Enums\CommunityMemberStatus;
use App\Enums\EventSignupStatus;
use App\Enums\EventVisibility;
use App\Enums\IntentType;
use App\Enums\JoinPolicy;
use App\Enums\KolabStatus;
use App\Enums\TierAssignmentRule;
use App\Enums\UserType;
use App\Models\Application;
use App\Models\AttendeeProfile;
use App\Models\Challenge;
use App\Models\ChatThread;
use App\Models\Collaboration;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityProfile;
use App\Models\Event;
use App\Models\EventSignup;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds everything the event-challenge QR loop needs to be testable, in one
 * command (#136, for the loop built in #132).
 *
 *   php artisan kolabing:seed-qa-gamification
 *
 * The loop is: the organizer displays an event check-in QR → a member scans it
 * (`POST /checkin`) → that member scans another member's profile QR → the
 * event's challenges are listed → picking one calls `/challenges/initiate` with
 * the scanned member as verifier → the verifier scans the challenger's QR and
 * `/challenge-completions/{id}/verify` writes the XP.
 *
 * Testing that needs a specific pile of state to exist at once, which is what
 * this builds:
 *
 *   - a community owned by a leader account,
 *   - TWO attendee accounts (the loop is two-person: one shows a code, the
 *     other scans it — one account cannot test it),
 *   - both attendees as active members of that community,
 *   - an event today, under that community, that both attendees are `going` to
 *     (the app only offers "Check in" once you are going),
 *   - challenges attached to that event,
 *   - and an approved, active kolab so the community looks like a real one.
 *
 * The kolab is built through the real chain — Kolab (published) → Application
 * (accepted) → Collaboration — the way ApplicationService::accept forms one,
 * rather than forging a Collaboration row that no code path would ever produce.
 *
 * Idempotent: everything is keyed on the fixed QA emails and the [QA] event
 * name, so re-running updates in place. `--fresh` deletes the previous QA
 * event/kolab first, for a clean slate.
 *
 * SAFETY: the local .env points DB_DATABASE at `main`, which is PRODUCTION
 * (docs/BACKEND-SCHEMA.md). This command therefore prints its target and asks
 * before writing anything. Run it against the DEVELOPMENT environment.
 */
class SeedQaGamification extends Command
{
    protected $signature = 'kolabing:seed-qa-gamification
        {--force : Skip the target confirmation (for non-interactive runs)}
        {--fresh : Delete previously seeded QA event/kolab rows first}';

    protected $description = 'Seed a community, leader, two attendees, an active kolab and an event with challenges so the QR gamification loop can be tested end to end.';

    /**
     * The business is expected to already exist — it is a real account, not
     * something QA data should invent.
     */
    private const BUSINESS_EMAIL = 'hello@eixample46.com';

    private const LEADER_EMAIL = 'qa-leader@kolabing.test';

    private const ATTENDEE_A_EMAIL = 'qa-attendee-a@kolabing.test';

    private const ATTENDEE_B_EMAIL = 'qa-attendee-b@kolabing.test';

    /** Same password for every seeded account — this is throwaway QA data. */
    private const PASSWORD = 'Kolabing2026!';

    /** Marks every row this command creates, so it can be found and removed. */
    private const MARKER = '[QA]';

    private const COMMUNITY_NAME = self::MARKER.' Eixample Runners';

    private const EVENT_NAME = self::MARKER.' Gamification Test Run';

    public function handle(): int
    {
        if (! $this->confirmTarget()) {
            return self::FAILURE;
        }

        $business = Profile::query()->where('email', self::BUSINESS_EMAIL)->first();

        if ($business === null) {
            $this->error(self::BUSINESS_EMAIL.' not found on this database.');
            $this->line('This is a real account, not QA data — it has to exist first.');
            $this->line('Check you are pointed at the right environment.');

            return self::FAILURE;
        }

        if (! $business->isBusiness()) {
            $this->error(self::BUSINESS_EMAIL." is not a business account (user_type={$business->user_type->value}).");

            return self::FAILURE;
        }

        $business->loadMissing('businessProfile');

        if ($business->businessProfile === null) {
            $this->error(self::BUSINESS_EMAIL.' has no business_profiles row.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->cleanup();
        }

        $result = DB::transaction(function () use ($business): array {
            $leader = $this->ensureLeader();
            $attendeeA = $this->ensureAttendee(self::ATTENDEE_A_EMAIL, 'QA Attendee A', 'qa_attendee_a');
            $attendeeB = $this->ensureAttendee(self::ATTENDEE_B_EMAIL, 'QA Attendee B', 'qa_attendee_b');

            $community = $this->ensureCommunity($leader);
            $this->ensureMember($community, $leader, canManage: true);
            $this->ensureMember($community, $attendeeA);
            $this->ensureMember($community, $attendeeB);

            $collaboration = $this->ensureActiveKolab($leader, $business);
            $event = $this->ensureEvent($community, $leader);
            $challenges = $this->ensureChallenges($event);

            $this->ensureGoing($event, $attendeeA);
            $this->ensureGoing($event, $attendeeB);

            return [
                'leader' => $leader,
                'attendeeA' => $attendeeA,
                'attendeeB' => $attendeeB,
                'community' => $community,
                'collaboration' => $collaboration,
                'event' => $event,
                'challenges' => $challenges,
            ];
        });

        $this->report($result, $business);

        return self::SUCCESS;
    }

    /**
     * Refuses to write until the operator has seen which database this is.
     *
     * `main` is production. Seeding QA accounts and a [QA] event into it would
     * be visible to real users, so it takes a deliberate --force.
     */
    private function confirmTarget(): bool
    {
        $connection = config('database.default');
        $database = config("database.connections.$connection.database");
        $host = config("database.connections.$connection.host");
        $isProduction = $database === 'main' || app()->environment('production');

        $this->newLine();
        $this->line('  Target for this seed');
        $this->line('  APP_ENV  : '.app()->environment());
        $this->line('  host     : '.$host);
        $this->line('  database : '.$database);
        $this->newLine();

        if ($isProduction) {
            $this->warn('  This looks like PRODUCTION (database "main" — see docs/BACKEND-SCHEMA.md).');
            $this->warn('  QA accounts and a [QA] event would be visible to real users.');
            $this->newLine();

            if (! $this->option('force')) {
                $this->error('Refusing to seed production. Point at the development environment,');
                $this->error('or pass --force if you genuinely mean to do this.');

                return false;
            }

            $this->warn('  --force given: seeding production anyway.');

            return true;
        }

        if ($this->option('force')) {
            return true;
        }

        return $this->confirm('Seed QA gamification data into this database?', true);
    }

    /**
     * Removes the previous QA event and kolab so a re-run starts clean.
     *
     * Accounts, the community and its memberships are deliberately kept: their
     * ids appear in QA notes, and losing them would mean re-registering every
     * time. Only the per-run data goes.
     */
    private function cleanup(): void
    {
        // `event_checkins` and `challenge_completions` both cascadeOnDelete on
        // event_id (and completions also on challenge_id), so a QA round's
        // check-ins and completed challenges go with the event — no need to
        // clear them first, and no FK violation from this order.
        $events = Event::query()->where('name', self::EVENT_NAME)->pluck('id');

        if ($events->isNotEmpty()) {
            Challenge::query()->whereIn('event_id', $events)->delete();
            EventSignup::query()->whereIn('event_id', $events)->delete();
            Event::query()->whereIn('id', $events)->delete();
            $this->line('  cleaned '.$events->count().' previous QA event(s)');
        }

        $kolabs = Kolab::query()->where('title', 'like', self::MARKER.'%')->pluck('id');

        if ($kolabs->isNotEmpty()) {
            Collaboration::query()->whereIn('kolab_id', $kolabs)->delete();
            Application::query()->whereIn('kolab_id', $kolabs)->delete();
            Kolab::query()->whereIn('id', $kolabs)->delete();
            $this->line('  cleaned '.$kolabs->count().' previous QA kolab(s)');
        }
    }

    private function ensureLeader(): Profile
    {
        $leader = Profile::query()->firstOrCreate(
            ['email' => self::LEADER_EMAIL],
            [
                'password' => self::PASSWORD,
                'user_type' => UserType::Community,
                'name' => 'QA Community Leader',
                'handle' => 'qa_leader',
                'email_verified_at' => Carbon::now(),
            ]
        );

        CommunityProfile::query()->firstOrCreate(
            ['profile_id' => $leader->id],
            [
                'name' => 'QA Community Leader',
                'community_type' => 'running',
            ]
        );

        return $leader->fresh(['communityProfile']);
    }

    private function ensureAttendee(string $email, string $name, string $handle): Profile
    {
        $attendee = Profile::query()->firstOrCreate(
            ['email' => $email],
            [
                'password' => self::PASSWORD,
                'user_type' => UserType::Attendee,
                'name' => $name,
                'handle' => $handle,
                'email_verified_at' => Carbon::now(),
            ]
        );

        AttendeeProfile::query()->firstOrCreate(['profile_id' => $attendee->id]);

        return $attendee->fresh(['attendeeProfile']);
    }

    /**
     * Mirrors CommunityService::create — default tier and main chat thread
     * included, since the member surfaces read both.
     */
    private function ensureCommunity(Profile $leader): Community
    {
        $community = Community::query()->firstOrCreate(
            ['owner_profile_id' => $leader->id, 'name' => self::COMMUNITY_NAME],
            [
                'slug' => 'qa-eixample-runners-'.Str::lower(Str::random(6)),
                'type' => 'running',
                'description' => 'Seeded by kolabing:seed-qa-gamification for QA of the QR challenge loop. Safe to delete.',
                'is_primary' => true,
                'join_policy' => JoinPolicy::Open->value,
            ]
        );

        if ($community->tiers()->count() === 0) {
            $community->tiers()->create([
                'name' => 'Member',
                'rank' => 1,
                'assignment_rule' => TierAssignmentRule::Manual->value,
                'threshold' => null,
                'permissions' => ['view' => [], 'chat_channels' => [], 'perks' => [], 'capabilities' => []],
                'is_default' => true,
            ]);
        }

        ChatThread::query()->firstOrCreate(
            ['type' => ChatThreadType::CommunityMain->value, 'community_id' => $community->id],
            ['name' => $community->name]
        );

        return $community->fresh(['tiers']);
    }

    private function ensureMember(Community $community, Profile $profile, bool $canManage = false): CommunityMember
    {
        $defaultTier = $community->tiers()->where('is_default', true)->first();

        return CommunityMember::query()->updateOrCreate(
            ['community_id' => $community->id, 'profile_id' => $profile->id],
            [
                'tier_id' => $defaultTier?->id,
                'can_manage' => $canManage,
                'status' => CommunityMemberStatus::Active->value,
                'joined_at' => Carbon::now()->subDays(7),
                'tier_assigned_at' => Carbon::now()->subDays(7),
            ]
        );
    }

    /**
     * The full accepted chain, authored the way a real one is: the COMMUNITY
     * publishes the Kolab, the BUSINESS applies, the application is accepted,
     * and the Collaboration is created from it.
     *
     * Status is `active` rather than `scheduled` — the ask was an "active"
     * kolab, and active is what a collaboration in progress looks like.
     */
    private function ensureActiveKolab(Profile $leader, Profile $business): Collaboration
    {
        $existing = Collaboration::query()
            ->whereHas('kolab', fn ($q) => $q->where('title', 'like', self::MARKER.'%'))
            ->where('creator_profile_id', $leader->id)
            ->where('applicant_profile_id', $business->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $kolab = Kolab::query()->create([
            'creator_profile_id' => $leader->id,
            'intent_type' => IntentType::CommunitySeeking,
            'title' => self::MARKER.' Eixample Runners × Eixample 46',
            'description' => 'Seeded by kolabing:seed-qa-gamification so the community has a real, approved collaboration while the QR loop is tested. Safe to delete.',
            'status' => KolabStatus::Published,
            'preferred_city' => $leader->communityProfile?->city?->name ?? 'Barcelona',
            'availability_mode' => 'flexible',
            'published_at' => Carbon::now()->subDays(5),
        ]);

        $application = Application::query()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $business->id,
            'applicant_profile_type' => UserType::Business,
            'message' => self::MARKER.' Seeded accepted application.',
            'status' => ApplicationStatus::Accepted,
        ]);

        return Collaboration::query()->create([
            'application_id' => $application->id,
            'kolab_id' => $kolab->id,
            'creator_profile_id' => $leader->id,
            'applicant_profile_id' => $business->id,
            'business_profile_id' => $business->businessProfile->id,
            'community_profile_id' => $leader->communityProfile->id,
            'status' => CollaborationStatus::Active,
            'scheduled_date' => Carbon::today(),
        ]);
    }

    /**
     * Today's event, public and active.
     *
     * `checkin_token` is left null on purpose: the organizer minting one via
     * `POST /events/{id}/generate-qr` is step 1 of the flow under test, and
     * pre-filling it would skip the thing being tested.
     */
    private function ensureEvent(Community $community, Profile $leader): Event
    {
        return Event::query()->updateOrCreate(
            ['name' => self::EVENT_NAME, 'community_id' => $community->id],
            [
                'profile_id' => $leader->id,
                'partner_name' => $community->name,
                'partner_type' => UserType::Community->value,
                'event_date' => Carbon::today(),
                'starts_at' => Carbon::today()->setTime(18, 0),
                'ends_at' => Carbon::today()->setTime(22, 0),
                'address' => 'Carrer de Girona 46, Barcelona',
                'location' => 'Eixample 46',
                'capacity' => 50,
                'visibility' => EventVisibility::Public->value,
                'tier_gate' => null,
                'max_challenges_per_attendee' => 10,
                'is_active' => true,
                'checkin_token' => null,
                'checkin_token_expires_at' => null,
            ]
        );
    }

    /**
     * Three event-scoped challenges, one per difficulty.
     *
     * `event_id` is what makes them show up: ChallengeService::listForEvent()
     * returns system challenges with a null trigger_action OR anything whose
     * event_id matches. Setting event_id (and is_system = false) keeps this
     * event's list deterministic instead of depending on what the system
     * challenge seeder happens to have installed.
     *
     * @return array<int, Challenge>
     */
    private function ensureChallenges(Event $event): array
    {
        $definitions = [
            ['Take a selfie together', ChallengeDifficulty::Easy, 5],
            ['Introduce each other to someone new', ChallengeDifficulty::Medium, 15],
            ['Finish the route together', ChallengeDifficulty::Hard, 30],
        ];

        $challenges = [];

        foreach ($definitions as [$name, $difficulty, $points]) {
            $challenges[] = Challenge::query()->updateOrCreate(
                ['event_id' => $event->id, 'name' => self::MARKER.' '.$name],
                [
                    'description' => 'Seeded for QA of the QR challenge loop.',
                    'difficulty' => $difficulty->value,
                    'points' => $points,
                    'is_system' => false,
                    // Event challenges are peer-verified, never trigger-driven.
                    // A non-null trigger_action would turn this into a general
                    // mission and exclude it from the event surfaces entirely.
                    'trigger_action' => null,
                    'target_value' => 1,
                ]
            );
        }

        return $challenges;
    }

    private function ensureGoing(Event $event, Profile $attendee): void
    {
        EventSignup::query()->updateOrCreate(
            ['event_id' => $event->id, 'profile_id' => $attendee->id],
            [
                'status' => EventSignupStatus::Going->value,
                'waitlist_position' => null,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $r
     */
    private function report(array $r, Profile $business): void
    {
        $this->newLine();
        $this->info('QA gamification data ready.');
        $this->newLine();

        $this->line('  Accounts (password for all seeded accounts: '.self::PASSWORD.')');
        $this->table(
            ['role', 'email', 'profile_id'],
            [
                ['community leader', self::LEADER_EMAIL, $r['leader']->id],
                ['attendee A (challenger)', self::ATTENDEE_A_EMAIL, $r['attendeeA']->id],
                ['attendee B (verifier)', self::ATTENDEE_B_EMAIL, $r['attendeeB']->id],
                ['business (pre-existing)', self::BUSINESS_EMAIL, $business->id],
            ]
        );

        $this->line('  Data');
        $this->table(
            ['what', 'id', 'detail'],
            [
                ['community', $r['community']->id, $r['community']->name],
                ['collaboration', $r['collaboration']->id, 'status '.$r['collaboration']->status->value],
                ['event', $r['event']->id, $r['event']->name.' — today 18:00'],
                ['challenges', count($r['challenges']).' rows', collect($r['challenges'])->pluck('points')->implode(' / ').' points'],
            ]
        );

        $this->line('  Both attendees are `going` to the event and active members of the community.');
        $this->line('  The event has NO check-in token yet — the organizer minting one is step 1 of the test.');
        $this->newLine();
        $this->line('  Next: sign in as the leader, open the event, "Show check-in QR".');
        $this->line('  Then check in as both attendees and scan each other. See');
        $this->line('  kolabing-app/docs/qa/2026-08-22-event-challenge-qr-test-plan.md');
    }
}
