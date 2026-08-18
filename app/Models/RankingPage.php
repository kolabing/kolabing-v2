<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Editorial copy for one public ranking page (a city hub or a city×topic page).
 * The ranked LIST itself is a live projection of CrmAccount (see DirectoryController);
 * this model only holds the curated marketing copy so it can be edited without
 * touching the community leads.
 *
 * @property array<int, array{q: string, a: string}>|null $faq
 */
class RankingPage extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'city', 'topic', 'slug', 'title', 'meta_description',
        'intro', 'how_ranked', 'faq', 'editor_name', 'published', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'faq' => 'array',
            'published' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isHub(): bool
    {
        return $this->topic === null;
    }

    /**
     * @param  Builder<RankingPage>  $query
     * @return Builder<RankingPage>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true);
    }
}
