<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AdminWelcomeEmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminWelcomeEmailTemplate>
 */
class AdminWelcomeEmailTemplateFactory extends Factory
{
    protected $model = AdminWelcomeEmailTemplate::class;

    public function definition(): array
    {
        return [
            'locale' => fake()->unique()->languageCode(),
            // A single fake()->word() occasionally lands on a common short word (e.g. "id"),
            // which is a near-universal substring of any rendered HTML page (every id="..."
            // attribute matches it) -- flaked a CI run on exactly that. A multi-word phrase
            // is effectively never a substring of anything else on the page.
            'label' => fake()->unique()->words(3, true),
            'subject' => 'Your Kolabing listing is live — set your password',
            'intro_markdown' => "# Welcome, {{name}}!\n\nWe've listed you on Kolabing so {{who}} can already find you.\n\n## What you get\n\n- A public profile {{who}} can discover\n- Direct collaboration requests\n- Full control over your listing",
            'next_steps_markdown' => "## What happens next\n\nSet your password below to manage your profile.",
            'footer_markdown' => "This link is only valid for a limited time.\n\nThanks,\nKolabing",
            'is_active' => true,
        ];
    }
}
