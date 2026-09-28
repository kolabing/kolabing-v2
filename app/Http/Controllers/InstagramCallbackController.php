<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\InstagramException;
use App\Models\InstagramDataDeletionRequest;
use App\Models\Profile;
use App\Services\InstagramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Web endpoints Meta calls (BE-NF-75, docs/instagram-connect.md):
 *   GET  /instagram/callback               OAuth redirect_uri
 *   POST /instagram/deauthorize            user removed the app (signed_request)
 *   POST /instagram/data-deletion          user asked for deletion (signed_request)
 *   GET  /instagram/data-deletion/{code}   human-readable deletion status
 */
class InstagramCallbackController extends Controller
{
    public function __construct(
        private readonly InstagramService $instagram,
    ) {}

    public function callback(Request $request): RedirectResponse
    {
        $state = $this->instagram->parseState($request->query('state'));
        $back = fn (string $status, ?string $reason = null): RedirectResponse => redirect()->away(
            $this->instagram->returnUrl($state['return'], $status, $reason)
        );

        if ($request->filled('error')) {
            // error=access_denied, error_reason=user_denied when the user cancels.
            return $back('error', 'denied');
        }

        if (! $state['valid'] || ! $request->filled('code')) {
            return $back('error', 'invalid_state');
        }

        $profile = Profile::query()->find($state['profile_id']);

        if ($profile === null || ! $this->instagram->isAvailableFor($profile)) {
            return $back('error', 'disabled');
        }

        try {
            $this->instagram->connect($profile, (string) $request->query('code'));
        } catch (InstagramException $e) {
            return $back('error', $e->reason());
        } catch (\Throwable $e) {
            report($e);

            return $back('error', 'error');
        }

        return $back('ok');
    }

    public function deauthorize(Request $request): JsonResponse
    {
        $payload = $this->instagram->parseSignedRequest($request->input('signed_request'));

        if ($payload === null) {
            return response()->json(['success' => false], 400);
        }

        $count = $this->instagram->deauthorize((string) $payload['user_id']);
        Log::info('Instagram deauthorize callback', ['accounts' => $count]);

        return response()->json(['success' => true]);
    }

    public function dataDeletion(Request $request): JsonResponse
    {
        $payload = $this->instagram->parseSignedRequest($request->input('signed_request'));

        if ($payload === null) {
            return response()->json(['success' => false], 400);
        }

        $deletion = $this->instagram->deleteData((string) $payload['user_id']);

        // Meta's required response shape: {url, confirmation_code}.
        return response()->json([
            'url' => url('/instagram/data-deletion/'.$deletion->confirmation_code),
            'confirmation_code' => $deletion->confirmation_code,
        ]);
    }

    public function deletionStatus(string $code): Response
    {
        $deletion = InstagramDataDeletionRequest::query()->where('confirmation_code', $code)->first();

        abort_if($deletion === null, 404);

        return response()->view('instagram.data-deletion-status', ['deletion' => $deletion]);
    }
}
