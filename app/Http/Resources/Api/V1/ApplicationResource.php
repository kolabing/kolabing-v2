<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Application;
use App\Services\LegacyOpportunityBridgeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Application
 */
class ApplicationResource extends JsonResource
{
    /**
     * Indicates if the resource should include opportunity details.
     */
    protected bool $includeOpportunity = true;

    /**
     * Disable opportunity inclusion to prevent circular references.
     */
    public function withoutOpportunity(): self
    {
        $this->includeOpportunity = false;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Phase 2: source the embedded opportunity object from the related KOLAB
        // when present (mapped through the bridge to the same OpportunitySummary
        // shape), falling back to the legacy collabOpportunity otherwise.
        $opportunityResource = $this->when(
            $this->includeOpportunity,
            fn () => $this->resolveOpportunitySummary()
        );

        return [
            'id' => $this->id,
            'collab_opportunity_id' => $this->collab_opportunity_id,
            'collab_opportunity' => $opportunityResource,
            'opportunity' => $opportunityResource,
            'applicant_profile' => $this->whenLoaded('applicantProfile', function () {
                return new ProfileSummaryResource($this->applicantProfile);
            }),
            'message' => $this->message,
            'availability' => $this->availability,
            'status' => $this->status->value,
            'collaboration' => $this->whenLoaded('collaboration', function () {
                return $this->collaboration
                    ? (new CollaborationResource($this->collaboration))->withoutApplication()
                    : null;
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Resolve the embedded opportunity summary, preferring the kolab relation.
     *
     * Returns the OpportunitySummaryResource when either relation is loaded and
     * present; otherwise a missing-value marker so the key behaves exactly as the
     * old `whenLoaded` did (omitted when nothing is loaded).
     */
    private function resolveOpportunitySummary(): mixed
    {
        if ($this->relationLoaded('kolab') && $this->kolab !== null) {
            $opportunity = app(LegacyOpportunityBridgeService::class)
                ->makeCompatibilityOpportunity($this->kolab);

            return new OpportunitySummaryResource($opportunity);
        }

        return $this->whenLoaded('collabOpportunity', function () {
            return new OpportunitySummaryResource($this->collabOpportunity);
        });
    }
}
