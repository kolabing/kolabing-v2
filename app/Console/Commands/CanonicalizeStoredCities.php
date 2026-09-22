<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BusinessProfile;
use App\Models\Kolab;
use App\Models\MultiKolabEvent;
use App\Services\CityResolver;
use Illuminate\Console\Command;

/**
 * Rewrites the free-text city columns to the canonical `cities.name`.
 *
 * Rows written before BE-FX-56 hold whatever Google Places returned as the
 * venue's `locality` — "Ciudad de México", or in a metro area the borough
 * ("Cuajimalpa de Morelos") — while the picker and `GET /api/v1/cities` offer
 * "Mexico City". The filter compared the two literally, so those listings were
 * invisible in Explore. New writes are canonicalized at the service layer; this
 * brings the existing rows in line and fills the `city_id` they never got.
 */
class CanonicalizeStoredCities extends Command
{
    protected $signature = 'cities:canonicalize {--apply : Write the changes (without this flag the command only reports)}';

    protected $description = 'Rewrite free-text city columns (kolabs.preferred_city, business_profiles.city_name/city_id, multi_kolab_events.city) to the canonical cities.name.';

    public function handle(CityResolver $cityResolver): int
    {
        $apply = (bool) $this->option('apply');
        $changed = 0;

        $this->info($apply ? 'Canonicalizing stored cities…' : 'Dry run — nothing will be written. Pass --apply to commit.');

        $changed += $this->canonicalizeKolabs($cityResolver, $apply);
        $changed += $this->canonicalizeBusinessProfiles($cityResolver, $apply);
        $changed += $this->canonicalizeMultiKolabEvents($cityResolver, $apply);

        $this->newLine();
        $this->info($apply
            ? "Done. {$changed} row(s) updated."
            : "Done. {$changed} row(s) would be updated.");

        return self::SUCCESS;
    }

    private function canonicalizeKolabs(CityResolver $cityResolver, bool $apply): int
    {
        $changed = 0;

        Kolab::query()
            ->whereNotNull('preferred_city')
            ->chunkById(200, function ($kolabs) use ($cityResolver, $apply, &$changed): void {
                foreach ($kolabs as $kolab) {
                    $canonical = $cityResolver->canonicalFor($kolab->preferred_city);

                    if ($canonical === null || $canonical === $kolab->preferred_city) {
                        continue;
                    }

                    $this->line("  kolab {$kolab->id}: '{$kolab->preferred_city}' → '{$canonical}'");
                    $changed++;

                    if ($apply) {
                        $kolab->forceFill(['preferred_city' => $canonical])->saveQuietly();
                    }
                }
            });

        return $changed;
    }

    private function canonicalizeBusinessProfiles(CityResolver $cityResolver, bool $apply): int
    {
        $changed = 0;

        BusinessProfile::query()
            ->whereNotNull('city_name')
            ->chunkById(200, function ($profiles) use ($cityResolver, $apply, &$changed): void {
                foreach ($profiles as $profile) {
                    $city = $cityResolver->resolveByName($profile->city_name);

                    if ($city === null) {
                        continue;
                    }

                    $needsName = $profile->city_name !== $city->name;
                    $needsId = $profile->city_id !== $city->id;
                    $needsCountry = $profile->city_country !== $city->country;

                    if (! $needsName && ! $needsId && ! $needsCountry) {
                        continue;
                    }

                    $this->line("  business_profile {$profile->profile_id}: '{$profile->city_name}' → '{$city->name}' (city_id ".($profile->city_id ?? 'null')." → {$city->id})");
                    $changed++;

                    if ($apply) {
                        $profile->forceFill([
                            'city_id' => $city->id,
                            'city_name' => $city->name,
                            'city_country' => $city->country,
                        ])->saveQuietly();
                    }
                }
            });

        return $changed;
    }

    private function canonicalizeMultiKolabEvents(CityResolver $cityResolver, bool $apply): int
    {
        $changed = 0;

        MultiKolabEvent::query()
            ->whereNotNull('city')
            ->chunkById(200, function ($events) use ($cityResolver, $apply, &$changed): void {
                foreach ($events as $event) {
                    $canonical = $cityResolver->canonicalFor($event->city);

                    if ($canonical === null || $canonical === $event->city) {
                        continue;
                    }

                    $this->line("  multi_kolab_event {$event->id}: '{$event->city}' → '{$canonical}'");
                    $changed++;

                    if ($apply) {
                        $event->forceFill(['city' => $canonical])->saveQuietly();
                    }
                }
            });

        return $changed;
    }
}
