<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Models\CrmAccount;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CrmApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    private function nonMaintainer(): User
    {
        return User::factory()->create(['is_maintainer' => false]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/admin/crm')->assertUnauthorized();
    }

    public function test_a_mobile_app_profile_token_is_rejected_even_though_it_is_a_valid_sanctum_token(): void
    {
        $profile = Profile::factory()->business()->create();

        $this->actingAs($profile, 'sanctum')
            ->getJson('/api/v1/admin/crm')
            ->assertForbidden();
    }

    public function test_a_non_maintainer_user_token_is_rejected(): void
    {
        $this->actingAs($this->nonMaintainer(), 'sanctum')
            ->getJson('/api/v1/admin/crm')
            ->assertForbidden();
    }

    public function test_a_maintainer_token_can_read_crm_accounts(): void
    {
        CrmAccount::query()->create(['type' => 'business', 'name' => 'Cafe Uno', 'metrics' => ['source_city' => 'Barcelona']]);
        CrmAccount::query()->create(['type' => 'business', 'name' => 'Cafe Dos', 'metrics' => ['source_city' => 'Madrid']]);

        $response = $this->actingAs($this->maintainer(), 'sanctum')
            ->getJson('/api/v1/admin/crm?type=business');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_response_includes_contact_fields_but_never_notes_or_linked_profile_id(): void
    {
        CrmAccount::query()->create([
            'type' => 'business',
            'name' => 'Cafe Uno',
            'email' => 'owner@cafeuno.example',
            'phone' => '+34600000000',
            'instagram_handle' => '@cafeuno',
            'whatsapp' => '+34600000001',
            'notes' => 'Internal sales note: prefers WhatsApp, mentioned a competitor deal.',
            'metrics' => ['source_city' => 'Barcelona'],
        ]);

        $row = $this->actingAs($this->maintainer(), 'sanctum')
            ->getJson('/api/v1/admin/crm?type=business')
            ->json('data.0');

        // The point of this endpoint: contact fields must be present for the CRM export.
        $this->assertSame('owner@cafeuno.example', $row['email']);
        $this->assertSame('+34600000000', $row['phone']);
        $this->assertSame('@cafeuno', $row['instagram_handle']);
        $this->assertSame('+34600000001', $row['whatsapp']);
        $this->assertSame(['source_city' => 'Barcelona'], $row['metrics']);

        // Never leaked: free-text internal sales notes and the internal profile FK — neither
        // is exposed even to a logged-in maintainer in the Blade panel by default.
        $this->assertArrayNotHasKey('notes', $row);
        $this->assertArrayNotHasKey('linked_profile_id', $row);
    }

    public function test_defaults_to_business_type_when_type_is_omitted_or_invalid(): void
    {
        CrmAccount::query()->create(['type' => 'business', 'name' => 'Biz Co']);
        CrmAccount::query()->create(['type' => 'community', 'name' => 'Comm Co']);

        $response = $this->actingAs($this->maintainer(), 'sanctum')
            ->getJson('/api/v1/admin/crm?type=not-a-real-type');

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Biz Co'));
        $this->assertFalse($names->contains('Comm Co'));
    }

    public function test_city_filter_matches_the_type_specific_metrics_key(): void
    {
        CrmAccount::query()->create(['type' => 'business', 'name' => 'BCN Biz', 'metrics' => ['source_city' => 'Barcelona']]);
        CrmAccount::query()->create(['type' => 'business', 'name' => 'CDMX Biz', 'metrics' => ['source_city' => 'Mexico City']]);
        CrmAccount::query()->create(['type' => 'community', 'name' => 'BCN Comm', 'metrics' => ['city' => 'Barcelona']]);
        CrmAccount::query()->create(['type' => 'community', 'name' => 'CDMX Comm', 'metrics' => ['city' => 'Mexico City']]);

        $maintainer = $this->maintainer();

        $business = $this->actingAs($maintainer, 'sanctum')
            ->getJson('/api/v1/admin/crm?type=business&city=Mexico+City')
            ->json('data');
        $this->assertSame(['CDMX Biz'], collect($business)->pluck('name')->all());

        $community = $this->actingAs($maintainer, 'sanctum')
            ->getJson('/api/v1/admin/crm?type=community&city=Mexico+City')
            ->json('data');
        $this->assertSame(['CDMX Comm'], collect($community)->pluck('name')->all());
    }

    public function test_per_page_is_capped_at_200(): void
    {
        $response = $this->actingAs($this->maintainer(), 'sanctum')
            ->getJson('/api/v1/admin/crm?per_page=9999');

        $response->assertOk()->assertJsonPath('meta.per_page', 200);
    }

    public function test_the_web_admin_panel_and_the_api_agree_on_filtered_results(): void
    {
        CrmAccount::query()->create(['type' => 'community', 'name' => 'Target Co', 'status' => 'Target', 'metrics' => ['city' => 'Barcelona']]);
        CrmAccount::query()->create(['type' => 'community', 'name' => 'Onboarded Co', 'status' => 'Onboarded', 'metrics' => ['city' => 'Barcelona']]);

        $maintainer = $this->maintainer();

        $apiNames = collect(
            $this->actingAs($maintainer, 'sanctum')
                ->getJson('/api/v1/admin/crm?type=community&status=Target')
                ->json('data')
        )->pluck('name')->all();

        $webBody = $this->actingAs($maintainer, 'admin')
            ->get('/admin/crm?type=community&status=Target')
            ->getContent();

        $this->assertSame(['Target Co'], $apiNames);
        $this->assertStringContainsString('Target Co', $webBody);
        $this->assertStringNotContainsString('Onboarded Co', $webBody);
    }
}
