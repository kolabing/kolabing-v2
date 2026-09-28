@php
    /**
     * Product + FAQPage JSON-LD for /pricing, /es/pricing and /ca/pricing.
     *
     * Expects: $description, $canonical, $locale, $c (copy array), $faqs.
     *
     * JSON-LD is built here, NOT inline in the <script> tag. Blade compiles
     * directives inside `{!! !!}` expressions, and Laravel 12 has an `@context`
     * directive — so a literal '@context' key written there is replaced by compiled
     * PHP and the emitted structured data loses its @context entirely. Inside a
     * @php block the compiler leaves it alone. See PublicProfilePageTest /
     * MarketingSeoTest for the guard.
     */
    $offer = static fn (string $name, int $price): array => [
        '@type' => 'Offer',
        'name' => $name,
        'price' => (string) $price,
        'priceCurrency' => 'EUR',
        'availability' => 'https://schema.org/InStock',
        'url' => $canonical,
    ];

    $productSchema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => 'Kolabing Business',
        'description' => $description,
        'brand' => ['@type' => 'Brand', 'name' => 'Kolabing'],
        'url' => $canonical,
        'inLanguage' => $locale === 'en' ? null : $locale,
        'offers' => [
            $offer($c['free_badge'], 0),
            $offer($c['monthly_name'], (int) config('subscriptions.business.stripe.monthly.price')),
            $offer($c['quarterly_name'], (int) config('subscriptions.business.stripe.three_months.price')),
            $offer($c['pro_name'], (int) config('subscriptions.business.stripe.pro_monthly.price')),
        ],
    ], static fn ($value) => $value !== null);

    $faqSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(static fn (array $faq): array => [
            '@type' => 'Question',
            'name' => $faq['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a']],
        ], $faqs),
    ];

    $productJson = json_encode($productSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $faqJson = json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@endphp
<script type="application/ld+json">
    {!! $productJson !!}
</script>
<script type="application/ld+json">
    {!! $faqJson !!}
</script>
