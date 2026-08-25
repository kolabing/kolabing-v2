@php

    $title = 'Precios';
    $description = 'Precios de Kolabing Business: €'.(int) config('subscriptions.business.stripe.monthly.price').' al mes para publicar Kolabs ilimitados y colaborar con comunidades locales. Las comunidades usan Kolabing siempre gratis.';
    $canonical = route('pricing.es');
    $locale = 'es';
    $alternates = [
        ['hreflang' => 'en', 'href' => route('pricing')],
        ['hreflang' => 'es', 'href' => route('pricing.es')],
        ['hreflang' => 'x-default', 'href' => route('pricing')],
    ];

    $c = [
        'eyebrow' => 'Precios',
        'headline' => 'Un plan. Colaboraciones locales ilimitadas.',
        'intro' => 'Kolabing Business es una única suscripción mensual: publica todos los Kolabs que quieras, mira quién aplica y organiza las colaboraciones que llenan tus horas flojas. Las comunidades nunca pagan.',
        'monthly_name' => 'Mensual',
        'quarterly_name' => '3 meses',
        'per_month' => '/ mes',
        'monthly_note' => 'Cobro mensual · cancela cuando quieras',
        'quarterly_note' => ':price cobrados cada 3 meses',
        'save_badge' => 'Ahorra :percent%',
        'cta' => 'Empezar ahora',
        'login' => '¿Ya tienes cuenta? Inicia sesión',
        'included_title' => 'Qué incluye',
        'included' => [
            'Publica Kolabs ilimitados',
            'Ve nombres, perfiles y tamaño de audiencia de las comunidades',
            'Recibe y acepta solicitudes',
            'Aplica tú mismo a peticiones de comunidades',
            'Chatea con tus colaboradores',
            'Cancela cuando quieras, sin permanencia',
        ],
        'communities_title' => 'Las comunidades nunca pagan',
        'communities_desc' => 'Clubes, equipos, creadores y organizadores exploran, aplican y colaboran gratis. No hay ningún plan de comunidad que comprar — si alguien te pide pagar por organizar en Kolabing, no somos nosotros.',
        'communities_cta' => 'Crear una cuenta de comunidad gratis',
        'faq_title' => 'Preguntas',
        'final_title' => 'Lanza tu primer Kolab esta semana',
        'final_desc' => 'Crea tu cuenta de negocio en el navegador, elige un plan y publica tu primera colaboración en minutos. Sin instalar nada.',
    ];

    $faqs = [
        ['q' => '¿Qué pasa después de pagar?', 'a' => 'Tu plan se activa al momento y vuelves a Kolabing listo para publicar. El pago lo gestiona Stripe — nosotros nunca vemos los datos de tu tarjeta.'],
        ['q' => '¿Puedo cancelar?', 'a' => 'Sí, cuando quieras, desde el portal de facturación dentro de tu cuenta. El plan sigue activo hasta el final del periodo que ya has pagado.'],
        ['q' => '¿Las comunidades pagan algo?', 'a' => 'No. Las cuentas de comunidad son gratis para siempre: explorar, aplicar y colaborar no cuesta nada.'],
        ['q' => '¿Puedo pagar 3 meses de una vez?', 'a' => 'Sí. El plan de 3 meses se cobra una vez por trimestre y sale más barato por mes que el pago mensual.'],
        ['q' => '¿Tenéis descuentos?', 'a' => 'Hacemos campañas de vez en cuando. Si tienes un código promocional, puedes introducirlo en la pantalla de pago de Stripe antes de pagar.'],
        ['q' => '¿Puedo usar Kolabing desde el móvil?', 'a' => 'Sí. Todo funciona en el navegador, y la app de Kolabing añade chat, notificaciones y check-in en eventos.'],
    ];

    /**
     * JSON-LD is built here, NOT inline in the <script> tag. Blade compiles
     * directives inside `{!! !!}` expressions, and Laravel 12 has an `@context`
     * directive — so a literal '@context' key written there is replaced by compiled
     * PHP and the emitted structured data loses its @context entirely. Inside a
     * @php block the compiler leaves it alone. See PublicProfilePageTest /
     * MarketingSeoTest for the guard.
     */
    $productSchema = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => 'Kolabing Business',
        'description' => $description,
        'brand' => ['@type' => 'Brand', 'name' => 'Kolabing'],
        'url' => $canonical,
        'offers' => [
            [
                '@type' => 'Offer',
                'name' => 'Mensual',
                'price' => (string) (int) config('subscriptions.business.stripe.monthly.price'),
                'priceCurrency' => 'EUR',
                'availability' => 'https://schema.org/InStock',
                'url' => $canonical,
            ],
            [
                '@type' => 'Offer',
                'name' => '3 meses',
                'price' => (string) (int) config('subscriptions.business.stripe.three_months.price'),
                'priceCurrency' => 'EUR',
                'availability' => 'https://schema.org/InStock',
                'url' => $canonical,
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $faqSchema = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'inLanguage' => 'es',
        'mainEntity' => array_map(static fn (array $faq): array => [
            '@type' => 'Question',
            'name' => $faq['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a']],
        ], $faqs),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@endphp

<x-layouts.marketing-page :title="$title" :description="$description" :canonical="$canonical" :locale="$locale" :alternates="$alternates">
    <x-slot:head>
        <script type="application/ld+json">
            {!! $productSchema !!}
        </script>
        <script type="application/ld+json">
            {!! $faqSchema !!}
        </script>
    </x-slot:head>

    @include('pages.partials.pricing-content', ['c' => $c, 'faqs' => $faqs])
</x-layouts.marketing-page>
