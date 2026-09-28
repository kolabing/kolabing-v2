<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\KolabStatus;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * `kolabing:move-kolabs` (bug report 2026-09-28, item 3): LabTwentyTwo's only
 * Published kolab sits on a deleted profile while a new live LabTwentyTwo
 * profile exists. The command hands Draft/Published kolabs to the live profile.
 */
class MoveKolabsTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array{0: Profile, 1: Profile, 2: Kolab, 3: Kolab, 4: Kolab}
     */
    private function labTwentyTwo(): array
    {
        $old = Profile::factory()->business()->create(['email' => 'hotel@labtwentytwo.com']);
        $published = Kolab::factory()->forCreator($old)->published()->create(['title' => 'Labtwentytwo Barcelona']);
        $draft = Kolab::factory()->forCreator($old)->create(['title' => 'Draft idea']);
        $closed = Kolab::factory()->forCreator($old)->closed()->create(['title' => 'Old closed one']);
        $old->delete();

        $live = Profile::factory()->business()->create(['email' => 'hotel@labtwentytwo.com']);

        return [$old, $live, $published, $draft, $closed];
    }

    public function test_dry_run_lists_kolabs_of_a_deleted_owner_and_writes_nothing(): void
    {
        [$old, $live, $published, $draft, $closed] = $this->labTwentyTwo();

        $this->artisan('kolabing:move-kolabs', ['--from' => $old->id, '--to' => $live->id])
            ->expectsOutputToContain('Dry run')
            ->expectsOutputToContain('DELETED')
            ->expectsOutputToContain('Labtwentytwo Barcelona')
            ->expectsOutputToContain('stays (closed)')
            ->expectsOutputToContain('2 kolab(s) would be moved')
            ->assertSuccessful();

        $this->assertSame($old->id, $published->fresh()->creator_profile_id);
        $this->assertSame($old->id, $draft->fresh()->creator_profile_id);
    }

    public function test_apply_moves_draft_and_published_only(): void
    {
        [$old, $live, $published, $draft, $closed] = $this->labTwentyTwo();

        $this->artisan('kolabing:move-kolabs', ['--from' => $old->id, '--to' => $live->id, '--apply' => true])
            ->expectsOutputToContain('2 kolab(s) moved')
            ->assertSuccessful();

        $this->assertSame($live->id, $published->fresh()->creator_profile_id);
        $this->assertSame(KolabStatus::Published, $published->fresh()->status);
        $this->assertSame($live->id, $draft->fresh()->creator_profile_id);
        $this->assertSame($old->id, $closed->fresh()->creator_profile_id);

        // The moved kolab now reaches discovery through its live owner.
        $this->assertTrue(Kolab::query()->fromActiveOwner()->whereKey($published->id)->exists());
    }

    public function test_apply_is_idempotent(): void
    {
        [$old, $live, $published] = $this->labTwentyTwo();

        $this->artisan('kolabing:move-kolabs', ['--from' => $old->id, '--to' => $live->id, '--apply' => true])->assertSuccessful();

        $this->artisan('kolabing:move-kolabs', ['--from' => $old->id, '--to' => $live->id, '--apply' => true])
            ->expectsOutputToContain('No Draft or Published kolabs to move')
            ->assertSuccessful();

        $this->assertSame($live->id, $published->fresh()->creator_profile_id);
        $this->assertSame(2, Kolab::query()->where('creator_profile_id', $live->id)->count());
    }

    public function test_refuses_a_deleted_target(): void
    {
        [$old, $live, $published] = $this->labTwentyTwo();
        $live->delete();

        $this->artisan('kolabing:move-kolabs', ['--from' => $old->id, '--to' => $live->id, '--apply' => true])
            ->expectsOutputToContain('is deleted')
            ->assertFailed();

        $this->assertSame($old->id, $published->fresh()->creator_profile_id);
    }

    public function test_refuses_an_inactive_target(): void
    {
        [$old, $live, $published] = $this->labTwentyTwo();
        $live->forceFill(['is_active' => false])->save();

        $this->artisan('kolabing:move-kolabs', ['--from' => $old->id, '--to' => $live->id, '--apply' => true])
            ->expectsOutputToContain('inactive')
            ->assertFailed();

        $this->assertSame($old->id, $published->fresh()->creator_profile_id);
    }

    public function test_refuses_a_target_of_another_user_type(): void
    {
        [$old, , $published] = $this->labTwentyTwo();
        $community = Profile::factory()->community()->create();

        $this->artisan('kolabing:move-kolabs', ['--from' => $old->id, '--to' => $community->id, '--apply' => true])
            ->expectsOutputToContain('same type')
            ->assertFailed();

        $this->assertSame($old->id, $published->fresh()->creator_profile_id);
    }

    public function test_refuses_missing_or_identical_ids(): void
    {
        $profile = Profile::factory()->business()->create();

        $this->artisan('kolabing:move-kolabs', ['--from' => $profile->id])->assertFailed();
        $this->artisan('kolabing:move-kolabs', ['--from' => $profile->id, '--to' => $profile->id])->assertFailed();
        $this->artisan('kolabing:move-kolabs', ['--from' => $profile->id, '--to' => '00000000-0000-0000-0000-000000000000'])
            ->expectsOutputToContain('No profile with id')
            ->assertFailed();
    }
}
