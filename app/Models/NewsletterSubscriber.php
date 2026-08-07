<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NewsletterAudience;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A pre-signup marketing lead captured from the public landing-page pop-up.
 *
 * @property string $id
 * @property string $email
 * @property NewsletterAudience|null $audience
 * @property string $source
 * @property string|null $locale
 * @property string|null $ip_address
 */
class NewsletterSubscriber extends Model
{
    use HasUuids;

    protected $fillable = [
        'email',
        'audience',
        'source',
        'locale',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience' => NewsletterAudience::class,
        ];
    }
}
