@php
    $title = 'Kolabing — Local Business & Community Collaboration Platform';
    $description = 'Kolabing helps local businesses and community groups launch partnerships that create foot traffic, member value, and repeatable neighborhood growth.';
    $canonical = route('home');
    $socialImage = url('/social-preview.svg');
    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => $canonical.'#organization',
                'name' => 'Kolabing',
                'url' => $canonical,
                'logo' => url('/brand/logo-mark.svg'),
                'email' => 'support@kolabing.com',
            ],
            [
                '@type' => 'WebSite',
                '@id' => $canonical.'#website',
                'name' => 'Kolabing',
                'url' => $canonical,
                'description' => $description,
                'publisher' => ['@id' => $canonical.'#organization'],
            ],
            [
                '@type' => 'SoftwareApplication',
                '@id' => $canonical.'#app',
                'name' => 'Kolabing',
                'operatingSystem' => 'iOS, Android',
                'applicationCategory' => 'BusinessApplication',
                'description' => $description,
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '0',
                    'priceCurrency' => 'EUR',
                ],
            ],
        ],
    ];
@endphp
<!DOCTYPE html>
<html class="scroll-smooth" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
    <meta name="theme-color" content="#0D1216">
    <meta name="apple-mobile-web-app-title" content="Kolabing">
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Kolabing">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $socialImage }}">
    <meta property="og:image:alt" content="Kolabing local business and community collaboration platform">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $socialImage }}">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/brand/logo-mark.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Montserrat:ital,wght@0,900;1,900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries,typography"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: "#FFD560",
                        "off-black": "#1B1F1C",
                        "off-white": "#FDFBF7",
                    },
                    fontFamily: {
                        sans: ["DM Sans", "sans-serif"],
                        montserrat: ["Montserrat", "sans-serif"],
                    },
                    borderRadius: {
                        "4xl": "2.5rem",
                        "5xl": "3.5rem",
                    },
                },
            },
        };
    </script>
    <style type="text/tailwindcss">
        @layer base {
            body {
                font-family: "DM Sans", sans-serif;
                background-color: #FDFBF7;
                color: #1B1F1C;
                line-height: 1.6;
                letter-spacing: 0.01em;
            }
        }
        .hero-video-container {
            position: relative;
            width: 100%;
            min-height: 92svh;
            overflow: hidden;
        }
        .hero-video {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .dot-pattern {
            background-image: radial-gradient(#1B1F1C 1px, transparent 1px);
            background-size: 28px 28px;
        }
    </style>
</head>
<body class="bg-off-white text-off-black">
<header class="fixed top-0 z-50 w-full border-b border-white/10 bg-off-black/60 px-4 py-4 text-white backdrop-blur md:px-8">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-6">
        <a href="{{ route('home') }}" class="flex items-center gap-2">
            <span class="rounded-2xl bg-off-black px-3 py-2 shadow-lg shadow-primary/20">
                <img src="/brand/logo-wordmark.svg" alt="Kolabing" width="1200" height="260" class="h-7 w-auto md:h-8">
            </span>
        </a>
        <nav class="hidden items-center gap-8 text-sm font-medium md:flex">
            <a class="hover:text-primary transition-colors" href="#how-it-works">How it works</a>
            <a class="hover:text-primary transition-colors" href="{{ route('for-businesses') }}">Businesses</a>
            <a class="hover:text-primary transition-colors" href="{{ route('for-communities') }}">Communities</a>
            <a class="hover:text-primary transition-colors" href="{{ route('support') }}">Support</a>
            <a class="rounded-full bg-primary px-6 py-2.5 text-off-black hover:shadow-lg transition-all" href="#get-started">Explore Kolabing</a>
        </nav>
    </div>
</header>

<section class="hero-video-container">
    <video class="hero-video brightness-[0.68] contrast-[1.05]" autoplay muted loop playsinline preload="metadata">
        <source src="/assets/hero3.mp4" type="video/mp4">
    </video>

    <div class="absolute inset-0 bg-gradient-to-b from-off-black/70 via-off-black/40 to-off-black/75">
        <div class="mx-auto flex min-h-[92svh] max-w-7xl flex-col justify-center px-6 pb-20 pt-32 text-white">
            <div class="mb-6 inline-flex w-fit rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-xs font-bold uppercase tracking-[0.24em]">
                Local business and community growth
            </div>
            <h1 class="max-w-5xl font-montserrat text-4xl font-black uppercase leading-[1.05] tracking-tight sm:text-5xl lg:text-[70px]">
                Your next 30 customers are already in a <span class="text-primary italic">community nearby.</span>
            </h1>
            <p class="mt-6 max-w-3xl text-lg text-white/90 md:text-2xl">
                Kolabing helps local businesses and community groups plan collaborations that create foot traffic, trusted word of mouth, and repeat visits without wasting budget on generic reach.
            </p>
            <p class="mt-4 max-w-3xl text-sm font-medium text-white/75 md:text-base">
                Built for neighborhood brands, clubs, creators, and organizers who want better real-world partnerships in cities like Barcelona and beyond.
            </p>
            <div id="get-started" class="mt-10 flex flex-col gap-4 sm:flex-row">
                <a href="{{ route('for-businesses') }}" class="inline-flex items-center justify-center rounded-2xl bg-primary px-7 py-4 text-base font-bold text-off-black shadow-xl transition-all hover:-translate-y-0.5 hover:shadow-2xl">See how businesses grow</a>
                <a href="{{ route('for-communities') }}" class="inline-flex items-center justify-center rounded-2xl bg-white px-7 py-4 text-base font-bold text-off-black shadow-xl transition-all hover:-translate-y-0.5 hover:shadow-2xl">See how communities partner</a>
                <a href="{{ route('support') }}" class="inline-flex items-center justify-center rounded-2xl border border-white/30 bg-white/10 px-7 py-4 text-base font-bold text-white backdrop-blur transition-all hover:bg-white/15">Talk to Kolabing</a>
            </div>
            <div class="mt-8 grid max-w-4xl gap-4 text-sm font-medium text-white/80 md:grid-cols-3">
                <p class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">Bring niche local audiences into your venue or offer.</p>
                <p class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">Create member value through perks, experiences, and access.</p>
                <p class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">Turn one-off events into repeatable neighborhood momentum.</p>
            </div>
        </div>
    </div>
</section>

<section class="bg-off-white py-16 md:py-24" id="how-it-works">
    <div class="mx-auto max-w-7xl px-6">
        <div class="text-center">
            <p class="text-sm font-bold uppercase tracking-[0.24em] text-off-black/45">How it works</p>
            <h2 class="mt-4 font-montserrat text-3xl font-black uppercase tracking-tight md:text-5xl">Post the opportunity. Match the right partner. Make it happen.</h2>
            <p class="mx-auto mt-5 max-w-3xl text-lg text-off-black/65">Kolabing is designed for practical local growth. Businesses can create partnership opportunities, communities can respond with relevant audiences, and both sides can coordinate around clear outcomes.</p>
        </div>
        <div class="mt-12 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-4xl border-2 border-primary bg-white p-7 shadow-sm">
                <p class="mb-4 text-sm font-black uppercase tracking-[0.2em] text-primary">01</p>
                <h3 class="text-2xl font-bold">Create a collaboration brief</h3>
                <p class="mt-4 text-off-black/65">Describe your event, venue, campaign, timing, and what success looks like so the right partner can self-select quickly.</p>
            </article>
            <article class="rounded-4xl border border-off-black/10 bg-white p-7 shadow-sm">
                <p class="mb-4 text-sm font-black uppercase tracking-[0.2em] text-primary">02</p>
                <h3 class="text-2xl font-bold">Get relevant matches</h3>
                <p class="mt-4 text-off-black/65">Communities and businesses discover each other based on fit, not vanity metrics, so the first conversation starts closer to a real yes.</p>
            </article>
            <article class="rounded-4xl border border-off-black/10 bg-white p-7 shadow-sm">
                <p class="mb-4 text-sm font-black uppercase tracking-[0.2em] text-primary">03</p>
                <h3 class="text-2xl font-bold">Coordinate inside the product</h3>
                <p class="mt-4 text-off-black/65">Use in-app messaging and clear expectations to finalize logistics, attendance details, and the member experience.</p>
            </article>
            <article class="rounded-4xl border border-off-black/10 bg-white p-7 shadow-sm">
                <p class="mb-4 text-sm font-black uppercase tracking-[0.2em] text-primary">04</p>
                <h3 class="text-2xl font-bold">Measure the real-world outcome</h3>
                <p class="mt-4 text-off-black/65">Track attendance, content, and repeat visits so each new collaboration becomes easier to repeat and scale.</p>
            </article>
        </div>
    </div>
</section>

<section class="bg-primary/20 py-16 md:py-24">
    <div class="mx-auto max-w-7xl px-6">
        <div class="grid gap-8 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.24em] text-off-black/45">Why it matters</p>
                <h2 class="mt-4 font-montserrat text-3xl font-black uppercase tracking-tight md:text-5xl">Community marketing works because trust travels locally.</h2>
                <p class="mt-6 max-w-3xl text-lg text-off-black/70">When a local business partners with a real community, the result is more than one event. It creates a recommendation loop: members show up, capture authentic content, talk about the experience, and come back with friends. That kind of growth is harder to fake, more memorable than paid reach, and more useful for neighborhood brands that depend on repeat visits.</p>
                <p class="mt-4 max-w-3xl text-lg text-off-black/70">Kolabing is built for operators who care about long-term local demand, not empty impressions. It gives businesses and communities a cleaner path to partnerships that feel aligned, practical, and measurable.</p>
            </div>
            <div class="grid gap-5 md:grid-cols-3 lg:grid-cols-1">
                <div class="rounded-4xl bg-white p-8 shadow-sm">
                    <span class="material-symbols-outlined text-5xl text-primary">groups</span>
                    <h3 class="mt-5 text-2xl font-bold">Real people in real places</h3>
                    <p class="mt-3 text-off-black/65">Foot traffic from audiences who already gather together offline.</p>
                </div>
                <div class="rounded-4xl bg-white p-8 shadow-sm">
                    <span class="material-symbols-outlined text-5xl text-primary">videocam</span>
                    <h3 class="mt-5 text-2xl font-bold">Authentic user-generated content</h3>
                    <p class="mt-3 text-off-black/65">Photos, videos, and recommendations created by actual participants.</p>
                </div>
                <div class="rounded-4xl bg-white p-8 shadow-sm">
                    <span class="material-symbols-outlined text-5xl text-primary">autorenew</span>
                    <h3 class="mt-5 text-2xl font-bold">Repeat visits and retention</h3>
                    <p class="mt-3 text-off-black/65">Partnerships that can evolve into recurring events, launches, and member perks.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="bg-white py-16 md:py-24">
    <div class="mx-auto max-w-7xl px-6">
        <div class="grid gap-8 md:grid-cols-2">
            <article class="rounded-5xl bg-off-black p-8 text-white md:p-10">
                <p class="text-sm font-bold uppercase tracking-[0.24em] text-primary">For businesses</p>
                <h2 class="mt-4 text-3xl font-bold">Make local discovery feel intentional.</h2>
                <p class="mt-5 text-white/75">Use Kolabing to launch venue activations, product tastings, classes, community meetups, neighborhood campaigns, and loyalty-building experiences that speak to a defined audience.</p>
                <ul class="mt-6 space-y-4 text-white/80">
                    <li>Direct access to niche local demographics that already spend time together.</li>
                    <li>Campaigns that can fill slow days, launch offers, or introduce a new product.</li>
                    <li>Better ROI than broad paid reach when your goal is in-person action.</li>
                </ul>
                <a href="{{ route('for-businesses') }}" class="mt-8 inline-flex rounded-full bg-primary px-6 py-3 font-bold text-off-black">Explore business use cases</a>
            </article>
            <article class="rounded-5xl bg-primary/20 p-8 md:p-10">
                <p class="text-sm font-bold uppercase tracking-[0.24em] text-off-black/45">For communities</p>
                <h2 class="mt-4 text-3xl font-bold">Give members better places, perks, and experiences.</h2>
                <p class="mt-5 text-off-black/70">Community leaders can find aligned business partners that want to host, reward, and grow with their members instead of treating them like anonymous impressions.</p>
                <ul class="mt-6 space-y-4 text-off-black/75">
                    <li>Secure partner venues and sponsor support without cold outreach.</li>
                    <li>Create memorable member experiences that deepen loyalty.</li>
                    <li>Build partnerships that respect your community identity and rhythm.</li>
                </ul>
                <a href="{{ route('for-communities') }}" class="mt-8 inline-flex rounded-full bg-off-black px-6 py-3 font-bold text-white">Explore community use cases</a>
            </article>
        </div>
    </div>
</section>

<section class="bg-off-black py-16 text-white md:py-24">
    <div class="mx-auto max-w-7xl px-6">
        <div class="mb-12 max-w-3xl">
            <p class="text-sm font-bold uppercase tracking-[0.24em] text-primary">What strong collaborations look like</p>
            <h2 class="mt-4 font-montserrat text-3xl font-black uppercase tracking-tight md:text-5xl">Examples businesses and communities can actually launch.</h2>
        </div>
        <div class="grid gap-6 lg:grid-cols-3">
            <article class="rounded-4xl border border-white/10 bg-white/5 p-8">
                <h3 class="text-2xl font-bold">Fitness + cafe partnerships</h3>
                <p class="mt-4 text-white/75">Running clubs, cycling crews, and wellness communities can turn low-traffic hours into recurring meetups with meaningful post-event spend.</p>
            </article>
            <article class="rounded-4xl border border-white/10 bg-white/5 p-8">
                <h3 class="text-2xl font-bold">Retail + creator communities</h3>
                <p class="mt-4 text-white/75">Local retail brands can host launches, styling sessions, or member nights that generate both content and qualified in-store visits.</p>
            </article>
            <article class="rounded-4xl border border-white/10 bg-white/5 p-8">
                <h3 class="text-2xl font-bold">Neighborhood events</h3>
                <p class="mt-4 text-white/75">Studios, venues, and food concepts can co-create gatherings that feel rooted in place and worth repeating month after month.</p>
            </article>
        </div>
    </div>
</section>

<section class="relative overflow-hidden bg-off-white py-16 md:py-24">
    <div class="absolute inset-0 dot-pattern opacity-[0.03]"></div>
    <div class="relative mx-auto max-w-7xl px-6">
        <div class="grid gap-10 lg:grid-cols-[0.9fr_1.1fr]">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.24em] text-off-black/45">Frequently asked questions</p>
                <h2 class="mt-4 font-montserrat text-3xl font-black uppercase tracking-tight md:text-5xl">Questions local teams ask before they launch.</h2>
                <p class="mt-5 text-lg text-off-black/65">We are keeping the experience simple: make the fit obvious, define the offer, and help both sides move from interest to execution quickly.</p>
            </div>
            <div class="space-y-5">
                <article class="rounded-4xl border border-off-black/10 bg-white p-7 shadow-sm">
                    <h3 class="text-xl font-bold">Is Kolabing for one-time activations or recurring partnerships?</h3>
                    <p class="mt-3 text-off-black/65">Both. Many of the best collaborations start with a single event and become recurring because the audience response is easy to see.</p>
                </article>
                <article class="rounded-4xl border border-off-black/10 bg-white p-7 shadow-sm">
                    <h3 class="text-xl font-bold">What kinds of businesses fit best?</h3>
                    <p class="mt-3 text-off-black/65">Neighborhood brands, hospitality concepts, studios, retail spaces, venues, and other operators who benefit from trusted in-person discovery tend to fit especially well.</p>
                </article>
                <article class="rounded-4xl border border-off-black/10 bg-white p-7 shadow-sm">
                    <h3 class="text-xl font-bold">What do communities gain?</h3>
                    <p class="mt-3 text-off-black/65">Member perks, better event spaces, partner-funded experiences, and new ways to grow their presence without losing their identity.</p>
                </article>
            </div>
        </div>
    </div>
</section>

<section class="bg-primary py-16 md:py-20">
    <div class="mx-auto max-w-6xl px-6 text-center text-off-black">
        <p class="text-sm font-bold uppercase tracking-[0.24em] text-off-black/55">Ready to plan better local partnerships?</p>
        <h2 class="mt-4 font-montserrat text-3xl font-black uppercase tracking-tight md:text-5xl">Start with the side that fits you.</h2>
        <p class="mx-auto mt-5 max-w-3xl text-lg text-off-black/70">Kolabing is built for practical growth. Explore the pages below to see how businesses and communities can use the platform, then contact the team for launch and support details.</p>
        <div class="mt-10 flex flex-col justify-center gap-4 sm:flex-row">
            <a href="{{ route('for-businesses') }}" class="rounded-full bg-off-black px-7 py-4 font-bold text-white">For businesses</a>
            <a href="{{ route('for-communities') }}" class="rounded-full bg-white px-7 py-4 font-bold text-off-black">For communities</a>
            <a href="{{ route('support') }}" class="rounded-full border border-off-black/15 bg-primary/70 px-7 py-4 font-bold text-off-black">Support and contact</a>
        </div>
    </div>
</section>

<footer class="bg-off-black py-14 text-white md:py-20">
    <div class="mx-auto max-w-7xl px-8">
        <div class="flex flex-col gap-8 md:flex-row md:items-center md:justify-between">
            <div class="max-w-2xl">
                <img src="/brand/logo-wordmark.svg" alt="Kolabing" width="1200" height="260" class="h-9 w-auto">
                <p class="mt-4 text-sm text-white/65">Kolabing helps local businesses and community groups create neighborhood partnerships that convert attention into attendance, trusted recommendations, and repeat visits.</p>
            </div>
            <div class="flex flex-wrap gap-6 text-sm font-medium text-white/70">
                <a class="hover:text-primary transition-colors" href="{{ route('for-businesses') }}">Businesses</a>
                <a class="hover:text-primary transition-colors" href="{{ route('for-communities') }}">Communities</a>
                <a class="hover:text-primary transition-colors" href="{{ route('support') }}">Support</a>
                <a class="hover:text-primary transition-colors" href="{{ route('careers') }}">Careers</a>
                <a class="hover:text-primary transition-colors" href="{{ route('privacy') }}">Privacy</a>
                <a class="hover:text-primary transition-colors" href="{{ route('terms') }}">Terms</a>
            </div>
        </div>
        <div class="mt-10 border-t border-white/10 pt-8 text-sm text-white/35">
            <p>Contact: <a href="mailto:support@kolabing.com" class="text-white/60 hover:text-primary">support@kolabing.com</a> · Sitemap: <a href="{{ route('sitemap') }}" class="text-white/60 hover:text-primary">/sitemap.xml</a> · AI guidance: <a href="{{ route('llms') }}" class="text-white/60 hover:text-primary">/llms.txt</a></p>
        </div>
    </div>
</footer>
</body>
</html>
