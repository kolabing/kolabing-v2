<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\KolabingTeamOfferKind;
use App\Models\KolabingTeamOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The offer card inside a Kolabing team message.
 *
 * ROLES §2.5: a business without an active subscription must not learn a
 * community's name, so a business_propose card only names the community when the
 * viewer is subscribed; otherwise it is described by type and city.
 *
 * @mixin KolabingTeamOffer
 */
class KolabingTeamOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $community = $this->communityProfile;
        $communityExtended = $community?->getExtendedProfile();

        $communityLabel = null;
        if ($this->kind === KolabingTeamOfferKind::BusinessPropose && $community !== null) {
            $communityLabel = $viewer !== null && $viewer->hasActiveSubscription()
                ? ($communityExtended?->name ?? 'A local community')
                : 'A local community';
        }

        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'status' => $this->status->value,
            'title' => $this->title,
            'message' => $this->message,
            'kolab_id' => $this->kolab_id,
            'kolab_title' => $this->whenLoaded('kolab', fn () => $this->kolab?->title),
            'venue_name' => $this->whenLoaded('kolab', fn () => $this->kolab?->venue_name),
            'community_label' => $communityLabel,
            'proposed_date' => $this->proposed_date?->toDateString(),
            'what_business_gets' => $this->what_business_gets,
            'what_community_gets' => $this->what_community_gets,
            'can_respond' => $this->isOpenForResponse(),
            'result_kolab_id' => $this->result_kolab_id,
            'result_application_id' => $this->result_application_id,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
        ];
    }
}
