<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\KolabStatus;
use App\Models\BusinessProfile;
use App\Models\CommunityProfile;
use App\Models\Kolab;
use App\Models\Profile;
use App\Services\ApplicationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Guards the apply-time date-availability check (audit #4): a community must
 * not be able to apply to a date-exhausted / closed Kolab, mirroring the
 * accept-time window validation.
 */
class ApplyDateAvailabilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ApplicationService $service;

    private Profile $creator;

    private Profile $applicant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ApplicationService::class);

        $this->creator = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $this->creator->id]);

        // Communities are never paywalled when applying — simplest applicant.
        $this->applicant = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $this->applicant->id]);
    }

    private function kolab(array $overrides): Kolab
    {
        return Kolab::factory()->create(array_merge([
            'creator_profile_id' => $this->creator->id,
            'status' => KolabStatus::Published,
            'availability_mode' => 'flexible',
        ], $overrides));
    }

    /**
     * @return array{message: string, availability: string}
     */
    private function payload(): array
    {
        return [
            'message' => 'We would love to collaborate on this with our community.',
            'availability' => 'Available on weekday evenings and most weekends this month.',
        ];
    }

    public function test_apply_is_rejected_when_the_availability_window_is_in_the_past(): void
    {
        $kolab = $this->kolab([
            'availability_start' => now()->subMonths(2),
            'availability_end' => now()->subMonth(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Applications for this opportunity are closed');

        $this->service->apply($this->applicant, $kolab, $this->payload());
    }

    public function test_apply_is_rejected_when_no_recurring_day_remains_in_the_window(): void
    {
        // Window Tue..Sun but only Mondays allowed — no selectable date.
        $tuesday = now()->next(2); // ISO 2 = Tuesday
        $kolab = $this->kolab([
            'availability_mode' => 'recurring',
            'availability_start' => $tuesday,
            'availability_end' => $tuesday->copy()->addDays(4), // Sat
            'recurring_days' => [1], // Monday only
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Applications for this opportunity are closed');

        $this->service->apply($this->applicant, $kolab, $this->payload());
    }

    public function test_apply_succeeds_when_a_future_date_is_still_available(): void
    {
        $kolab = $this->kolab([
            'availability_start' => now()->addDays(3),
            'availability_end' => now()->addMonth(),
        ]);

        $application = $this->service->apply($this->applicant, $kolab, $this->payload());

        $this->assertNotNull($application->id);
        $this->assertDatabaseHas('applications', [
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $this->applicant->id,
        ]);
    }
}
