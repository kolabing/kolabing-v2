<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Profile;
use App\Services\Notifications\NotificationPayloadFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NotificationPayloadFactoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_referral_payload_uses_user_type_specific_deeplink(): void
    {
        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();
        $factory = app(NotificationPayloadFactory::class);

        $this->assertSame('/business/referrals', $factory->target(NotificationType::ReferralRewardEarned, null, null, $business)->deeplink);
        $this->assertSame('/community/referrals', $factory->target(NotificationType::ReferralRewardEarned, null, null, $community)->deeplink);
    }

    public function test_push_payload_preserves_mobile_contract_fields(): void
    {
        $profile = Profile::factory()->business()->create();
        $notification = Notification::factory()->forProfile($profile)->create([
            'type' => NotificationType::NewMessage,
            'target_id' => 'application-uuid',
            'target_type' => 'application',
            'deeplink' => '/application/application-uuid/chat',
            'dedupe_key' => 'message:message-uuid',
        ]);

        $payload = app(NotificationPayloadFactory::class)->toPushData($notification->fresh());

        $this->assertSame('new_message', $payload['type']);
        $this->assertSame('application-uuid', $payload['id']);
        $this->assertSame('application', $payload['target_type']);
        $this->assertSame('application-uuid', $payload['target_id']);
        $this->assertSame('/application/application-uuid/chat', $payload['deeplink']);
        $this->assertSame('message:message-uuid', $payload['dedupe_key']);
    }
}
