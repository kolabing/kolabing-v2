<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Admin\CompanySettingService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CompanySettingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    /**
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'legal_name' => 'Kolabing SL',
            'registration_number' => 'B-12345678',
            'registered_address' => 'Carrer Exemple 1, Barcelona',
            'refund_policy' => 'Refunds within 14 days.',
            'privacy_email' => 'dpo@kolabing.com',
            'support_email' => 'help@kolabing.com',
            'terms_version' => '2026-07-15',
            'terms_effective_date' => '2026-07-15',
        ], $overrides);
    }

    public function test_guest_cannot_access_company_settings(): void
    {
        $this->get(route('admin.company-settings.edit'))->assertStatus(302);
    }

    public function test_maintainer_can_view_company_settings(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.company-settings.edit'))
            ->assertOk()
            ->assertSee('Registered company name', false);
    }

    public function test_maintainer_can_update_company_settings(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.company-settings.update'), $this->validPayload())
            ->assertRedirect(route('admin.company-settings.edit'));

        $this->assertDatabaseHas('company_settings', [
            'legal_name' => 'Kolabing SL',
            'privacy_email' => 'dpo@kolabing.com',
            'terms_version' => '2026-07-15',
        ]);
    }

    public function test_update_requires_legal_name_and_emails(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.company-settings.update'), $this->validPayload([
                'legal_name' => '',
                'privacy_email' => 'not-an-email',
            ]))
            ->assertSessionHasErrors(['legal_name', 'privacy_email']);
    }

    public function test_legal_page_shows_placeholder_by_default(): void
    {
        $this->get(route('terms'))
            ->assertOk()
            ->assertSee('[COMPANY NAME]', false);
    }

    public function test_legal_page_reflects_saved_company_details(): void
    {
        app(CompanySettingService::class)->update($this->validPayload([
            'legal_name' => 'Kolabing Sociedad Limitada',
        ]));

        $this->get(route('privacy'))
            ->assertOk()
            ->assertSee('Kolabing Sociedad Limitada', false)
            ->assertSee('dpo@kolabing.com', false)
            ->assertDontSee('[COMPANY NAME]', false);
    }

    public function test_bumping_version_reprompts_app_users(): void
    {
        $token = $this->postJson('/api/v1/auth/register/attendee', [
            'email' => 'consent@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accepted_terms' => true,
        ])->json('data.token');

        // Admin bumps the version from the settings page.
        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.company-settings.update'), $this->validPayload([
                'terms_version' => '2027-01-01',
                'terms_effective_date' => '2027-01-01',
            ]));

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.terms.current_version', '2027-01-01')
            ->assertJsonPath('data.terms.needs_acceptance', true);
    }
}
