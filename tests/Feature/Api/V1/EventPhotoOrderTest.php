<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EventPhotoOrderTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function photoFor(Event $event, int $order): EventPhoto
    {
        return EventPhoto::query()->create([
            'event_id' => $event->id,
            'url' => "https://example.com/{$event->id}-{$order}.jpg",
            'sort_order' => $order,
        ]);
    }

    public function test_the_event_creator_can_reorder_photos(): void
    {
        $owner = Profile::factory()->community()->create();
        $event = Event::factory()->create(['profile_id' => $owner->id]);
        $a = $this->photoFor($event, 0);
        $b = $this->photoFor($event, 1);
        $c = $this->photoFor($event, 2);

        $ids = $this->actingAs($owner)
            ->putJson("/api/v1/events/{$event->id}/photos/order", ['ids' => [$c->id, $b->id, $a->id]])
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$c->id, $b->id, $a->id], $ids);
        $this->assertSame(0, $c->fresh()->sort_order);
        $this->assertSame(1, $b->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);
    }

    public function test_photos_from_another_event_are_ignored(): void
    {
        $owner = Profile::factory()->community()->create();
        $event = Event::factory()->create(['profile_id' => $owner->id]);
        $mine = $this->photoFor($event, 0);
        $foreign = $this->photoFor(Event::factory()->create(), 0);

        $ids = $this->actingAs($owner)
            ->putJson("/api/v1/events/{$event->id}/photos/order", ['ids' => [$foreign->id, $mine->id]])
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$mine->id], $ids);
        $this->assertSame(0, $foreign->fresh()->sort_order);
    }

    public function test_omitted_photos_keep_their_relative_order_at_the_end(): void
    {
        $owner = Profile::factory()->community()->create();
        $event = Event::factory()->create(['profile_id' => $owner->id]);
        $a = $this->photoFor($event, 0);
        $b = $this->photoFor($event, 1);
        $c = $this->photoFor($event, 2);

        $ids = $this->actingAs($owner)
            ->putJson("/api/v1/events/{$event->id}/photos/order", ['ids' => [$c->id]])
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$c->id, $a->id, $b->id], $ids);
    }

    public function test_a_community_manager_can_reorder(): void
    {
        $community = Community::factory()->create();
        $event = Event::factory()->create(['community_id' => $community->id]);
        $this->photoFor($event, 0);

        $manager = Profile::factory()->attendee()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'profile_id' => $manager->id,
            'can_manage' => true,
        ]);

        $this->actingAs($manager)
            ->putJson("/api/v1/events/{$event->id}/photos/order", ['ids' => [$event->photos()->first()->id]])
            ->assertOk();
    }

    public function test_a_stranger_cannot_reorder(): void
    {
        $event = Event::factory()->create();
        $a = $this->photoFor($event, 0);
        $b = $this->photoFor($event, 1);

        $this->actingAs(Profile::factory()->community()->create())
            ->putJson("/api/v1/events/{$event->id}/photos/order", ['ids' => [$b->id, $a->id]])
            ->assertForbidden();

        $this->assertSame(0, $a->fresh()->sort_order);
        $this->assertSame(1, $b->fresh()->sort_order);
    }

    public function test_an_empty_id_list_is_rejected(): void
    {
        $owner = Profile::factory()->community()->create();
        $event = Event::factory()->create(['profile_id' => $owner->id]);

        $this->actingAs($owner)
            ->putJson("/api/v1/events/{$event->id}/photos/order", ['ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ids');
    }
}
