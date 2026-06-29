<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\KolabStatus;
use App\Enums\UserType;
use App\Models\Application;
use App\Models\BusinessSubscription;
use App\Models\Collaboration;
use App\Models\CollaborationReview;
use App\Models\Kolab;
use App\Models\Profile;
use App\Models\User;
use App\Services\Admin\KolabLifecycleService;
use App\Services\Admin\PlatformStatsService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StatsDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_route_is_protected_and_renders_for_maintainer(): void
    {
        $this->get(route('admin.stats.index'))->assertRedirect();

        $user = User::factory()->create(['is_maintainer' => false]);
        $this->actingAs($user, 'admin')->get(route('admin.stats.index'))->assertForbidden();

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.stats.index'))
            ->assertOk()
            ->assertSee('Statistics');
    }

    public function test_quality_avg_rating_uses_star_overall_not_rounded_legacy_rating(): void
    {
        $collaboration = Collaboration::factory()->completed()->create();

        // Mirrors what the controller stores for a 5-star review: the legacy
        // `rating` column holds the rounded average (5), while the precise
        // per-dimension scores average to 4.6.
        CollaborationReview::factory()->create([
            'collaboration_id' => $collaboration->id,
            'reviewer_role' => 'creator',
            'rating' => 5,
            'communication_rating' => 5,
            'reliability_rating' => 4,
            'fit_rating' => 4,
            'value_rating' => 5,
            'repeat_rating' => 5,
        ]);

        $quality = app(PlatformStatsService::class)->summary('all')['quality'];

        // (5 + 4 + 4 + 5 + 5) / 5 = 4.6, NOT the rounded legacy 5.
        $this->assertSame(4.6, $quality['avg_rating']);
        $this->assertSame(4.6, $quality['per_side']['creator']['avg']);
    }

    public function test_kolab_lifecycle_average_rating_uses_star_overall(): void
    {
        $collaboration = Collaboration::factory()->completed()->create();
        $kolab = Kolab::findOrFail($collaboration->kolab_id);

        CollaborationReview::factory()->create([
            'collaboration_id' => $collaboration->id,
            'reviewer_role' => 'creator',
            'rating' => 5,
            'communication_rating' => 5,
            'reliability_rating' => 4,
            'fit_rating' => 4,
            'value_rating' => 5,
            'repeat_rating' => 5,
        ]);

        $summary = app(KolabLifecycleService::class)->summarize($kolab);

        $this->assertSame(4.6, $summary['average_rating']);
    }

    public function test_summary_returns_audience_counts_split_by_user_type(): void
    {
        Profile::factory()->business()->count(3)->create();
        Profile::factory()->community()->count(5)->create();

        $summary = app(PlatformStatsService::class)->summary('all');

        $this->assertSame(8, $summary['audience']['total']);
        $this->assertSame(3, $summary['audience']['business']);
        $this->assertSame(5, $summary['audience']['community']);
    }

    public function test_applications_split_by_applicant_type_and_acceptance_rate(): void
    {
        $kolab = Kolab::factory()->published()->create();

        Application::factory()->accepted()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => Profile::factory()->community(),
            'applicant_profile_type' => UserType::Community,
        ]);
        Application::factory()->accepted()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => Profile::factory()->community(),
            'applicant_profile_type' => UserType::Community,
        ]);
        Application::factory()->declined()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => Profile::factory()->business(),
            'applicant_profile_type' => UserType::Business,
        ]);

        $summary = app(PlatformStatsService::class)->summary('all');

        $this->assertSame(3, $summary['applications']['total']);
        $this->assertSame(2, $summary['applications']['accepted']);
        $this->assertSame(1, $summary['applications']['declined']);
        $this->assertSame(2, $summary['applications']['from_community']);
        $this->assertSame(1, $summary['applications']['from_business']);
        $this->assertEqualsWithDelta(66.7, $summary['applications']['acceptance_rate_pct'], 0.1);
    }

    public function test_funnel_reports_per_user_type_step_counts(): void
    {
        $businesses = Profile::factory()->business()->count(4)->create();
        $publishing = $businesses->first();
        $kolab = Kolab::factory()->published()->create(['creator_profile_id' => $publishing->id]);

        $communities = Profile::factory()->community()->count(3)->create();
        $acceptedApp = Application::factory()->accepted()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $communities->get(0)->id,
            'applicant_profile_type' => UserType::Community,
        ]);
        Application::factory()->pending()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $communities->get(1)->id,
            'applicant_profile_type' => UserType::Community,
        ]);
        Collaboration::factory()->completed()->create([
            'kolab_id' => $kolab->id,
            'application_id' => $acceptedApp->id,
            'creator_profile_id' => $publishing->id,
            'applicant_profile_id' => $communities->get(0)->id,
        ]);

        $summary = app(PlatformStatsService::class)->summary('all');

        $this->assertSame(4, $summary['funnel']['business']['created']['n']);
        $this->assertSame(1, $summary['funnel']['business']['published_kolab']['n']);

        $this->assertSame(3, $summary['funnel']['community']['created']['n']);
        $this->assertSame(2, $summary['funnel']['community']['applied']['n']);
        $this->assertSame(1, $summary['funnel']['community']['accepted']['n']);
        $this->assertSame(1, $summary['funnel']['community']['collaborated']['n']);
        $this->assertSame(1, $summary['funnel']['community']['completed']['n']);
    }

    public function test_money_counts_active_subs_split_by_source(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessSubscription::query()->updateOrCreate(
            ['profile_id' => $business->id],
            ['source' => 'maintainer', 'status' => 'active'],
        );
        Profile::factory()->business()->count(3)->create();

        $summary = app(PlatformStatsService::class)->summary('all');

        $this->assertSame(1, $summary['money']['active_total']);
        $this->assertSame(1, $summary['money']['active_by_source']['maintainer']);
        $this->assertEqualsWithDelta(25.0, $summary['money']['paid_penetration_pct'], 0.1);
    }

    public function test_kolab_section_includes_lifecycle_distribution(): void
    {
        Kolab::factory()->create(['status' => KolabStatus::Draft]);
        Kolab::factory()->published()->count(2)->create();

        $summary = app(PlatformStatsService::class)->summary('all');

        $this->assertSame(3, $summary['kolabs']['total']);
        $this->assertSame(1, $summary['kolabs']['draft']);
        $this->assertSame(2, $summary['kolabs']['published']);
        $this->assertArrayHasKey('draft', $summary['kolabs']['lifecycle_distribution']);
        $this->assertArrayHasKey('open', $summary['kolabs']['lifecycle_distribution']);
    }

    public function test_range_parameter_falls_back_to_30d_on_invalid_input(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.stats.index', ['range' => 'nonsense']))
            ->assertOk()
            ->assertSee('Last 30d');
    }

    public function test_dau_wau_mau_count_active_profiles_using_last_active_at(): void
    {
        Profile::factory()->business()->create(['last_active_at' => now()->subHours(2)]);
        Profile::factory()->community()->create(['last_active_at' => now()->subDays(3)]);
        Profile::factory()->community()->create(['last_active_at' => now()->subDays(15)]);
        Profile::factory()->community()->create(['last_active_at' => null]);

        $summary = app(PlatformStatsService::class)->summary('all');

        $this->assertSame(1, $summary['activity']['dau']);
        $this->assertSame(2, $summary['activity']['wau']);
        $this->assertSame(3, $summary['activity']['mau']);
    }
}
