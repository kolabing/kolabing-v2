<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ChatParticipantState;
use App\Enums\ChatThreadType;
use App\Models\Application;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\CollabOpportunity;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\Event;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityChatOperatorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_communities_index_renders_with_counts(): void
    {
        $owner = Profile::factory()->community()->create(['email' => 'owner@example.com']);
        $community = Community::factory()->forOwner($owner)->create(['name' => 'Run Club Madrid']);
        CommunityTier::factory()->forCommunity($community)->defaultTier()->create();
        CommunityMember::factory()->forCommunity($community)->create();
        ChatThread::factory()->main()->forCommunity($community)->create();

        $response = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.communities.index'));

        $response->assertOk();
        $response->assertSee('Run Club Madrid', false);
        $response->assertSee('owner@example.com', false);
    }

    public function test_communities_index_search_filters_by_owner_email(): void
    {
        $matchOwner = Profile::factory()->community()->create(['email' => 'findme@example.com']);
        Community::factory()->forOwner($matchOwner)->create(['name' => 'Target Community']);
        Community::factory()->create(['name' => 'Other Community']);

        $response = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.communities.index', ['q' => 'findme']));

        $response->assertOk();
        $response->assertSee('Target Community', false);
        $response->assertDontSee('Other Community', false);
    }

    public function test_community_detail_lists_tiers_events_and_threads(): void
    {
        $community = Community::factory()->create(['name' => 'Greek Life']);
        CommunityTier::factory()->forCommunity($community)->create(['name' => 'Gold']);

        Event::factory()->create([
            'community_id' => $community->id,
            'name' => 'Future Gala',
            'event_date' => now()->addWeek()->toDateString(),
        ]);
        Event::factory()->create([
            'community_id' => $community->id,
            'name' => 'Past Mixer',
            'event_date' => now()->subWeek()->toDateString(),
        ]);

        $main = ChatThread::factory()->main()->forCommunity($community)->create(['name' => 'Main Lobby']);
        ChatMessage::factory()->count(2)->create(['thread_id' => $main->id, 'application_id' => null]);

        $response = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.communities.show', $community));

        $response->assertOk();
        $response->assertSee('Gold', false);
        $response->assertSee('Future Gala', false);
        $response->assertDontSee('Past Mixer', false);
        $response->assertSee('Main Lobby', false);
    }

    public function test_businesses_index_lists_only_business_profiles(): void
    {
        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create(['email' => 'community-only@example.com']);

        $response = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.businesses.index'));

        $response->assertOk();
        $response->assertSee($business->email, false);
        $response->assertDontSee('community-only@example.com', false);
    }

    public function test_business_detail_shows_active_collaboration_chats_only(): void
    {
        $business = Profile::factory()->business()->create();
        $opportunity = CollabOpportunity::factory()->create(['creator_profile_id' => $business->id]);
        $application = Application::factory()->forOpportunity($opportunity)->create();

        $activeThread = ChatThread::query()->create([
            'type' => ChatThreadType::Collaboration->value,
            'application_id' => $application->id,
            'last_message_at' => now(),
        ]);

        // A second application with no messages (no last_message_at) must not appear.
        $silentApp = Application::factory()->forOpportunity($opportunity)->create();
        ChatThread::query()->create([
            'type' => ChatThreadType::Collaboration->value,
            'application_id' => $silentApp->id,
        ]);

        $response = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.businesses.show', $business));

        $response->assertOk();
        $response->assertSee(route('admin.chats.show', $activeThread), false);
    }

    public function test_business_detail_rejects_non_business_profile(): void
    {
        $community = Profile::factory()->community()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.businesses.show', $community))
            ->assertNotFound();
    }

    public function test_chat_transcript_renders_messages_and_participants(): void
    {
        $community = Community::factory()->create();
        $thread = ChatThread::factory()->custom()->forCommunity($community)->create(['name' => 'Captains']);
        $sender = Profile::factory()->attendee()->create();
        ChatMessage::factory()->create([
            'thread_id' => $thread->id,
            'application_id' => null,
            'sender_profile_id' => $sender->id,
            'content' => 'Hello operator world',
        ]);

        $response = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.chats.show', $thread));

        $response->assertOk();
        $response->assertSee('Hello operator world', false);
        $response->assertSee($sender->email, false);
    }

    public function test_maintainer_can_soft_delete_a_custom_thread(): void
    {
        $community = Community::factory()->create();
        $thread = ChatThread::factory()->custom()->forCommunity($community)->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->delete(route('admin.chats.destroy', $thread))
            ->assertRedirect();

        $this->assertSoftDeleted('chat_threads', ['id' => $thread->id]);
    }

    public function test_main_thread_cannot_be_deleted(): void
    {
        $community = Community::factory()->create();
        $thread = ChatThread::factory()->main()->forCommunity($community)->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->delete(route('admin.chats.destroy', $thread))
            ->assertSessionHasErrors('thread');

        $this->assertNotSoftDeleted('chat_threads', ['id' => $thread->id]);
    }

    public function test_maintainer_can_ban_a_participant(): void
    {
        $community = Community::factory()->create();
        $thread = ChatThread::factory()->custom()->forCommunity($community)->create();
        $member = Profile::factory()->attendee()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.chats.ban', $thread), ['profile_id' => $member->id])
            ->assertRedirect();

        $this->assertDatabaseHas('chat_thread_participants', [
            'thread_id' => $thread->id,
            'profile_id' => $member->id,
            'state' => ChatParticipantState::Banned->value,
            'banned_by' => null,
        ]);
    }

    public function test_non_maintainer_is_forbidden_on_every_operator_route(): void
    {
        $user = User::factory()->create(['is_maintainer' => false]);
        $community = Community::factory()->create();
        $business = Profile::factory()->business()->create();
        $thread = ChatThread::factory()->custom()->forCommunity($community)->create();

        $this->actingAs($user, 'admin')->get(route('admin.communities.index'))->assertForbidden();
        $this->actingAs($user, 'admin')->get(route('admin.communities.show', $community))->assertForbidden();
        $this->actingAs($user, 'admin')->get(route('admin.businesses.index'))->assertForbidden();
        $this->actingAs($user, 'admin')->get(route('admin.businesses.show', $business))->assertForbidden();
        $this->actingAs($user, 'admin')->get(route('admin.chats.show', $thread))->assertForbidden();
        $this->actingAs($user, 'admin')->delete(route('admin.chats.destroy', $thread))->assertForbidden();
        $this->actingAs($user, 'admin')->post(route('admin.chats.ban', $thread), ['profile_id' => $business->id])->assertForbidden();
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get(route('admin.communities.index'))->assertRedirect(route('login'));
    }
}
