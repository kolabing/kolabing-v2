@php
    $monthly = (int) config('subscriptions.business.stripe.monthly.price');
    $quarterly = (int) config('subscriptions.business.stripe.three_months.price');
    $pro = (int) config('subscriptions.business.stripe.pro_monthly.price');

    $title = 'Pricing for businesses';
    $description = 'List your venue on Kolabing for free. Receiving applications and running kolabs cost nothing. After your free listing, stay listed for €'.$monthly.' a month or €'.$quarterly.' for 3 months. Communities never pay.';
    $canonical = route('pricing');
    $locale = 'en';
    $alternates = [
        ['hreflang' => 'en', 'href' => route('pricing')],
        ['hreflang' => 'es', 'href' => route('pricing.es')],
        ['hreflang' => 'ca', 'href' => route('pricing.ca')],
        ['hreflang' => 'x-default', 'href' => route('pricing')],
    ];

    $c = [
        'eyebrow' => 'Pricing',
        'headline' => 'List free. Pay only to stay listed.',
        'intro' => 'Every business gets a free listing in Kolabing Explore the moment it signs up, so local communities can find you and bring their members on your quiet nights. Receiving applications, accepting them and running kolabs are free. Communities never pay.',
        'free_badge' => 'Free listing',
        'free_price_note' => 'to start',
        'free_title' => 'Start with a free listing',
        'free_desc' => 'Your free listing lasts until you complete 3 kolabs or for 90 days, whichever comes first.',
        'free_items' => [
            'Your venue listed in Explore as soon as you sign up',
            'Receive and accept applications from communities',
            'Run your kolabs and chat with the communities you accept',
            'Your public profile page on kolabing.com',
        ],
        'free_cta' => 'List your venue free',
        'paid_title' => 'Stay listed after your free period',
        'paid_desc' => 'When your free listing ends, choose a plan to stay in Explore and keep receiving applications.',
        'monthly_name' => 'Monthly',
        'quarterly_name' => '3 months',
        'per_month' => '/ month',
        'monthly_note' => 'Billed monthly · cancel anytime',
        'quarterly_note' => ':price billed every 3 months',
        'save_badge' => 'Save :percent%',
        'cta' => 'Choose this plan',
        'pro_name' => 'Venue Pro',
        'pro_badge' => 'For hotels',
        'pro_note' => 'Billed monthly · web only',
        'pro_desc' => 'For hotels and venues that host events every week.',
        'pro_cta' => 'Choose Venue Pro',
        'login' => 'Already have an account? Log in',
        'included_title' => 'Every paid plan includes',
        'included' => [
            'Stay listed in Explore',
            'Publish more kolabs',
            'See community names, profiles and audience size',
            'Apply to community requests yourself',
            'Cancel anytime, no contract',
        ],
        'communities_title' => 'Communities never pay',
        'communities_desc' => 'Clubs, teams, creators and organizers browse, apply and collaborate for free. There is no community plan to buy. If someone asks you to pay to organize on Kolabing, that is not us.',
        'communities_cta' => 'Create a free community account',
        'faq_title' => 'Questions',
        'final_title' => 'Get listed this week',
        'final_desc' => 'Create your business account in the browser and your venue goes live in Explore for free. No app needed.',
    ];

    $faqs = [
        ['q' => 'What is free?', 'a' => 'Listing your venue, receiving and accepting applications, and running your kolabs. Every new business gets a listing in Explore automatically, and you can edit or close it at any time.'],
        ['q' => 'When does the free listing end?', 'a' => 'After your 3rd completed kolab or 90 days after you sign up, whichever comes first. To stay listed after that, choose the monthly plan (€'.$monthly.') or the 3-month plan (€'.$quarterly.').'],
        ['q' => 'What is Venue Pro?', 'a' => 'A €'.$pro.' monthly plan for hotels and venues that host events every week. It adds revenue and attendance for every event, your best communities ranked, and CSV export. It is sold on the web only.'],
        ['q' => 'What happens after I pay?', 'a' => 'Your plan activates immediately and you land back on Kolabing ready to publish. Payment is handled by Stripe, and we never see your card details.'],
        ['q' => 'Can I cancel?', 'a' => 'Yes, any time, from the billing portal inside your account. Your plan stays active until the end of the period you already paid for.'],
        ['q' => 'Do communities pay anything?', 'a' => 'No. Community accounts are free forever: browsing, applying and collaborating all cost nothing.'],
        ['q' => 'Do you offer discounts?', 'a' => 'We run campaigns from time to time. If you have a promotion code you can enter it on the Stripe checkout screen before paying.'],
        ['q' => 'Can I use Kolabing on my phone?', 'a' => 'Yes. Everything works in the browser, and the Kolabing app adds chat, notifications and event check-ins.'],
    ];
@endphp

<x-layouts.marketing-page :title="$title" :description="$description" :canonical="$canonical" :alternates="$alternates">
    <x-slot:head>
        @include('pages.partials.pricing-schema', ['description' => $description, 'canonical' => $canonical, 'locale' => $locale, 'c' => $c, 'faqs' => $faqs])
    </x-slot:head>

    @include('pages.partials.pricing-content', ['c' => $c, 'faqs' => $faqs, 'locale' => $locale])
</x-layouts.marketing-page>
