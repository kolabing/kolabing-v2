<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\NewsletterSubscribeRequest;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class NewsletterController extends Controller
{
    /**
     * Store a landing-page mailing-list signup. Idempotent per email: a repeat
     * submission updates the existing row rather than erroring, so a visitor
     * who re-opens the pop-up never sees a "duplicate" failure.
     */
    public function store(NewsletterSubscribeRequest $request): JsonResponse|RedirectResponse
    {
        $email = Str::lower(trim((string) $request->string('email')));

        NewsletterSubscriber::query()->updateOrCreate(
            ['email' => $email],
            [
                'audience' => $request->input('audience'),
                'source' => 'landing_popup',
                'locale' => app()->getLocale(),
                'ip_address' => $request->ip(),
            ],
        );

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('newsletter_status', 'ok');
    }
}
