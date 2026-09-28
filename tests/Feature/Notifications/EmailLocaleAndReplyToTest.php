<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Jobs\SendTransactionalEmail;
use App\Models\BusinessProfile;
use App\Models\OnboardingDripState;
use App\Models\Profile;
use App\Services\EmailService;
use App\Services\OnboardingDripService;
use App\Services\PostmarkClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Bug report 2026-09-28, items 5 and 6: emails went out in English whatever the
 * recipient's preferred_locale, and the welcome drip asks people to reply while
 * being sent from a no-reply address.
 */
class EmailLocaleAndReplyToTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function sendAs(?string $locale, array $published): ?string
    {
        config(['services.postmark.localized_aliases' => $published]);
        Bus::fake();

        $profile = Profile::factory()->business()->create(['preferred_locale' => $locale]);
        app(EmailService::class)->send($profile, 'business-welcome-01', [], EmailService::CATEGORY_ACCOUNT);

        $alias = null;
        Bus::assertDispatched(SendTransactionalEmail::class, function (SendTransactionalEmail $job) use (&$alias): bool {
            $alias = $job->data['alias'];

            return true;
        });

        return $alias;
    }

    public function test_uses_the_localized_alias_when_it_is_listed(): void
    {
        $this->assertSame('business-welcome-01-es', $this->sendAs('es', ['business-welcome-01-es']));
        $this->assertSame('business-welcome-01-ca', $this->sendAs('ca', ['business-welcome-01-ca']));
        $this->assertSame('business-welcome-01-tr', $this->sendAs('tr', ['business-welcome-01-tr']));
    }

    public function test_falls_back_to_english_when_the_localized_alias_is_not_listed(): void
    {
        // Default config: nothing listed, so nothing changes until a translation is published.
        $this->assertSame('business-welcome-01', $this->sendAs('es', []));
        // Listed for another locale only.
        $this->assertSame('business-welcome-01', $this->sendAs('ca', ['business-welcome-01-es']));
    }

    public function test_english_other_and_missing_locales_keep_the_base_alias(): void
    {
        $listed = ['business-welcome-01-es', 'business-welcome-01-en', 'business-welcome-01-fr'];

        $this->assertSame('business-welcome-01', $this->sendAs('en', $listed));
        $this->assertSame('business-welcome-01', $this->sendAs('fr', $listed));
        $this->assertSame('business-welcome-01', $this->sendAs(null, $listed));
    }

    public function test_a_region_suffixed_locale_uses_its_language(): void
    {
        $this->assertSame('business-welcome-01-es', $this->sendAs('es-ES', ['business-welcome-01-es']));
    }

    public function test_the_default_localized_alias_list_is_empty(): void
    {
        $this->assertSame([], config('services.postmark.localized_aliases'));
    }

    public function test_onboarding_drip_welcome_sets_reply_to_from_config(): void
    {
        config(['services.postmark.reply_to' => 'team@kolabing.com']);
        Bus::fake();

        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        app(OnboardingDripService::class)->startForProfile($profile);
        $state = OnboardingDripState::query()->where('profile_id', $profile->id)->firstOrFail();
        DB::table('onboarding_drip_states')->where('id', $state->id)->update(['scheduled_for' => now()->subHour()]);

        $this->artisan('app:send-onboarding-drip')->assertSuccessful();

        Bus::assertDispatched(SendTransactionalEmail::class, fn (SendTransactionalEmail $job): bool => $job->data['alias'] === 'business-welcome-01'
            && $job->data['reply_to'] === 'team@kolabing.com');
    }

    public function test_reply_to_defaults_to_hello_at_kolabing(): void
    {
        $this->assertSame('hello@kolabing.com', config('services.postmark.reply_to'));
    }

    public function test_other_emails_carry_no_reply_to(): void
    {
        Bus::fake();

        $profile = Profile::factory()->business()->create();
        app(EmailService::class)->send($profile, 'password-changed', [], EmailService::CATEGORY_SECURITY);

        Bus::assertDispatched(SendTransactionalEmail::class, fn (SendTransactionalEmail $job): bool => $job->data['reply_to'] === null);
    }

    public function test_postmark_payload_includes_reply_to_only_when_given(): void
    {
        Http::fake(['api.postmarkapp.com/*' => Http::response(['ErrorCode' => 0])]);
        $client = new PostmarkClient('token', 'noreply@kolabing.com', 'Kolabing', 'outbound');

        $client->sendTemplate('a@example.com', 'business-welcome-01', [], null, 'hello@kolabing.com');
        $client->sendTemplate('b@example.com', 'business-welcome-01', []);

        Http::assertSent(fn (Request $request): bool => $request['To'] === 'a@example.com' && $request['ReplyTo'] === 'hello@kolabing.com');
        Http::assertSent(fn (Request $request): bool => $request['To'] === 'b@example.com' && ! isset($request['ReplyTo']));
    }
}
