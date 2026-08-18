<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ListingTestimonial;
use App\Models\RankingPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin surface for the public community-rankings directory: publish/unpublish the
 * editorial pages (the progressive-rollout guard) and moderate member testimonials.
 * Re-ranking itself stays in /admin/crm (score + metrics.rank_override + the listed flag).
 */
class RankingController extends Controller
{
    public function index(): View
    {
        return view('admin.rankings.index', [
            'pages' => RankingPage::query()->orderBy('city')->orderBy('sort')->get()->groupBy('city'),
            'pending' => ListingTestimonial::query()->with('listing')
                ->where('status', 'pending')->latest()->get(),
        ]);
    }

    public function togglePublish(RankingPage $page): RedirectResponse
    {
        $page->update(['published' => ! $page->published]);

        return back()->with('status', $page->published ? "Published: {$page->title}" : "Unpublished: {$page->title}");
    }

    public function moderate(ListingTestimonial $testimonial, string $decision): RedirectResponse
    {
        abort_unless(in_array($decision, ['approve', 'reject'], true), 404);

        $testimonial->update([
            'status' => $decision === 'approve' ? 'approved' : 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => optional(auth('admin')->user())->name ?? 'admin',
        ]);

        return back()->with('status', 'Testimonial '.$decision.'d.');
    }
}
