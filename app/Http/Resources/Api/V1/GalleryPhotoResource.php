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
            // Additive (BE-NF-75). `url` is always an image — for a video it is
            // the poster frame — so older clients keep rendering it; the video
            // file is `video_url`. `source` is "instagram" for imported items.
            'media_type' => $this->media_type ?? 'image',
            'video_url' => $this->video_url,
            'source' => $this->instagram_source_id !== null ? 'instagram' : 'upload',
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
