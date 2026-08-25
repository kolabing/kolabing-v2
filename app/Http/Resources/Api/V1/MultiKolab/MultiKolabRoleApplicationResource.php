<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\MultiKolab;

use App\Models\MultiKolabRoleApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Matches the frozen API contract §7 exactly. Deliberately never serializes
 * `withdrawal_reason` (contract §12: never publicly expose it) and never
 * nests the applicant's full profile — only `applicant_profile_id`/`_type`,
 * so listing a role's applications for organizer review carries no N+1 risk
 * from eager-loading applicant profiles that are never rendered.
 *
 * @mixin MultiKolabRoleApplication
 */
class MultiKolabRoleApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'multi_kolab_role_id' => $this->multi_kolab_role_id,
            'applicant_profile_id' => $this->applicant_profile_id,
            'applicant_profile_type' => $this->applicant_profile_type,
            'status' => $this->status->value,
            'pitch' => $this->pitch,
            'availability' => $this->availability,
            'kolab_id' => $this->kolab_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
