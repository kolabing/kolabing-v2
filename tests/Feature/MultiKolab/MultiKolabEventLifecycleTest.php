<?php

declare(strict_types=1);

namespace Tests\Feature\MultiKolab;

use App\Enums\MultiKolabEventStatus;
use App\Enums\MultiKolabRoleApplicationStatus;
use App\Exceptions\EventCreatorEntitlementRequiredException;
use App\Exceptions\MultiKolabEventPublishValidationException;
use App\Models\MultiKolabEvent;
use App\Models\MultiKolabRole;
use App\Models\MultiKolabRoleApplication;
use App\Models\Profile;
use App\Services\MultiKolabEventService;
use App\Services\OrganizerEntitlementService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MultiKolabEventLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): MultiKolabEventService
    {
        return app(MultiKolabEventService::class);
    }

    private function entitle(Profile $profile): void
    {
        app(OrganizerEntitlementService::class)->grant($profile);
    }

    // --- Draft creation: never gated -----------------------------------

    public function test_business_can_create_a_draft_without_entitlement(): void
    {
        $creator = Profile::factory()->business()->create();
        $this->assertFalse($creator->hasEventCreatorEntitlement());

        $event = $this->service()->createDraft($creator, ['title' => 'Launch Weekend']);

        $this->assertSame(MultiKolabEventStatus::Draft, $event->status);
        $this->assertSame($creator->id, $event->creator_profile_id);
    }

    public function test_community_can_create_a_draft_without_entitlement(): void
    {
        $creator = Profile::factory()->community()->create();

        $event = $this->service()->createDraft($creator, ['title' => 'Launch Weekend']);

        $this->assertSame(MultiKolabEventStatus::Draft, $event->status);
    }

    // --- Owner-only editing (policy) ------------------------------------

    public function test_only_the_creator_can_update_the_event_per_policy(): void
    {
        $creator = Profile::factory()->business()->create();
        $stranger = Profile::factory()->business()->create();
        $event = $this->service()->createDraft($creator, ['title' => 'Launch Weekend']);

        $this->assertTrue($stranger->can('update', $event) === false);
        $this->assertTrue($creator->can('update', $event));
    }

    // --- Publish requires entitlement ------------------------------------

    public function test_publish_without_entitlement_is_rejected(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = $this->service()->createDraft($creator, ['title' => 'Launch Weekend']);
        $this->service()->addRole($event, ['title' => 'Venue Partner', 'eligible_account_type' => 'business']);
        $event->update(['description' => 'A great event.']);

        $this->expectException(EventCreatorEntitlementRequiredException::class);

        $this->service()->publish($event->fresh(), $creator);
    }

    public function test_entitled_business_and_community_can_both_publish(): void
    {
        foreach (['business', 'community'] as $type) {
            $creator = Profile::factory()->{$type}()->create();
            $this->entitle($creator);

            $event = $this->service()->createDraft($creator, [
                'title' => 'Launch Weekend',
                'description' => 'A great event.',
            ]);
            $this->service()->addRole($event, ['title' => 'Partner', 'eligible_account_type' => 'either']);

            $published = $this->service()->publish($event->fresh(), $creator);

            $this->assertSame(MultiKolabEventStatus::Recruiting, $published->status, "failed for {$type}");
            $this->assertNotNull($published->published_at);
        }
    }

    // --- Strict publish validation ---------------------------------------

    public function test_publish_requires_https_rsvp_url(): void
    {
        $creator = Profile::factory()->business()->create();
        $this->entitle($creator);
        $event = $this->service()->createDraft($creator, [
            'title' => 'Launch Weekend',
            'description' => 'A great event.',
            'rsvp_url' => 'http://insecure.example.com',
        ]);
        $this->service()->addRole($event, ['title' => 'Partner', 'eligible_account_type' => 'either']);

        try {
            $this->service()->publish($event->fresh(), $creator);
            $this->fail('Expected MultiKolabEventPublishValidationException.');
        } catch (MultiKolabEventPublishValidationException $e) {
            $this->assertArrayHasKey('rsvp_url', $e->errors());
        }
    }

    public function test_publish_requires_venue_needed_consistency(): void
    {
        $creator = Profile::factory()->business()->create();
        $this->entitle($creator);
        $event = $this->service()->createDraft($creator, [
            'title' => 'Launch Weekend',
            'description' => 'A great event.',
            'venue_needed' => true,
            'city' => null,
        ]);
        $this->service()->addRole($event, ['title' => 'Partner', 'eligible_account_type' => 'either']);

        try {
            $this->service()->publish($event->fresh(), $creator);
            $this->fail('Expected MultiKolabEventPublishValidationException.');
        } catch (MultiKolabEventPublishValidationException $e) {
            $this->assertArrayHasKey('city', $e->errors());
        }
    }

    public function test_publish_requires_at_least_one_role(): void
    {
        $creator = Profile::factory()->business()->create();
        $this->entitle($creator);
        $event = $this->service()->createDraft($creator, [
            'title' => 'Launch Weekend',
            'description' => 'A great event.',
        ]);

        try {
            $this->service()->publish($event->fresh(), $creator);
            $this->fail('Expected MultiKolabEventPublishValidationException.');
        } catch (MultiKolabEventPublishValidationException $e) {
            $this->assertArrayHasKey('roles', $e->errors());
        }
    }

    public function test_publish_requires_description(): void
    {
        $creator = Profile::factory()->business()->create();
        $this->entitle($creator);
        $event = $this->service()->createDraft($creator, ['title' => 'Launch Weekend']);
        $this->service()->addRole($event, ['title' => 'Partner', 'eligible_account_type' => 'either']);

        try {
            $this->service()->publish($event->fresh(), $creator);
            $this->fail('Expected MultiKolabEventPublishValidationException.');
        } catch (MultiKolabEventPublishValidationException $e) {
            $this->assertArrayHasKey('description', $e->errors());
        }
    }

    // --- Moderation: reactive-only, per founder decision (contract §12) --

    public function test_free_text_fields_accept_arbitrary_content_with_no_proactive_filter(): void
    {
        // Per the founder's decision (contract §12): Multi-Kolab text behaves
        // exactly like existing kolabs.title/description — no profanity
        // filter, no automatic rejection, no external moderation provider.
        // This test locks in that decision as a regression guard.
        $creator = Profile::factory()->business()->create();
        $event = $this->service()->createDraft($creator, [
            'title' => 'This event is absolutely, unbelievably, wildly great!!!',
            'description' => str_repeat('Loud UGC text with punctuation!!! ', 5),
        ]);

        $this->assertSame('This event is absolutely, unbelievably, wildly great!!!', $event->title);

        $role = $this->service()->addRole($event, [
            'title' => 'Partner',
            'eligible_account_type' => 'either',
            'need' => 'Any old free text goes here, unfiltered.',
        ]);

        $this->assertSame('Any old free text goes here, unfiltered.', $role->need);
    }

    // --- Transition table: cancelled is terminal --------------------------

    public function test_cancelled_event_cannot_be_confirmed_or_published(): void
    {
        $creator = Profile::factory()->business()->create();
        $this->entitle($creator);
        $event = $this->service()->createDraft($creator, [
            'title' => 'Launch Weekend',
            'description' => 'A great event.',
        ]);
        $this->service()->addRole($event, ['title' => 'Partner', 'eligible_account_type' => 'either']);
        $published = $this->service()->publish($event->fresh(), $creator);

        $cancelled = $this->service()->cancel($published, $creator, 'Change of plans.');
        $this->assertSame(MultiKolabEventStatus::Cancelled, $cancelled->status);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->confirm($cancelled, $creator);
    }

    public function test_cancelled_event_cannot_be_cancelled_again_or_recruited(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->cancelled()->for($creator, 'creatorProfile')->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service()->cancel($event, $creator, 'Another reason.');
    }

    // --- Status-event audit trail -----------------------------------------

    public function test_lifecycle_transitions_each_record_one_status_event(): void
    {
        $creator = Profile::factory()->business()->create();
        $this->entitle($creator);
        $event = $this->service()->createDraft($creator, [
            'title' => 'Launch Weekend',
            'description' => 'A great event.',
        ]);
        $this->service()->addRole($event, ['title' => 'Partner', 'eligible_account_type' => 'either']);

        $published = $this->service()->publish($event->fresh(), $creator);
        $confirmed = $this->service()->confirm($published, $creator);
        $completed = $this->service()->complete($confirmed, $creator);

        $statuses = $completed->statusEvents()->pluck('status')->map(fn ($s) => $s->value)->all();

        $this->assertSame(['recruiting', 'confirmed', 'completed'], $statuses);
    }

    public function test_cancel_records_a_status_event_with_the_reason(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = $this->service()->createDraft($creator, ['title' => 'Launch Weekend']);

        $cancelled = $this->service()->cancel($event, $creator, 'Venue fell through.');

        $statusEvent = $cancelled->statusEvents()->latest()->first();
        $this->assertSame('cancelled', $statusEvent->status->value);
        $this->assertSame('Venue fell through.', $statusEvent->reason);
        $this->assertSame($creator->id, $statusEvent->actor_profile_id);
    }

    // --- Role removal prohibited once an application is accepted ---------

    public function test_role_cannot_be_removed_once_it_has_an_accepted_application(): void
    {
        $event = MultiKolabEvent::factory()->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create();
        MultiKolabRoleApplication::factory()
            ->for($role, 'role')
            ->create(['status' => MultiKolabRoleApplicationStatus::Accepted]);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->removeRole($role);
    }

    public function test_role_without_an_accepted_application_can_be_removed(): void
    {
        $event = MultiKolabEvent::factory()->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create();
        MultiKolabRoleApplication::factory()
            ->for($role, 'role')
            ->create(['status' => MultiKolabRoleApplicationStatus::Pending]);

        $this->service()->removeRole($role);

        $this->assertModelMissing($role);
    }

    // --- positions_needed >= 1 --------------------------------------------

    public function test_add_role_rejects_zero_positions_needed(): void
    {
        $event = MultiKolabEvent::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service()->addRole($event, [
            'title' => 'Partner',
            'eligible_account_type' => 'either',
            'positions_needed' => 0,
        ]);
    }

    // --- Reportability (founder decision, contract §12) --------------------

    public function test_multi_kolab_event_and_role_are_reportable_via_the_existing_report_endpoint(): void
    {
        $event = MultiKolabEvent::factory()->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create();
        $reporter = Profile::factory()->community()->create();

        foreach (['multi_kolab_event' => $event->id, 'multi_kolab_role' => $role->id] as $targetType => $targetId) {
            $this->actingAs($reporter)
                ->postJson('/api/v1/reports', [
                    'target_type' => $targetType,
                    'target_id' => $targetId,
                    'reason' => 'inappropriate',
                ])
                ->assertStatus(201)
                ->assertJsonPath('success', true);

            $this->assertDatabaseHas('content_reports', [
                'reporter_profile_id' => $reporter->id,
                'target_type' => $targetType,
                'target_id' => $targetId,
            ]);
        }
    }
}
