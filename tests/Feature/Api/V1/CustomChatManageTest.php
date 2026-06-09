<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Community;
use App\Models\Profile;
use App\Services\CommunityService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CustomChatManageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function community(Profile $leader): Community
    {
        return app(CommunityService::class)->create($leader, ['name' => 'Run Club', 'type' => 'greek']);
    }

    private function customChat(Profile $leader, Community $community, string $name = 'Socials'): array
    {
        return $this->actingAs($leader)
            ->postJson("/api/v1/communities/{$community->id}/chats", ['name' => $name])
            ->json('data');
    }

    public function test_leader_renames_custom_chat_keeping_slug(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $chat = $this->customChat($leader, $community); // slug "socials"

        $this->actingAs($leader)
            ->patchJson("/api/v1/chats/{$chat['id']}", ['name' => 'Social Events'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Social Events')
            ->assertJsonPath('data.slug', 'socials'); // slug preserved → tier grants intact
    }

    public function test_leader_deletes_custom_chat(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $chat = $this->customChat($leader, $community);

        $this->actingAs($leader)
            ->deleteJson("/api/v1/chats/{$chat['id']}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('chat_threads', ['id' => $chat['id']]);
    }

    public function test_non_manager_cannot_rename_or_delete(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $chat = $this->customChat($leader, $community);
        $outsider = Profile::factory()->community()->create();

        $this->actingAs($outsider)
            ->patchJson("/api/v1/chats/{$chat['id']}", ['name' => 'Hijack'])
            ->assertStatus(403);
        $this->actingAs($outsider)
            ->deleteJson("/api/v1/chats/{$chat['id']}")
            ->assertStatus(403);

        $this->assertDatabaseHas('chat_threads', ['id' => $chat['id']]);
    }
}
