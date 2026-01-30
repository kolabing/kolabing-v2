<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\ProfileGalleryPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProfileGalleryPhoto
 */
class GalleryPhotoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'caption' => $this->caption,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
