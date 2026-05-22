<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ApplicationStatus;
use App\Enums\CollaborationStatus;
use App\Enums\OfferStatus;
use App\Enums\UserType;
use App\Models\Application;
use App\Models\CollabOpportunity;
use App\Models\Collaboration;
use App\Models\Profile;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One-off helper to seed a fully-formed, FINISHABLE collaboration so the
 * "finish / close collaboration" flow can be exercised end-to-end in the app.
 *
 *   php artisan kolabing:seed-test-collaboration
 *
 * Authorship mirrors how a real accepted collaboration is formed
 * (see App\Services\ApplicationService::accept + createCollaboration):
 *   - the COMMUNITY (realrunclub@gmail.com) creates + publishes the opportunity
 *   - the BUSINESS (hello@eixample46.com) applies and is accepted
 *   - a Collaboration row is created, status = scheduled, scheduled_date = yesterday
 *
 * "Scheduled" is finishable: the finish endpoint
 * (CollaborationService::finish) only blocks a CANCELLED collaboration; both
 * scheduled and active rows can be finished by either participant.
 *
 * Each run creates a FRESH opportunity + application + collaboration (it does
 * not reuse or dedupe), so it is safe to run repeatedly. The whole chain is
 * wrapped in a DB transaction.
 */
class SeedTestCollaboration extends Command
{
    protected $signature = 'kolabing:seed-test-collaboration';

    protected $description = 'Seed a finishable test collaboration between hello@eixample46.com (business) and realrunclub@gmail.com (community) so the finish/close flow can be tested.';

    private const BUSINESS_EMAIL = 'hello@eixample46.com';

    private const COMMUNITY_EMAIL = 'realrunclub@gmail.com';

    public function handle(): int
    {
        $business = Profile::query()
            ->where('email', self::BUSINESS_EMAIL)
            ->first();

        if ($business === null) {
            $this->error('Business profile not found for '.self::BUSINESS_EMAIL.'. Aborting.');

            return self::FAILURE;
        }

        $community = Profile::query()
            ->where('email', self::COMMUNITY_EMAIL)
            ->first();

        if ($community === null) {
            $this->error('Community profile not found for '.self::COMMUNITY_EMAIL.'. Aborting.');

            return self::FAILURE;
        }

        if (! $business->isBusiness()) {
            $this->error(self::BUSINESS_EMAIL." is not a business account (user_type={$business->user_type->value}). Aborting.");

            return self::FAILURE;
        }

        if (! $community->isCommunity()) {
            $this->error(self::COMMUNITY_EMAIL." is not a community account (user_type={$community->user_type->value}). Aborting.");

            return self::FAILURE;
        }

        $business->loadMissing('businessProfile');
        $community->loadMissing('communityProfile');

        if ($business->businessProfile === null) {
            $this->error(self::BUSINESS_EMAIL.' has no business_profiles row. Aborting.');

            return self::FAILURE;
        }

        if ($community->communityProfile === null) {
            $this->error(self::COMMUNITY_EMAIL.' has no community_profiles row. Aborting.');

            return self::FAILURE;
        }

        $yesterday = Carbon::yesterday();

        $collaboration = DB::transaction(function () use ($business, $community, $yesterday): Collaboration {
            // 1) The COMMUNITY creates + publishes the opportunity (the "creator").
            $opportunity = CollabOpportunity::create([
                'creator_profile_id' => $community->id,
                'creator_profile_type' => UserType::Community,
                'title' => '[TEST] Finish-flow collaboration',
                'description' => 'Seeded by kolabing:seed-test-collaboration to exercise the finish/close collaboration flow. Safe to delete.',
                'status' => OfferStatus::Published,
                'published_at' => Carbon::now(),
            ]);

            // 2) The BUSINESS applies and is accepted (the "applicant").
            $application = Application::create([
                'collab_opportunity_id' => $opportunity->id,
                'applicant_profile_id' => $business->id,
                'applicant_profile_type' => UserType::Business,
                'message' => '[TEST] Seeded accepted application.',
                'status' => ApplicationStatus::Accepted,
            ]);

            // 3) The Collaboration, wired exactly like ApplicationService::createCollaboration.
            //    creator = community (created the opportunity), applicant = business.
            return Collaboration::create([
                'application_id' => $application->id,
                'collab_opportunity_id' => $opportunity->id,
                'creator_profile_id' => $community->id,
                'applicant_profile_id' => $business->id,
                'business_profile_id' => $business->businessProfile->id,
                'community_profile_id' => $community->communityProfile->id,
                'status' => CollaborationStatus::Scheduled,
                'scheduled_date' => $yesterday,
            ]);
        });

        $this->info('Test collaboration created (fresh row created on every run; no dedupe).');
        $this->line('  collaboration_id : '.$collaboration->id);
        $this->line('  status           : '.$collaboration->status->value.' (finishable)');
        $this->line('  scheduled_date   : '.$collaboration->scheduled_date?->toDateString().' (yesterday)');
        $this->line('  creator (community / opportunity author): '.self::COMMUNITY_EMAIL.' [profile '.$community->id.']');
        $this->line('  applicant (business / accepted)         : '.self::BUSINESS_EMAIL.' [profile '.$business->id.']');
        $this->line('Sign in as either participant and run the finish/close flow against this collaboration.');

        return self::SUCCESS;
    }
}
