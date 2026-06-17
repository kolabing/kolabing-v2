<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Application;
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
        $kolabResource = $this->when(
            $this->includeOpportunity,
            fn () => $this->whenLoaded('kolab', function () {
                return new KolabResource($this->kolab);
            })
        );
        $opportunityResource = $this->when(
            $this->includeOpportunity,
            fn () => $this->whenLoaded('kolab', function () {
                return new OpportunitySummaryResource($this->kolab);
            })
        );

        return [
            'id' => $this->id,
            'kolab_id' => $this->kolab_id,
            'kolab' => $kolabResource,
            'collab_opportunity_id' => $this->kolab_id,
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
}
