<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Collaboration;
use App\Models\CollaborationReview;
use App\Models\Kolab;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReviewModerationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    private function collaborationWithReview(array $reviewOverrides = []): CollaborationReview
    {
        $kolab = Kolab::factory()->published()->create(['title' => 'Beach Cleanup Kolab']);
        $application = Application::factory()->create([
            'kolab_id' => $kolab->id,
            'status' => ApplicationStatus::Accepted,
        ]);
        $collaboration = Collaboration::factory()->completed()->create([
            'kolab_id' => $kolab->id,
            'application_id' => $application->id,
        ]);

        return CollaborationReview::factory()->create(array_merge([
            'collaboration_id' => $collaboration->id,
            'reviewer_profile_id' => Profile::factory()->business(),
            'reviewed_profile_id' => Profile::factory()->community(),
            'reviewer_role' => 'business',
            'communication_rating' => 5,
            'reliability_rating' => 4,
            'fit_rating' => 5,
            'value_rating' => 4,
            'repeat_rating' => 5,
            'public_comment' => 'Great Kolab partner, would work with again!',
        ], $reviewOverrides));
    }

    public function test_maintainer_sees_review_list_with_ratings_and_comment(): void
    {
        $this->collaborationWithReview();

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.reviews.index'))
            ->assertOk()
            ->assertSee('Beach Cleanup Kolab')
            ->assertSee('Great Kolab partner, would work with again!')
            ->assertSee('4.6'); // overall_rating average of 5,4,5,4,5
    }

    public function test_non_maintainer_cannot_access_review_list(): void
    {
        $this->collaborationWithReview();
        $nonMaintainer = User::factory()->create(['is_maintainer' => false]);

        $this->actingAs($nonMaintainer, 'admin')
            ->get(route('admin.reviews.index'))
            ->assertForbidden();
    }

    public function test_maintainer_can_hide_public_comment(): void
    {
        $review = $this->collaborationWithReview();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.reviews.toggle-comment', $review))
            ->assertRedirect();

        $this->assertFalse($review->fresh()->public_comment_visible);
    }

    public function test_maintainer_can_unhide_public_comment(): void
    {
        $review = $this->collaborationWithReview(['public_comment_visible' => false]);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.reviews.toggle-comment', $review))
            ->assertRedirect();

        $this->assertTrue($review->fresh()->public_comment_visible);
    }

    public function test_hiding_public_comment_does_not_change_ratings(): void
    {
        $review = $this->collaborationWithReview();
        $overallBefore = $review->overall_rating;

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.reviews.toggle-comment', $review));

        $review->refresh();
        $this->assertSame(5, $review->communication_rating);
        $this->assertSame(4, $review->reliability_rating);
        $this->assertSame(5, $review->fit_rating);
        $this->assertSame(4, $review->value_rating);
        $this->assertSame(5, $review->repeat_rating);
        $this->assertSame($overallBefore, $review->fresh()->overall_rating);
        $this->assertSame('Great Kolab partner, would work with again!', $review->public_comment);
    }

    public function test_hidden_comment_remains_visible_in_admin_list(): void
    {
        $review = $this->collaborationWithReview(['public_comment_visible' => false]);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.reviews.index'))
            ->assertOk()
            ->assertSee('Great Kolab partner, would work with again!')
            ->assertSee('Hidden');
    }

    public function test_third_review_from_same_pair_shows_excluded_badge(): void
    {
        $reviewer = Profile::factory()->business()->create();
        $reviewed = Profile::factory()->community()->create();

        $this->collaborationWithReview([
            'reviewer_profile_id' => $reviewer->id,
            'reviewed_profile_id' => $reviewed->id,
        ]);
        $this->collaborationWithReview([
            'reviewer_profile_id' => $reviewer->id,
            'reviewed_profile_id' => $reviewed->id,
        ]);
        $this->collaborationWithReview([
            'reviewer_profile_id' => $reviewer->id,
            'reviewed_profile_id' => $reviewed->id,
        ]);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.reviews.index'))
            ->assertOk()
            ->assertSee('Excluded from reputation');
    }

    public function test_first_two_reviews_from_same_pair_show_no_excluded_badge(): void
    {
        $reviewer = Profile::factory()->business()->create();
        $reviewed = Profile::factory()->community()->create();

        $this->collaborationWithReview([
            'reviewer_profile_id' => $reviewer->id,
            'reviewed_profile_id' => $reviewed->id,
        ]);
        $this->collaborationWithReview([
            'reviewer_profile_id' => $reviewer->id,
            'reviewed_profile_id' => $reviewed->id,
        ]);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.reviews.index'))
            ->assertOk()
            ->assertDontSee('Excluded from reputation');
    }
}
