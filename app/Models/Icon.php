<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * An entry in the admin-managed personalised-icon library. The SVG file lives on the
 * public disk under `category-icons/`; `url` is derived from `filename` so it is never
 * stale. `source` is 'bundled' (seeded from the mobile app's bundled category SVGs) or
 * 'uploaded' (admin upload). Any taxonomy (offer_options, ...) picks one and stores its
 * public `url`, which the app's CategoryIcon widget renders with top priority.
 *
 * @property string $id
 * @property string $label
 * @property string $slug
 * @property string $filename
 * @property string $source
 * @property bool $is_active
 * @property int $sort_order
 * @property string $url
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Icon extends Model
{
    use HasFactory;
    use HasUuids;

    public const SOURCE_BUNDLED = 'bundled';

    public const SOURCE_UPLOADED = 'uploaded';

    /** Public-disk directory the library SVGs live in. */
    public const DIRECTORY = 'category-icons';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'label',
        'slug',
        'filename',
        'source',
        'is_active',
        'sort_order',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['url'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Public URL of the SVG, derived from the filename on the public disk.
     *
     * @return Attribute<string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::get(
            fn (): string => Storage::disk('public')->url(self::DIRECTORY.'/'.$this->filename),
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Icon>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Icon>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Icon>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Icon>
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('label');
    }
}
