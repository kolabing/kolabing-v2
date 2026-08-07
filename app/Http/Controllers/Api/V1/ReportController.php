<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReportRequest;
use App\Models\Profile;
use App\Services\ModerationService;
use Illuminate\Http\JsonResponse;

/**
 * Reporting objectionable content (App Review Guideline 1.2). Persists a report
 * row and emails the developer/moderation team.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ModerationService $moderation,
    ) {}

    /**
     * File a report against a profile, kolab, review or chat message.
     *
     * POST /api/v1/reports
     */
    public function store(StoreReportRequest $request): JsonResponse
    {
        /** @var Profile $reporter */
        $reporter = $request->user();

        $this->moderation->report(
            reporter: $reporter,
            targetType: $request->string('target_type')->value(),
            targetId: $request->string('target_id')->value(),
            reportedProfileId: $request->input('reported_profile_id'),
            reason: $request->string('reason')->value(),
            note: $request->input('note'),
        );

        return response()->json(['success' => true], 201);
    }
}
