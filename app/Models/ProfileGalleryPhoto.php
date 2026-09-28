<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $profile_id
 * @property string $url
 * @property string|null $caption
 * @property int $sort_order
 * @property string $media_type image|video — for a video, `url` is the poster frame
 * @property string|null $video_url
 * @property string|null $instagram_media_id
 * @property string|null $instagram_source_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 */
class ProfileGalleryPhoto extends Model
{
    /** @use HasFactory<\Database\Factories\ProfileGalleryPhotoFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'profile_id',
        'url',
        'caption',
        'sort_order',
        'media_type',
        'video_url',
        'instagram_media_id',
        'instagram_source_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'media_type' => 'image',
    ];

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
