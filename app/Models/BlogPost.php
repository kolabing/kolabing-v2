<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A public marketing / SEO blog post (Community Commerce content) served at
 * /blog and /blog/{slug}. Route-bound by slug; only published posts are public
 * (see scopePublished + isPublished). Draft = published_at null or in the future.
 */
class BlogPost extends Model
{
    /** @use HasFactory<\Database\Factories\BlogPostFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'slug', 'title', 'description', 'body', 'faq',
        'author_name', 'author_title', 'cover_image_url',
        'locale', 'published_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'faq' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The FAQ pairs that are safe to render: rows with both a question and an
     * answer. One list feeds the visible FAQ section and the FAQPage JSON-LD.
     *
     * @return list<array{question: string, answer: string}>
     */
    public function faqPairs(): array
    {
        return collect($this->faq ?? [])
            ->filter(fn ($pair) => is_array($pair)
                && filled($pair['question'] ?? null)
                && filled($pair['answer'] ?? null))
            ->map(fn (array $pair) => [
                'question' => (string) $pair['question'],
                'answer' => (string) $pair['answer'],
            ])
            ->values()
            ->all();
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    /**
     * @param  Builder<BlogPost>  $query
     * @return Builder<BlogPost>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
