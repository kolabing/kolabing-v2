@extends('webapp.layout')
@section('title', __('webapp.meta.home_title'))
@section('description', __('webapp.meta.home_description'))
@section('robots', 'index,follow')

@php
    // Shown on the plan card — read from the same config Stripe bills.
    $monthlyPrice = (int) config('subscriptions.business.stripe.monthly.price');
@endphp

@section('body')
<div class="min-h-screen bg-cream-alt" x-data="kbMerge(kbThemeState(), kbLoginModal(), welcomeScreen())" x-init="init()">

    {{-- ── Hero ──────────────────────────────────────────────────────────── --}}
    <div class="bg-primary h-[34vh] min-h-[190px] flex items-center justify-center kb-hero-curve">
        <img src="/webapp-assets/wordmark-dark.png" alt="Kolabing" class="w-[240px] max-w-[70%]">
    </div>

    <div class="flex flex-col items-center px-6 pt-12 pb-10">
        <div class="max-w-[620px] w-full flex flex-col gap-6 text-center">
            <h1 class="font-anton text-[34px] sm:text-[46px] leading-[1.05] text-ink">{{ __('webapp.hero.headline') }}</h1>
            {{-- The old page stopped at a tagline; this is the sentence that actually
                 says what the product does. --}}
            <p class="text-[15px] sm:text-base text-body leading-relaxed">{{ __('webapp.hero.explainer') }}</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center mt-1">
                <a href="{{ $base }}/register"
                   class="h-14 px-8 rounded-pill bg-primary text-ink text-base font-bold shadow-btn hover:bg-primary-dark hover:-translate-y-px transition flex items-center justify-center">{{ __('webapp.hero.get_started') }}</a>
                <button type="button" @click="openLogin()"
                        class="h-14 px-8 rounded-pill bg-white border border-line text-ink text-base font-bold hover:border-ink transition">{{ __('webapp.hero.log_in') }}</button>
            </div>
            <p class="text-[13px] text-muted">{{ __('webapp.hero.tagline') }}</p>
        </div>
    </div>

    {{-- ── What a Kolab actually is ──────────────────────────────────────────
         "Kolab" means nothing to a first-time visitor, so show three real ones
         instead of defining the word. One per intent type the product supports. --}}
    <section class="px-6 py-12 bg-cream">
        <div class="max-w-[980px] mx-auto">
            <h2 class="font-anton text-[26px] sm:text-[32px] text-ink text-center">{{ __('webapp.hero.what_title') }}</h2>
            <p class="text-sm text-body text-center mt-3 max-w-[560px] mx-auto">{{ __('webapp.hero.what_sub') }}</p>

            <div class="grid md:grid-cols-3 gap-4 mt-9">
                @foreach (__('webapp.hero.examples') as $example)
                    <div class="bg-white border border-ink/[.08] rounded-3xl p-6 shadow-card flex flex-col">
                        <span class="inline-flex self-start px-3 py-1 rounded-pill bg-peach text-peach-ink text-[11px] font-bold tracking-[.4px] uppercase">{{ $example['tag'] }}</span>
                        <p class="text-[15px] font-bold text-ink mt-4 leading-snug">{{ $example['title'] }}</p>
                        <p class="text-[13px] text-body mt-2 leading-relaxed flex-1">{{ $example['body'] }}</p>
                        <div class="border-t border-ink/[.08] mt-4 pt-3 flex items-start gap-2">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-ink shrink-0 mt-0.5"><path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><path d="M7 7h.01"/></svg>
                            <p class="text-[12.5px] font-semibold text-ink leading-snug">{{ $example['deal'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── How it works ──────────────────────────────────────────────────── --}}
    <section class="px-6 py-12">
        <div class="max-w-[980px] mx-auto">
            <h2 class="font-anton text-[26px] sm:text-[32px] text-ink text-center">{{ __('webapp.hero.how_title') }}</h2>
            <div class="grid md:grid-cols-3 gap-4 mt-9">
                @foreach (__('webapp.hero.steps') as $i => $step)
                    <div class="relative bg-white border border-ink/[.08] rounded-3xl p-6 shadow-card">
                        <span class="font-anton text-[34px] text-primary leading-none">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <p class="text-[15px] font-bold text-ink mt-2">{{ $step['title'] }}</p>
                        <p class="text-[13px] text-body mt-1.5 leading-relaxed">{{ $step['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── Two ways in ───────────────────────────────────────────────────────
         The role split is the product's central fact, and it is also the first
         thing register asks — so make the choice here and land on the right form. --}}
    <section class="px-6 py-12 bg-cream">
        <div class="max-w-[980px] mx-auto">
            <h2 class="font-anton text-[26px] sm:text-[32px] text-ink text-center">{{ __('webapp.hero.ways_title') }}</h2>

            <div class="grid md:grid-cols-2 gap-4 mt-9">
                {{-- Business --}}
                <div class="bg-white border border-ink/[.08] rounded-3xl p-7 shadow-card flex flex-col">
                    <span class="w-12 h-12 rounded-2xl bg-cream-low flex items-center justify-center">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-ink"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/></svg>
                    </span>
                    <p class="text-[19px] font-bold text-ink mt-4">{{ __('webapp.hero.biz_title') }}</p>
                    <p class="text-[13px] text-body mt-1.5">{{ __('webapp.hero.biz_sub') }}</p>
                    <div class="flex flex-col gap-2.5 mt-5 flex-1">
                        @foreach (__('webapp.hero.biz_points') as $point)
                            <div class="flex items-start gap-2.5 text-[13.5px] text-ink">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-0.5"><path d="M20 6 9 17l-5-5"/></svg>
                                {{ $point }}
                            </div>
                        @endforeach
                    </div>
                    <p class="text-[12.5px] text-muted mt-5">{{ __('webapp.hero.biz_price', ['price' => '€'.$monthlyPrice]) }}</p>
                    <a href="{{ $base }}/register?type=business"
                       class="h-12 mt-3 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark hover:-translate-y-px transition flex items-center justify-center">{{ __('webapp.hero.biz_cta') }}</a>
                </div>

                {{-- Community --}}
                <div class="bg-white border border-ink/[.08] rounded-3xl p-7 shadow-card flex flex-col">
                    <span class="w-12 h-12 rounded-2xl bg-cream-low flex items-center justify-center">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-ink"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </span>
                    <div class="flex items-center gap-2 mt-4">
                        <p class="text-[19px] font-bold text-ink">{{ __('webapp.hero.comm_title') }}</p>
                        <span class="px-2 py-[3px] rounded-pill bg-ok-surface text-ok-ink text-[10px] font-bold tracking-[.5px]">{{ __('webapp.form.free') }}</span>
                    </div>
                    <p class="text-[13px] text-body mt-1.5">{{ __('webapp.hero.comm_sub') }}</p>
                    <div class="flex flex-col gap-2.5 mt-5 flex-1">
                        @foreach (__('webapp.hero.comm_points') as $point)
                            <div class="flex items-start gap-2.5 text-[13.5px] text-ink">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-0.5"><path d="M20 6 9 17l-5-5"/></svg>
                                {{ $point }}
                            </div>
                        @endforeach
                    </div>
                    <p class="text-[12.5px] text-muted mt-5">{{ __('webapp.hero.comm_price') }}</p>
                    <a href="{{ $base }}/register?type=community"
                       class="h-12 mt-3 rounded-pill bg-ink text-primary text-sm font-bold hover:-translate-y-px transition flex items-center justify-center">{{ __('webapp.hero.comm_cta') }}</a>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Closing CTA ───────────────────────────────────────────────────── --}}
    <section class="px-6 pb-16">
        <div class="max-w-[980px] mx-auto rounded-3xl bg-primary p-9 sm:p-12 text-center">
            <h2 class="font-anton text-[26px] sm:text-[34px] text-ink">{{ __('webapp.hero.final_title') }}</h2>
            <p class="text-sm text-amber font-semibold mt-3">{{ __('webapp.hero.final_sub') }}</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center mt-7">
                <a href="{{ $base }}/register"
                   class="h-14 px-8 rounded-pill bg-ink text-primary text-base font-bold hover:-translate-y-px transition flex items-center justify-center">{{ __('webapp.hero.get_started') }}</a>
                <button type="button" @click="openLogin()"
                        class="h-14 px-8 rounded-pill bg-white/70 text-ink text-base font-bold hover:bg-white transition">{{ __('webapp.hero.log_in') }}</button>
            </div>
        </div>
    </section>

    @include('webapp.partials.login-modal')
</div>

@push('scripts')
<script>
    function welcomeScreen() {
        return {
            init() {
                // Authenticated visitors go straight to the app; guests see the page.
                if (!window.kb.requireGuest()) return;
                // ?login=1 lets the marketing site (or any external link) land straight
                // on the open sheet instead of a separate login page.
                if (new URLSearchParams(location.search).get('login') === '1') this.openLogin();
            },
        };
    }
</script>
@endpush
@endsection
