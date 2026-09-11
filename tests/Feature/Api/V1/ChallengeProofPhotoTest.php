<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ChallengeCompletionStatus;
use App\Enums\ChallengeProofType;
use App\Models\AttendeeProfile;
use App\Models\Challenge;
use App\Models\ChallengeCompletion;
use App\Models\Event;
use App\Models\Profile;
use App\Services\FileUploadService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The photo two people took while completing a challenge (kolabing-v2#216).
 *
 * Before this there was nowhere to put one: `challenge_completions` recorded
 * that a challenge happened and what it paid, and nothing of the thing itself.
 *
 * The rule being tested is that the photo belongs to the **pair**, not to
 * whoever pressed the button — so either of them can attach, replace and remove
 * it, and nobody else can touch it.
 */
class ChallengeProofPhotoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function attendee(): Profile
    {
        $profile = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    /**
     * @return array{completion: ChallengeCompletion, challenger: Profile, verifier: Profile}
     */
    private function completion(ChallengeCompletionStatus $status = ChallengeCompletionStatus::Pending): array
    {
        $host = Profile::factory()->business()->create();
        $event = Event::factory()->forProfile($host)->create(['is_active' => true]);
        $challenge = Challenge::factory()->system()->easy()->create();

        $challenger = $this->attendee();
        $verifier = $this->attendee();

        $completion = ChallengeCompletion::query()->create([
            'challenge_id' => $challenge->id,
            'event_id' => $event->id,
            'challenger_profile_id' => $challenger->id,
            'verifier_profile_id' => $verifier->id,
            'status' => $status->value,
            'points_earned' => 0,
        ]);

        return compact('completion', 'challenger', 'verifier');
    }

    private function photo(string $name = 'selfie.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 600, 600);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(app(FileUploadService::class)->getStorageDisk());
    }

    public function test_the_challenger_can_attach_a_photo(): void
    {
        ['completion' => $completion, 'challenger' => $challenger] = $this->completion();

        $response = $this->actingAs($challenger)
            ->post("/api/v1/challenge-completions/{$completion->id}/photo", [
                'photo' => $this->photo(),
            ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertNotNull($response->json('data.proof_photo_url'));
        $this->assertNotNull($completion->fresh()->proof_photo_url);
    }

    /**
     * The point of the rule: two people are in that picture, and which of them
     * pressed the button is not a permission model.
     */
    public function test_the_verifier_can_attach_it_too(): void
    {
        ['completion' => $completion, 'verifier' => $verifier] = $this->completion();

        $this->actingAs($verifier)
            ->post("/api/v1/challenge-completions/{$completion->id}/photo", ['photo' => $this->photo()])
            ->assertStatus(200);

        $this->assertNotNull($completion->fresh()->proof_photo_url);
    }

    public function test_a_stranger_cannot(): void
    {
        ['completion' => $completion] = $this->completion();
        $stranger = $this->attendee();

        $this->actingAs($stranger)
            ->post("/api/v1/challenge-completions/{$completion->id}/photo", ['photo' => $this->photo()])
            ->assertStatus(403)
            ->assertJsonPath('error', 'not_a_participant');

        $this->assertNull($completion->fresh()->proof_photo_url);
    }

    public function test_replacing_a_photo_deletes_the_old_file(): void
    {
        ['completion' => $completion, 'challenger' => $challenger] = $this->completion();
        $disk = Storage::disk(app(FileUploadService::class)->getStorageDisk());

        $this->actingAs($challenger)
            ->post("/api/v1/challenge-completions/{$completion->id}/photo", ['photo' => $this->photo('first.jpg')])
            ->assertStatus(200);
        $first = $completion->fresh()->proof_photo_url;

        $this->actingAs($challenger)
            ->post("/api/v1/challenge-completions/{$completion->id}/photo", ['photo' => $this->photo('second.jpg')])
            ->assertStatus(200);
        $second = $completion->fresh()->proof_photo_url;

        $this->assertNotSame($first, $second);
        // One completion, one photo — and the disk should say the same, rather
        // than quietly keeping every version anyone ever tried.
        $this->assertCount(1, $disk->allFiles("challenge-proofs/{$completion->id}"));
    }

    public function test_either_participant_can_remove_it(): void
    {
        ['completion' => $completion, 'challenger' => $challenger, 'verifier' => $verifier] = $this->completion();
        $disk = Storage::disk(app(FileUploadService::class)->getStorageDisk());

        $this->actingAs($challenger)
            ->post("/api/v1/challenge-completions/{$completion->id}/photo", ['photo' => $this->photo()])
            ->assertStatus(200);
        $this->assertCount(1, $disk->allFiles("challenge-proofs/{$completion->id}"));

        // The one who did NOT upload it takes it down.
        $this->actingAs($verifier)
            ->deleteJson("/api/v1/challenge-completions/{$completion->id}/photo")
            ->assertStatus(200)
            ->assertJsonPath('data.proof_photo_url', null);

        $this->assertNull($completion->fresh()->proof_photo_url);
        $this->assertCount(0, $disk->allFiles("challenge-proofs/{$completion->id}"));
    }

    /**
     * A photo may be attached before the confirmation (the camera opens when the
     * pair agrees) and after it (people remember to keep it later).
     */
    public function test_a_verified_completion_still_accepts_a_photo(): void
    {
        ['completion' => $completion, 'challenger' => $challenger] =
            $this->completion(ChallengeCompletionStatus::Verified);

        $this->actingAs($challenger)
            ->post("/api/v1/challenge-completions/{$completion->id}/photo", ['photo' => $this->photo()])
            ->assertStatus(200);
    }

    /**
     * Rejected means it did not happen, so there is nothing to illustrate.
     */
    public function test_a_rejected_completion_does_not(): void
    {
        ['completion' => $completion, 'challenger' => $challenger] =
            $this->completion(ChallengeCompletionStatus::Rejected);

        $this->actingAs($challenger)
            ->post("/api/v1/challenge-completions/{$completion->id}/photo", ['photo' => $this->photo()])
            ->assertStatus(409)
            ->assertJsonPath('error', 'photo_not_allowed');
    }

    public function test_a_non_image_is_refused(): void
    {
        ['completion' => $completion, 'challenger' => $challenger] = $this->completion();

        $this->actingAs($challenger)
            ->post("/api/v1/challenge-completions/{$completion->id}/photo", [
                'photo' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_an_oversize_photo_is_refused(): void
    {
        ['completion' => $completion, 'challenger' => $challenger] = $this->completion();

        $this->actingAs($challenger)
            ->post("/api/v1/challenge-completions/{$completion->id}/photo", [
                // 6 MB, over the 5 MB the upload type allows.
                'photo' => UploadedFile::fake()->image('huge.jpg')->size(6 * 1024),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_it_requires_authentication(): void
    {
        ['completion' => $completion] = $this->completion();

        $this->postJson("/api/v1/challenge-completions/{$completion->id}/photo")->assertStatus(401);
    }

    /*
    |--------------------------------------------------------------------------
    | proof_type — the engine selector
    |--------------------------------------------------------------------------
    */

    /**
     * Every challenge that predates the column has always been text-played, and
     * must keep reporting itself that way.
     */
    public function test_proof_type_defaults_to_text(): void
    {
        $challenge = Challenge::factory()->system()->easy()->create();

        $this->assertSame(ChallengeProofType::Text, $challenge->fresh()->proof_type);
    }

    public function test_an_organizer_can_mark_a_challenge_photo_played(): void
    {
        $host = Profile::factory()->community()->create();
        $event = Event::factory()->forProfile($host)->create(['is_active' => true]);

        $response = $this->actingAs($host)
            ->postJson("/api/v1/events/{$event->id}/challenges", [
                'name' => 'Take a selfie together',
                'description' => 'Get both of you in frame.',
                'difficulty' => 'easy',
                'points' => 5,
                'proof_type' => 'photo',
            ]);

        $response->assertSuccessful()->assertJsonPath('data.proof_type', 'photo');
    }

    public function test_an_unknown_proof_type_is_refused(): void
    {
        $host = Profile::factory()->community()->create();
        $event = Event::factory()->forProfile($host)->create(['is_active' => true]);

        $this->actingAs($host)
            ->postJson("/api/v1/events/{$event->id}/challenges", [
                'name' => 'Something',
                'difficulty' => 'easy',
                'points' => 5,
                'proof_type' => 'video',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['proof_type']);
    }
}
