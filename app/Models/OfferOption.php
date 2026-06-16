<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An admin-managed option in one of the kolab offer taxonomies, keyed by `kind`:
 * 'offering' (what a business offers), 'deliverable' (what's offered in return —
 * community offers_in_return / business expects), 'need' (community asks),
 * 'product_type' (product-promotion picker + product onboarding), or 'venue_type'
 * (venue onboarding + venue-promotion). Source of truth the app reads via
 * /lookup/{offerings,deliverables,needs,product-types,venue-types}.
 *
 * @property string $id
 * @property string $kind
 * @property string $name
 * @property string $slug
 * @property string|null $icon
 * @property string|null $icon_url
 * @property int $sort_order
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class OfferOption extends Model
{
    use HasFactory;
    use HasUuids;

    public const KIND_OFFERING = 'offering';

    public const KIND_DELIVERABLE = 'deliverable';

    public const KIND_NEED = 'need';

    public const KIND_PRODUCT_TYPE = 'product_type';

    public const KIND_VENUE_TYPE = 'venue_type';

    /** @var list<string> */
    public const KINDS = [
        self::KIND_OFFERING,
        self::KIND_DELIVERABLE,
        self::KIND_NEED,
        self::KIND_PRODUCT_TYPE,
        self::KIND_VENUE_TYPE,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kind',
        'name',
        'slug',
        'icon',
        'icon_url',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Active slugs for a given kind — used by CreateKolabRequest/UpdateKolabRequest
     * to validate payloads against the DB-backed taxonomy.
     *
     * @return list<string>
     */
    public static function activeSlugs(string $kind): array
    {
        return self::query()
            ->where('kind', $kind)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('slug')
            ->all();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<OfferOption>  $query
     * @return \Illuminate\Database\Eloquent\Builder<OfferOption>
     */
    public function scopeKind($query, string $kind)
    {
        return $query->where('kind', $kind);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<OfferOption>  $query
     * @return \Illuminate\Database\Eloquent\Builder<OfferOption>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<OfferOption>  $query
     * @return \Illuminate\Database\Eloquent\Builder<OfferOption>
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
