<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Single-row settings model holding the company / legal-entity details that
 * populate the public Terms of Service + Privacy Policy pages, plus the current
 * agreement version + effective date that drive the mobile re-consent gate.
 *
 * @property string $id
 * @property string|null $legal_name
 * @property string|null $registered_address
 * @property string|null $registration_number
 * @property string|null $refund_policy
 * @property string|null $privacy_email
 * @property string|null $support_email
 * @property string|null $terms_version
 * @property Carbon|null $terms_effective_date
 */
class CompanySetting extends Model
{
    use HasUuids;

    protected $table = 'company_settings';

    /** @var list<string> */
    protected $fillable = [
        'legal_name',
        'registered_address',
        'registration_number',
        'refund_policy',
        'privacy_email',
        'support_email',
        'terms_version',
        'terms_effective_date',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'terms_effective_date' => 'date',
        ];
    }

    /**
     * Human-readable effective date for the given page locale (en / es),
     * e.g. "12 July 2026" / "12 de julio de 2026". Falls back to today when unset.
     */
    public function effectiveDateLabel(string $locale = 'en'): string
    {
        $date = $this->terms_effective_date ?? Carbon::now();

        return $locale === 'es'
            ? $date->locale('es')->translatedFormat('j \d\e F \d\e Y')
            : $date->locale('en')->translatedFormat('j F Y');
    }
}
