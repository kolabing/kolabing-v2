<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\Cache;

/**
 * Read + write the single company_settings row. Reads are cached because the
 * settings feed the public legal pages and the consent version check on every
 * /auth/me. Cache is busted on admin write.
 *
 * When no row exists yet, current() returns an unsaved defaults object that
 * keeps the `[PLACEHOLDER]` copy on the legal pages and falls the agreement
 * version back to config('legal.terms_version').
 */
class CompanySettingService
{
    public const CACHE_KEY = 'company_settings.current';

    public function current(): CompanySetting
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addHour(),
            fn (): CompanySetting => CompanySetting::query()->first() ?? new CompanySetting($this->defaults()),
        );
    }

    /**
     * Update (or create) the single row, bust the cache, return the fresh state.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): CompanySetting
    {
        $row = CompanySetting::query()->first();

        if ($row === null) {
            $row = CompanySetting::query()->create(array_merge($this->defaults(), $data));
        } else {
            $row->fill($data)->save();
        }

        Cache::forget(self::CACHE_KEY);

        return $row->fresh();
    }

    /**
     * The agreement version currently in effect — the source of truth for the
     * consent + re-consent gate. Falls back to config when unset.
     */
    public function termsVersion(): string
    {
        return (string) ($this->current()->terms_version ?: config('legal.terms_version'));
    }

    /**
     * Placeholder defaults so the legal pages render obviously-unfilled copy
     * until an admin enters the real company details.
     *
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'legal_name' => '[COMPANY NAME]',
            'registered_address' => '[REGISTERED ADDRESS]',
            'registration_number' => '[COMPANY REG NUMBER / NIF]',
            'refund_policy' => '[REFUND POLICY]',
            'privacy_email' => (string) config('legal.contact_email'),
            'support_email' => 'support@kolabing.com',
            'terms_version' => (string) config('legal.terms_version'),
            'terms_effective_date' => (string) config('legal.terms_version'),
        ];
    }
}
