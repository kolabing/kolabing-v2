<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

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

        return view('admin.reviews.index', [
            'reviews' => $reviews,
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
}
