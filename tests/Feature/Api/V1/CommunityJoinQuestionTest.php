<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Enums\UserType;
use App\Models\Community;
use App\Models\CommunityJoinAnswer;
use App\Models\CommunityJoinQuestion;
use App\Models\CommunityJoinRequest;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CommunityJoinQuestionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Profile $leader;

    private Community $community;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = Profile::query()->create([
            'email' => 'leader-'.uniqid().'@example.test',
            'password' => 'secret1234',
            'user_type' => UserType::Community,
        ]);

        $this->community = Community::query()->create([
            'owner_profile_id' => $this->leader->id,
            'name' => 'Question Test Club',
            'slug' => 'questions-'.uniqid(),
            'type' => 'running',
        ]);
    }

    private function outsider(): Profile
    {
        return Profile::query()->create([
            'email' => 'outsider-'.uniqid().'@example.test',
            'password' => 'secret1234',
            'user_type' => UserType::Attendee,
        ]);
    }

    private function create(string $prompt, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->leader)->postJson(
            "/api/v1/communities/{$this->community->id}/join-questions",
            ['prompt' => $prompt] + $extra
        );
    }

    public function test_a_leader_creates_a_question(): void
    {
        $this->create('Why do you want to join?')
            ->assertStatus(201)
            ->assertJsonPath('data.prompt', 'Why do you want to join?')
            ->assertJsonPath('data.required', true)
            ->assertJsonPath('data.position', 1);
    }

    public function test_the_set_comes_back_in_display_order(): void
    {
        $this->create('Third', ['position' => 3]);
        $this->create('First', ['position' => 1]);
        $this->create('Second', ['position' => 2]);

        $response = $this->actingAs($this->leader)
            ->getJson("/api/v1/communities/{$this->community->id}/join-questions");

        $response->assertStatus(200);
        $this->assertSame(
            ['First', 'Second', 'Third'],
            collect($response->json('data.questions'))->pluck('prompt')->all()
        );
    }

    /**
     * The cap lives in the service, so it holds for every caller — not just the
     * UI that happens to know about it.
     */
    public function test_the_sixth_active_question_is_refused(): void
    {
        for ($i = 1; $i <= CommunityJoinQuestion::MAX_ACTIVE; $i++) {
            $this->create("Question $i")->assertStatus(201);
        }

        $this->create('One too many')
            ->assertStatus(422)
            ->assertJsonPath('error', 'too_many_questions');

        $this->assertSame(
            CommunityJoinQuestion::MAX_ACTIVE,
            CommunityJoinQuestion::query()->where('is_active', true)->count()
        );
    }

    public function test_retiring_frees_a_slot(): void
    {
        $ids = [];
        for ($i = 1; $i <= CommunityJoinQuestion::MAX_ACTIVE; $i++) {
            $ids[] = $this->create("Question $i")->json('data.id');
        }

        $this->actingAs($this->leader)
            ->deleteJson("/api/v1/communities/{$this->community->id}/join-questions/{$ids[0]}")
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        // The row is retired, not gone.
        $this->assertNotNull(CommunityJoinQuestion::query()->find($ids[0]));

        $this->create('Now there is room')->assertStatus(201);
    }

    public function test_a_retired_question_leaves_the_applicant_set(): void
    {
        $id = $this->create('Going away')->json('data.id');
        $this->create('Staying');

        $this->actingAs($this->leader)
            ->deleteJson("/api/v1/communities/{$this->community->id}/join-questions/{$id}");

        $response = $this->actingAs($this->outsider())
            ->getJson("/api/v1/communities/{$this->community->id}/join-questions");

        $this->assertSame(
            ['Staying'],
            collect($response->json('data.questions'))->pluck('prompt')->all()
        );
    }

    /**
     * Retiring must not take the answers with it, or an old application becomes
     * an answer to a question nobody can see.
     */
    public function test_retiring_keeps_existing_answers_readable(): void
    {
        $questionId = $this->create('Why join?')->json('data.id');
        $applicant = $this->outsider();

        $request = CommunityJoinRequest::query()->create([
            'community_id' => $this->community->id,
            'profile_id' => $applicant->id,
            'status' => 'pending',
            'requested_at' => Carbon::now(),
        ]);
        CommunityJoinAnswer::query()->create([
            'join_request_id' => $request->id,
            'question_id' => $questionId,
            'answer' => 'Because I run.',
        ]);

        $this->actingAs($this->leader)
            ->deleteJson("/api/v1/communities/{$this->community->id}/join-questions/{$questionId}");

        $this->assertSame(1, $request->fresh()->answers()->count());
        $this->assertSame('Why join?', $request->answers()->first()->question->prompt);
    }

    public function test_a_leader_edits_a_question(): void
    {
        $id = $this->create('Typo?')->json('data.id');

        $this->actingAs($this->leader)
            ->patchJson("/api/v1/communities/{$this->community->id}/join-questions/{$id}", [
                'prompt' => 'Fixed prompt',
                'required' => false,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.prompt', 'Fixed prompt')
            ->assertJsonPath('data.required', false);
    }

    // -------------------------------------------------------------------------
    // Authorization
    // -------------------------------------------------------------------------

    public function test_anyone_signed_in_can_read_the_set(): void
    {
        $this->create('Public to applicants');

        $this->actingAs($this->outsider())
            ->getJson("/api/v1/communities/{$this->community->id}/join-questions")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.questions');
    }

    public function test_a_non_manager_cannot_change_the_set(): void
    {
        $id = $this->create('Leader only')->json('data.id');
        $outsider = $this->outsider();

        $this->actingAs($outsider)
            ->postJson("/api/v1/communities/{$this->community->id}/join-questions", [
                'prompt' => 'Not allowed',
            ])
            ->assertStatus(403);

        $this->actingAs($outsider)
            ->patchJson("/api/v1/communities/{$this->community->id}/join-questions/{$id}", [
                'prompt' => 'Not allowed',
            ])
            ->assertStatus(403);

        $this->actingAs($outsider)
            ->deleteJson("/api/v1/communities/{$this->community->id}/join-questions/{$id}")
            ->assertStatus(403);
    }

    /**
     * A plain member is not a manager — the roster endpoint draws the same line.
     */
    public function test_a_plain_member_cannot_change_the_set(): void
    {
        $member = $this->outsider();
        $this->community->members()->create([
            'profile_id' => $member->id,
            'can_manage' => false,
            'status' => CommunityMemberStatus::Active->value,
            'joined_at' => Carbon::now(),
        ]);

        $this->actingAs($member)
            ->postJson("/api/v1/communities/{$this->community->id}/join-questions", [
                'prompt' => 'Nope',
            ])
            ->assertStatus(403);
    }

    public function test_a_question_from_another_community_is_not_found(): void
    {
        $other = Community::query()->create([
            'owner_profile_id' => $this->leader->id,
            'name' => 'Other Club',
            'slug' => 'other-'.uniqid(),
            'type' => 'running',
        ]);
        $foreign = $other->joinQuestions()->create([
            'position' => 1, 'prompt' => 'Elsewhere', 'required' => true, 'is_active' => true,
        ]);

        $this->actingAs($this->leader)
            ->deleteJson("/api/v1/communities/{$this->community->id}/join-questions/{$foreign->id}")
            ->assertStatus(404);
    }

    public function test_prompt_is_validated(): void
    {
        $this->actingAs($this->leader)
            ->postJson("/api/v1/communities/{$this->community->id}/join-questions", [])
            ->assertStatus(422);

        $this->create(str_repeat('a', 281))->assertStatus(422);
    }
}
