<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the REAL createToken() write path (not actingAs(), which fakes authentication
 * without touching the DB) — the gap that let personal_access_tokens.tokenable_id (uuid,
 * sized for Profile's UUID PK) ship broken for User (bigint PK) in PR #265. A maintainer's
 * first live admin:issue-api-token call failed in prod with "invalid input syntax for type
 * uuid" before the companion migration fixed the column type.
 */
class PersonalAccessTokenIssuanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_bigint_id_user_can_actually_have_a_token_created_and_stored(): void
    {
        $user = User::factory()->create(['is_maintainer' => true]);

        $token = $user->createToken('agent-api');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => (string) $user->id,
        ]);
        $this->assertNotEmpty($token->plainTextToken);
    }

    public function test_a_uuid_id_profile_can_still_have_a_token_created_and_stored(): void
    {
        $profile = Profile::factory()->business()->create();

        $token = $profile->createToken('mobile-app');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => Profile::class,
            'tokenable_id' => $profile->id,
        ]);
        $this->assertNotEmpty($token->plainTextToken);
    }

    public function test_a_freshly_issued_user_token_authenticates_a_real_request_end_to_end(): void
    {
        $user = User::factory()->create(['is_maintainer' => true]);
        $plainTextToken = $user->createToken('agent-api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$plainTextToken}")
            ->getJson('/api/v1/admin/crm');

        $response->assertOk();
    }

    public function test_a_freshly_issued_profile_token_still_authenticates_the_mobile_api(): void
    {
        $profile = Profile::factory()->business()->create();
        $plainTextToken = $profile->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$plainTextToken}")
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
    }
}
