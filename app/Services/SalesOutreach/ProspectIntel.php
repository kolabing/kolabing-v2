<?php

declare(strict_types=1);

namespace App\Services\SalesOutreach;

use App\Models\Profile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Everything we can learn about a business before pitching it (BE-NF-67).
 *
 * Three sources, in descending order of how much they are worth:
 *
 * 1. **What we already hold.** `business_profiles.primary_venue` carries the Google
 *    rating, the review count, the opening hours, the price level and the place
 *    types — captured at import and, until now, used for nothing. A business with
 *    4.2 from 418 reviews cares about reviews; a business whose hours we know has a
 *    quietest shift we can name. This costs nothing and is the single biggest lift.
 * 2. **Their own website.** Free, legitimate, and often says outright whether they
 *    already host events.
 * 3. **The weather where the venue is.** Open-Meteo, no key required. A rainy
 *    weekend is the most concrete reason a terrace owner has to want an indoor plan.
 *
 * Every source is optional and failure is never fatal: a pitch built from two
 * sources is still a pitch, and an unreachable website must not cost a maintainer
 * their click. What is gathered gets stored on the draft, because a claim made to a
 * real business has to stay explainable after the underlying page changes.
 *
 * Instagram is deliberately absent. The official Graph API needs the business to
 * authorise us, and scraping the public site breaks Meta's terms and would rot
 * within weeks — a data source that silently stops working is worse than one we
 * never had.
 */
class ProspectIntel
{
    /**
     * @return array<string, mixed>
     */
    public function gather(Profile $business): array
    {
        $intel = ['places' => $this->fromStoredPlaces($business)];

        if ((bool) config('sales_outreach.intel.website')) {
            $website = $business->businessProfile?->website
                ?? ($business->businessProfile?->primary_venue['website'] ?? null);

            $site = $this->fromWebsite(is_string($website) ? $website : null);

            if ($site !== null) {
                $intel['website'] = $site;
            }
        }

        if ((bool) config('sales_outreach.intel.weather')) {
            $venue = $business->businessProfile?->primary_venue;
            $weather = is_array($venue)
                ? $this->fromWeather($venue['latitude'] ?? null, $venue['longitude'] ?? null)
                : null;

            if ($weather !== null) {
                $intel['weather'] = $weather;
            }
        }

        return $intel;
    }

    /**
     * The Google data already sitting in `primary_venue`. No request, no cost.
     *
     * @return array<string, mixed>
     */
    private function fromStoredPlaces(Profile $business): array
    {
        $venue = $business->businessProfile?->primary_venue;
        $venue = is_array($venue) ? $venue : [];

        return array_filter([
            'google_rating' => $venue['rating'] ?? null,
            'google_review_count' => $venue['user_ratings_total'] ?? null,
            'price_level' => $venue['price_level'] ?? null,
            'opening_hours' => is_array($venue['opening_hours'] ?? null) ? $venue['opening_hours'] : null,
            'google_place_types' => is_array($venue['google_place_types'] ?? null) ? $venue['google_place_types'] : null,
            'phone_number' => $venue['phone_number'] ?? null,
        ], fn ($value): bool => filled($value));
    }

    /**
     * Read the business's own site.
     *
     * @return array<string, mixed>|null
     */
    private function fromWebsite(?string $url): ?array
    {
        if ($url === null || ! $this->isFetchable($url)) {
            return null;
        }

        try {
            $response = Http::timeout((int) config('sales_outreach.intel.website_timeout', 8))
                ->withHeaders(['User-Agent' => 'KolabingBot/1.0 (+https://kolabing.com)'])
                ->withOptions(['allow_redirects' => ['max' => 3, 'strict' => true]])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $html = $response->body();

            // Cap before parsing: a multi-megabyte page is not worth the memory, and
            // everything that matters on a small business's homepage is near the top.
            $html = mb_substr($html, 0, 400_000);

            $text = $this->visibleText($html);

            if ($text === '') {
                return null;
            }

            return array_filter([
                'url' => $url,
                'title' => $this->tag($html, 'title'),
                'description' => $this->metaDescription($html),
                // Truncated hard: the model needs a sense of the place, not the site.
                'text_excerpt' => Str::limit($text, 1500),
            ], fn ($value): bool => filled($value));
        } catch (Throwable $e) {
            Log::info('Prospect website could not be read', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * The forecast where the venue actually is, for the coming week.
     *
     * Open-Meteo needs no key and no account. A week is the useful horizon: the
     * pitch talks about "this weekend", and a forecast further out than that is
     * not something to put in front of a business owner as a reason to act.
     *
     * @return array<string, mixed>|null
     */
    private function fromWeather(mixed $latitude, mixed $longitude): ?array
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        try {
            $response = Http::timeout((int) config('sales_outreach.intel.weather_timeout', 8))
                ->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude' => (float) $latitude,
                    'longitude' => (float) $longitude,
                    'daily' => 'weather_code,temperature_2m_max,precipitation_probability_max',
                    'forecast_days' => 7,
                    'timezone' => 'auto',
                ]);

            if (! $response->successful()) {
                return null;
            }

            $daily = $response->json('daily');

            if (! is_array($daily) || ! isset($daily['time'])) {
                return null;
            }

            $days = [];

            foreach ((array) $daily['time'] as $i => $date) {
                $days[] = array_filter([
                    'date' => $date,
                    'max_temp_c' => $daily['temperature_2m_max'][$i] ?? null,
                    'rain_probability_pct' => $daily['precipitation_probability_max'][$i] ?? null,
                ], fn ($value): bool => $value !== null);
            }

            return $days === [] ? null : ['next_7_days' => $days];
        } catch (Throwable $e) {
            Log::info('Weather lookup failed for a prospect', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Refuse anything that is not a public http(s) address.
     *
     * `website` is user-supplied and we are about to make our server fetch it, which
     * is the textbook shape of an SSRF. A business that typed `http://127.0.0.1:6379`
     * or an AWS metadata address must not get our server to go and look. The URL rule
     * on the way in does not stop any of those — it only checks the shape.
     */
    private function isFetchable(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = $parts['host'];

        // Resolve first: a public-looking hostname can still point at a private
        // address, which is the whole trick.
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }

    private function visibleText(string $html): string
    {
        $stripped = preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $text = strip_tags($stripped);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function tag(string $html, string $tag): ?string
    {
        return preg_match("#<{$tag}[^>]*>(.*?)</{$tag}>#is", $html, $m) === 1
            ? trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            : null;
    }

    private function metaDescription(string $html): ?string
    {
        return preg_match('#<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\']#i', $html, $m) === 1
            ? trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            : null;
    }
}
