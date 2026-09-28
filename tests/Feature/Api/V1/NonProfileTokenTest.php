<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A Sanctum token can belong to a `User` (the maintainer read token minted by
 * `IssueMaintainerApiToken`) rather than an app `Profile`. The app API behind
 * `auth:sanctum` assumes a Profile everywhere, so those endpoints used to throw
 * and answer 500. They must answer a clean 403 instead.
 */
class NonProfileTokenTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function appEndpoints(): array
    {
        return [
            'me/profile' => ['/api/v1/me/profile'],
            'kolabs' => ['/api/v1/kolabs'],
            'opportunities' => ['/api/v1/opportunities'],
            'communities/discover' => ['/api/v1/communities/discover'],
            'discovery/opportunities' => ['/api/v1/discovery/opportunities'],
            'auth/me' => ['/api/v1/auth/me'],
        ];
    }

    #[DataProvider('appEndpoints')]
    public function test_a_user_token_without_an_app_profile_gets_403_not_500(string $path): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson($path)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'PROFILE_REQUIRED');
    }
}
