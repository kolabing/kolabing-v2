<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminWelcomeEmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EmailTemplateManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_maintainer_can_add_a_new_language(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.email-templates.store'), [
                'locale' => 'fr',
                'label' => 'Français',
                'subject' => 'Votre profil Kolabing est en ligne',
                'intro_markdown' => 'Bienvenue {{name}} !',
                'next_steps_markdown' => 'Créez votre mot de passe.',
                'footer_markdown' => 'Merci, Kolabing',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.email-templates.index'));

        $this->assertDatabaseHas('admin_welcome_email_templates', [
            'locale' => 'fr',
            'label' => 'Français',
        ]);
    }

    public function test_locale_must_be_unique(): void
    {
        AdminWelcomeEmailTemplate::factory()->create(['locale' => 'en']);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.email-templates.store'), [
                'locale' => 'en',
                'label' => 'English again',
                'subject' => 'x',
                'intro_markdown' => 'x',
                'next_steps_markdown' => 'x',
                'footer_markdown' => 'x',
            ])
            ->assertSessionHasErrors('locale');

        $this->assertDatabaseCount('admin_welcome_email_templates', 1);
    }

    public function test_maintainer_can_edit_a_language(): void
    {
        $template = AdminWelcomeEmailTemplate::factory()->create(['locale' => 'es', 'subject' => 'Old subject']);

        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.email-templates.update', $template), [
                'locale' => 'es',
                'label' => $template->label,
                'subject' => 'New subject',
                'intro_markdown' => $template->intro_markdown,
                'next_steps_markdown' => $template->next_steps_markdown,
                'footer_markdown' => $template->footer_markdown,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.email-templates.index'));

        $this->assertSame('New subject', $template->fresh()->subject);
    }

    public function test_editing_can_keep_the_same_locale(): void
    {
        // Regression guard: Rule::unique()->ignore() must actually exempt the row being
        // updated, or every edit would fail with "locale already taken" against itself.
        $template = AdminWelcomeEmailTemplate::factory()->create(['locale' => 'ca']);

        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.email-templates.update', $template), [
                'locale' => 'ca',
                'label' => $template->label,
                'subject' => $template->subject,
                'intro_markdown' => $template->intro_markdown,
                'next_steps_markdown' => $template->next_steps_markdown,
                'footer_markdown' => $template->footer_markdown,
            ])
            ->assertSessionDoesntHaveErrors('locale');
    }

    public function test_maintainer_can_remove_a_language(): void
    {
        $template = AdminWelcomeEmailTemplate::factory()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->delete(route('admin.email-templates.destroy', $template))
            ->assertRedirect(route('admin.email-templates.index'));

        $this->assertDatabaseMissing('admin_welcome_email_templates', ['id' => $template->id]);
    }

    public function test_deactivating_a_language_removes_it_from_the_send_dropdown_but_not_the_database(): void
    {
        $active = AdminWelcomeEmailTemplate::factory()->create(['locale' => 'en', 'is_active' => true]);
        $inactive = AdminWelcomeEmailTemplate::factory()->create(['locale' => 'de', 'is_active' => false]);

        $profile = \App\Models\Profile::factory()->business()->create();

        $response = $this->actingAs($this->maintainer(), 'admin')->get(route('admin.users.edit', $profile));

        $response->assertSee($active->label);
        $response->assertDontSee($inactive->label);
    }

    public function test_non_maintainer_cannot_manage_templates(): void
    {
        $user = User::factory()->create(['is_maintainer' => false]);

        $this->actingAs($user, 'admin')
            ->get(route('admin.email-templates.index'))
            ->assertForbidden();
    }
}
