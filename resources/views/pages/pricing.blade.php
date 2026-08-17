@php
    $title = 'Pricing';
    $description = 'Kolabing Business pricing: €'.(int) config('subscriptions.business.stripe.monthly.price').' per month to publish unlimited Kolabs and partner with local communities. Communities always use Kolabing for free.';
    $canonical = route('pricing');
    $alternates = [
        ['hreflang' => 'en', 'href' => route('pricing')],
        ['hreflang' => 'es', 'href' => route('pricing.es')],
        ['hreflang' => 'x-default', 'href' => route('pricing')],
    ];

    $c = [
        'eyebrow' => 'Pricing',
        'headline' => 'One plan. Unlimited local partnerships.',
        'intro' => 'Kolabing Business is a single monthly subscription — publish as many Kolabs as you want, see who applies, and run the collaborations that fill your quiet hours. Communities never pay.',
        'monthly_name' => 'Monthly',
        'quarterly_name' => '3 months',
        'per_month' => '/ month',
        'monthly_note' => 'Billed monthly · cancel anytime',
        'quarterly_note' => ':price billed every 3 months',
        'save_badge' => 'Save :percent%',
        'cta' => 'Start now',
        'login' => 'Already have an account? Log in',
        'included_title' => "What's included",
        'included' => [
            'Publish unlimited Kolabs',
            'See community names, profiles and audience size',
            'Receive and accept applications',
            'Apply to community requests yourself',
            'Chat with your collaborators',
            'Cancel anytime, no contract',
        ],
        'communities_title' => 'Communities never pay',
        'communities_desc' => 'Clubs, teams, creators and organizers browse, apply and collaborate for free. There is no community plan to buy — if someone asks you to pay to organize on Kolabing, that is not us.',
        'communities_cta' => 'Create a free community account',
        'faq_title' => 'Questions',
        'final_title' => 'Launch your first Kolab this week',
        'final_desc' => 'Create your business account in the browser, pick a plan, and post your first collaboration in minutes. No app needed.',
    ];

    $faqs = [
        ['q' => 'What happens after I pay?', 'a' => 'Your plan activates immediately and you land back on Kolabing ready to publish. Payment is handled by Stripe — we never see your card details.'],
        ['q' => 'Can I cancel?', 'a' => 'Yes, any time, from the billing portal inside your account. Your plan stays active until the end of the period you already paid for.'],
        ['q' => 'Do communities pay anything?', 'a' => 'No. Community accounts are free forever — browsing, applying and collaborating all cost nothing.'],
        ['q' => 'Can I pay for 3 months at once?', 'a' => 'Yes. The 3-month plan bills once every quarter and works out cheaper per month than paying monthly.'],
        ['q' => 'Do you offer discounts?', 'a' => 'We run campaigns from time to time. If you have a promotion code you can enter it on the Stripe checkout screen before paying.'],
        ['q' => 'Can I use Kolabing on my phone?', 'a' => 'Yes. Everything works in the browser, and the Kolabing app adds chat, notifications and event check-ins.'],
    ];
@endphp

<x-layouts.marketing-page :title="$title" :description="$description" :canonical="$canonical" :alternates="$alternates">
    <x-slot:head>
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => 'Kolabing Business',
                'description' => $description,
                'brand' => ['@type' => 'Brand', 'name' => 'Kolabing'],
                'url' => $canonical,
                'offers' => [
                    [
                        '@type' => 'Offer',
                        'name' => 'Monthly',
                        'price' => (string) (int) config('subscriptions.business.stripe.monthly.price'),
                        'priceCurrency' => 'EUR',
                        'availability' => 'https://schema.org/InStock',
                        'url' => $canonical,
                    ],
                    [
                        '@type' => 'Offer',
                        'name' => '3 months',
                        'price' => (string) (int) config('subscriptions.business.stripe.three_months.price'),
                        'priceCurrency' => 'EUR',
                        'availability' => 'https://schema.org/InStock',
                        'url' => $canonical,
                    ],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(static fn (array $faq): array => [
                    '@type' => 'Question',
                    'name' => $faq['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a']],
                ], $faqs),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    </x-slot:head>

    @include('pages.partials.pricing-content', ['c' => $c, 'faqs' => $faqs])
</x-layouts.marketing-page>
