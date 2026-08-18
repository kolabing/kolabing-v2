@extends('webapp.layout')
@section('title', __('webapp.meta.home_title'))
@section('description', __('webapp.meta.home_description'))
@section('robots', 'index,follow')

@php
    // Shown on the business card — read from the same config Stripe bills.
    $monthlyPrice = (int) config('subscriptions.business.stripe.monthly.price');
@endphp

@section('body')
{{-- Built to be understood in a few seconds: one headline, one line of what it
     is, three swaps that show the exchange, and the two ways in. Nothing else. --}}
<div class="min-h-screen bg-cream-alt" x-data="kbMerge(kbThemeState(), kbLoginModal(), welcomeScreen())" x-init="init()">

    {{-- ── Hero ──────────────────────────────────────────────────────────── --}}
    <div class="kb-on-yellow bg-primary h-[30vh] min-h-[170px] flex items-center justify-center kb-hero-curve">
        <img src="/webapp-assets/wordmark-dark.png" alt="Kolabing" class="w-[230px] max-w-[68%]">
    </div>

    <div class="flex flex-col items-center px-6 pt-11 pb-9">
        <div class="max-w-[600px] w-full flex flex-col items-center gap-5 text-center">
            <h1 class="font-anton text-[36px] sm:text-[52px] leading-[1.02] text-ink">{{ __('webapp.hero.headline') }}</h1>
            <p class="text-lg sm:text-xl text-body font-medium">{{ __('webapp.hero.subline') }}</p>

            <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto mt-1">
                <a href="{{ $base }}/register"
                   class="kb-on-yellow h-14 px-9 rounded-pill bg-primary text-ink text-base font-bold shadow-btn hover:bg-primary-dark hover:-translate-y-px transition flex items-center justify-center">{{ __('webapp.hero.get_started') }}</a>
                <button type="button" @click="openLogin()"
                        class="h-14 px-9 rounded-pill bg-white border border-line text-ink text-base font-bold hover:border-ink transition">{{ __('webapp.hero.log_in') }}</button>
            </div>
        </div>
    </div>

    {{-- ── The exchange ──────────────────────────────────────────────────────
         Three swaps, one line each. Showing the trade lands faster than any
         paragraph defining the word "Kolab". --}}
    <section class="px-6 pb-12">
        <div class="max-w-[860px] mx-auto flex flex-col gap-2.5">
            @foreach (__('webapp.hero.swaps') as $swap)
                <div class="bg-white border border-ink/[.08] rounded-2xl shadow-card px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-5">
                    <p class="flex-1 text-[15px] font-semibold text-ink">{{ $swap['gives'] }}</p>
                    <span class="kb-on-yellow shrink-0 w-9 h-9 rounded-full bg-primary flex items-center justify-center self-start sm:self-auto">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="text-ink"><path d="M7 17h14"/><path d="m17 21 4-4-4-4"/><path d="M17 7H3"/><path d="m7 3-4 4 4 4"/></svg>
                    </span>
                    <p class="flex-1 text-[15px] font-semibold text-ink">{{ $swap['gets'] }}</p>
                </div>
            @endforeach

            <div class="flex items-center justify-center gap-2 mt-3 flex-wrap">
                @foreach (__('webapp.hero.steps') as $step)
                    <span class="px-4 py-1.5 rounded-pill bg-cream-low text-[12px] font-bold tracking-[.6px] uppercase text-body">{{ $step }}</span>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── One account, both surfaces ────────────────────────────────────────
         The web app and the mobile app are the same product on the same API, so
         say it here — people who sign up on the web should know their account is
         already waiting in the app. Store links come from config; a blank one is
         simply not rendered. --}}
    <section class="px-6 pb-12">
        <div class="max-w-[860px] mx-auto rounded-3xl bg-ink/[.04] border border-ink/[.08] px-6 py-5 flex flex-col sm:flex-row sm:items-center gap-4">
            <span class="w-11 h-11 rounded-2xl bg-cream-low flex items-center justify-center shrink-0">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-ink"><rect x="2" y="4" width="14" height="12" rx="2"/><path d="M2 16h14"/><rect x="17" y="8" width="5" height="12" rx="1.5"/></svg>
            </span>
            <div class="flex-1 min-w-0">
                <p class="text-[15px] font-bold text-ink">{{ __('webapp.hero.cross_title') }}</p>
                <p class="text-[13px] text-body mt-0.5">{{ __('webapp.hero.cross_sub') }}</p>
            </div>
            @if (config('webapp.app_store_url') || config('webapp.play_store_url'))
                <div class="flex items-center gap-2 shrink-0">
                    @if (config('webapp.app_store_url'))
                        <a href="{{ config('webapp.app_store_url') }}" target="_blank" rel="noopener"
                           class="h-10 px-4 rounded-pill bg-white border border-line text-[13px] font-bold text-ink hover:border-ink transition flex items-center">{{ __('webapp.welcome.app_store') }}</a>
                    @endif
                    @if (config('webapp.play_store_url'))
                        <a href="{{ config('webapp.play_store_url') }}" target="_blank" rel="noopener"
                           class="h-10 px-4 rounded-pill bg-white border border-line text-[13px] font-bold text-ink hover:border-ink transition flex items-center">{{ __('webapp.welcome.google_play') }}</a>
                    @endif
                </div>
            @endif
        </div>
    </section>

    {{-- ── Two ways in ───────────────────────────────────────────────────── --}}
    <section class="px-6 pb-16">
        <div class="max-w-[860px] mx-auto grid sm:grid-cols-2 gap-3">
            <a href="{{ $base }}/register?type=business"
               class="group bg-white border border-ink/[.08] rounded-3xl p-6 shadow-card hover:-translate-y-0.5 hover:shadow-cardhover hover:border-ink/20 transition flex flex-col">
                <p class="text-[19px] font-bold text-ink">{{ __('webapp.hero.biz_title') }}</p>
                <p class="text-[13.5px] text-body mt-1.5 flex-1">{{ __('webapp.hero.biz_sub') }}</p>
                <p class="text-[12.5px] text-muted mt-4">{{ __('webapp.hero.biz_price', ['price' => '€'.$monthlyPrice]) }}</p>
                <span class="kb-on-yellow h-11 mt-3 rounded-pill bg-primary text-ink text-sm font-bold flex items-center justify-center">{{ __('webapp.hero.biz_cta') }}</span>
            </a>

            <a href="{{ $base }}/register?type=community"
               class="group bg-white border border-ink/[.08] rounded-3xl p-6 shadow-card hover:-translate-y-0.5 hover:shadow-cardhover hover:border-ink/20 transition flex flex-col">
                <div class="flex items-center gap-2">
                    <p class="text-[19px] font-bold text-ink">{{ __('webapp.hero.comm_title') }}</p>
                    <span class="px-2 py-[3px] rounded-pill bg-ok-surface text-ok-ink text-[10px] font-bold tracking-[.5px]">{{ __('webapp.form.free') }}</span>
                </div>
                <p class="text-[13.5px] text-body mt-1.5 flex-1">{{ __('webapp.hero.comm_sub') }}</p>
                <p class="text-[12.5px] text-muted mt-4">{{ __('webapp.hero.comm_price') }}</p>
                <span class="h-11 mt-3 rounded-pill bg-inverse text-on-inverse text-sm font-bold flex items-center justify-center">{{ __('webapp.hero.comm_cta') }}</span>
            </a>
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
