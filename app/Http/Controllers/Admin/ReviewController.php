<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CollaborationStatus;
use App\Http\Controllers\Controller;
use App\Models\CollaborationReview;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $reviews = CollaborationReview::query()
            ->with([
                'collaboration.kolab',
                'reviewerProfile',
                'reviewed',
            ])
            ->when($request->filled('reviewer_role'), fn ($q) => $q->where('reviewer_role', $request->string('reviewer_role')))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $excludedReviewIds = $this->resolveExcludedIds($reviews->items());

        return view('admin.reviews.index', [
            'reviews' => $reviews,
            'excludedReviewIds' => $excludedReviewIds,
            'filters' => [
                'reviewer_role' => (string) $request->string('reviewer_role'),
            ],
        ]);
    }

    public function toggleComment(CollaborationReview $review): RedirectResponse
    {
        $review->update(['public_comment_visible' => ! $review->public_comment_visible]);

        return redirect()->route('admin.reviews.index')
            ->with('status', $review->public_comment_visible ? 'Comment unhidden.' : 'Comment hidden from public view.');
    }

    /**
     * Given a page of reviews, return the IDs of reviews that would be excluded
     * from public reputation (pair rank > 2, ordered by created_at ASC).
     *
     * @param  array<int, CollaborationReview>  $reviews
     * @return array<int, string>
     */
    private function resolveExcludedIds(array $reviews): array
    {
        if (empty($reviews)) {
            return [];
        }

        $pairs = collect($reviews)
            ->map(fn (CollaborationReview $review) => [$review->reviewer_profile_id, $review->reviewed_profile_id])
            ->unique()
            ->values();

        $excluded = [];

        foreach ($pairs as [$reviewerId, $reviewedId]) {
            $ranked = CollaborationReview::query()
                ->where('reviewer_profile_id', $reviewerId)
                ->where('reviewed_profile_id', $reviewedId)
                ->whereNotNull('rating')
                ->whereHas('collaboration', fn ($q) => $q->where('status', CollaborationStatus::Completed))
                ->orderBy('created_at')
                ->pluck('id');

            foreach ($ranked->slice(2) as $id) {
                $excluded[] = $id;
            }
        }

        return $excluded;
    }
}
