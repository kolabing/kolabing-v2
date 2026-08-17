@php
    /**
     * Shared pricing markup for /pricing and /es/pricing. Only the copy differs per
     * locale ($c), so the plan maths and the CTA wiring live here once — a price
     * change is a config edit, not a hunt through per-locale files.
     *
     * Expects: $c (copy array), $faqs (list of ['q' => …, 'a' => …]).
     */
    $monthly = (int) config('subscriptions.business.stripe.monthly.price');
    $quarterly = (int) config('subscriptions.business.stripe.three_months.price');
    $quarterlyPerMonth = (int) round($quarterly / 3);
    $savePercent = $monthly > 0 ? (int) round((1 - ($quarterly / 3) / $monthly) * 100) : 0;

    $appUrl = rtrim(config('webapp.url'), '/');
@endphp

<section class="mx-auto max-w-6xl px-6 py-20">
    <p class="mb-4 text-sm font-bold uppercase tracking-[0.24em] text-off-black/50">{{ $c['eyebrow'] }}</p>
    <h1 class="max-w-4xl font-montserrat text-4xl font-black uppercase leading-tight md:text-6xl">{{ $c['headline'] }}</h1>
    <p class="mt-6 max-w-3xl text-lg text-off-black/70">{{ $c['intro'] }}</p>

    {{-- ── Plans ─────────────────────────────────────────────────────────── --}}
    <div class="mt-12 grid gap-6 md:grid-cols-2">
        <article class="flex flex-col rounded-3xl border border-off-black/10 bg-white p-8 shadow-sm">
            <h2 class="text-sm font-bold uppercase tracking-[0.14em] text-off-black/60">{{ $c['monthly_name'] }}</h2>
            <div class="mt-4 flex items-baseline gap-2">
                <span class="font-montserrat text-5xl font-black">€{{ $monthly }}</span>
                <span class="text-sm font-semibold text-off-black/60">{{ $c['per_month'] }}</span>
            </div>
            <p class="mt-2 text-sm text-off-black/60">{{ $c['monthly_note'] }}</p>
            <a href="{{ $appUrl }}/register?type=business&amp;plan=monthly"
               class="mt-8 rounded-full bg-off-black px-7 py-3 text-center font-bold text-white transition hover:bg-off-black/85">{{ $c['cta'] }}</a>
        </article>

        <article class="relative flex flex-col rounded-3xl border-2 border-off-black bg-primary/25 p-8 shadow-sm">
            @if ($savePercent > 0)
                <span class="absolute right-6 top-6 rounded-full bg-off-black px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-primary">{{ str_replace(':percent', (string) $savePercent, $c['save_badge']) }}</span>
            @endif
            <h2 class="text-sm font-bold uppercase tracking-[0.14em] text-off-black/60">{{ $c['quarterly_name'] }}</h2>
            <div class="mt-4 flex items-baseline gap-2">
                <span class="font-montserrat text-5xl font-black">€{{ $quarterlyPerMonth }}</span>
                <span class="text-sm font-semibold text-off-black/60">{{ $c['per_month'] }}</span>
            </div>
            <p class="mt-2 text-sm text-off-black/60">{{ str_replace(':price', '€'.$quarterly, $c['quarterly_note']) }}</p>
            <a href="{{ $appUrl }}/register?type=business&amp;plan=three_months"
               class="mt-8 rounded-full bg-off-black px-7 py-3 text-center font-bold text-primary transition hover:bg-off-black/85">{{ $c['cta'] }}</a>
        </article>
    </div>

    {{-- ── What's included ───────────────────────────────────────────────── --}}
    <div class="mt-12 rounded-[2rem] border border-off-black/10 bg-white p-8">
        <h2 class="text-2xl font-bold">{{ $c['included_title'] }}</h2>
        <ul class="mt-6 grid gap-4 md:grid-cols-2">
            @foreach ($c['included'] as $item)
                <li class="flex items-start gap-3 text-off-black/75">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 shrink-0 text-off-black"><path d="M20 6 9 17l-5-5"/></svg>
                    {{ $item }}
                </li>
            @endforeach
        </ul>
    </div>

    {{-- Communities are free. This is a product rule, not a promotion — it belongs
         on the pricing page so no organizer ever thinks they have to pay. --}}
    <div class="mt-6 rounded-[2rem] bg-primary/20 p-8">
        <h2 class="text-2xl font-bold">{{ $c['communities_title'] }}</h2>
        <p class="mt-4 max-w-3xl text-off-black/75">{{ $c['communities_desc'] }}</p>
        <a href="{{ $appUrl }}/register?type=community" class="mt-6 inline-block rounded-full bg-white px-7 py-3 font-bold text-off-black transition hover:bg-white/80">{{ $c['communities_cta'] }}</a>
    </div>

    {{-- ── FAQ ───────────────────────────────────────────────────────────── --}}
    <div class="mt-12">
        <h2 class="font-montserrat text-2xl font-black uppercase">{{ $c['faq_title'] }}</h2>
        <div class="mt-6 grid gap-5 md:grid-cols-2">
            @foreach ($faqs as $faq)
                <article class="rounded-3xl border border-off-black/10 bg-white p-7">
                    <h3 class="text-lg font-bold">{{ $faq['q'] }}</h3>
                    <p class="mt-3 text-off-black/70">{{ $faq['a'] }}</p>
                </article>
            @endforeach
        </div>
    </div>

    <div class="mt-12 rounded-[2rem] bg-off-black p-8 text-white md:flex md:items-center md:justify-between md:gap-10">
        <div>
            <h2 class="font-montserrat text-2xl font-black uppercase leading-tight md:text-3xl">{{ $c['final_title'] }}</h2>
            <p class="mt-3 max-w-xl text-white/70">{{ $c['final_desc'] }}</p>
        </div>
        <div class="mt-6 flex shrink-0 flex-col gap-3 md:mt-0">
            <a href="{{ $appUrl }}/register?type=business&amp;plan=monthly" class="rounded-full bg-primary px-7 py-3 text-center font-bold text-off-black transition hover:bg-primary/90">{{ $c['cta'] }}</a>
            <a href="{{ $appUrl }}/login" class="text-center text-sm font-medium text-white/60 hover:text-white">{{ $c['login'] }}</a>
        </div>
    </div>
</section>
