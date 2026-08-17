@extends('webapp.layout')
@section('title', __('webapp.meta.home_title'))
@section('description', __('webapp.meta.home_description'))
@section('robots', 'index,follow')

@section('body')
<div class="min-h-screen bg-cream-alt" x-data="kbMerge(kbLoginModal(), welcomeScreen())" x-init="init()">
    <div class="min-h-screen flex flex-col">
        <div class="bg-primary h-[42vh] min-h-[220px] flex items-center justify-center kb-hero-curve">
            <img src="/webapp-assets/wordmark-dark.png" alt="Kolabing" class="w-[240px] max-w-[70%]">
        </div>
        <div class="flex-1 flex flex-col items-center px-6 pt-14 pb-12">
            <div class="max-w-[480px] w-full flex flex-col gap-7 text-center">
                <h1 class="font-anton text-[34px] sm:text-[44px] leading-[1.05] text-ink">{{ __('webapp.hero.headline') }}</h1>
                <p class="text-sm text-body leading-relaxed">{{ __('webapp.hero.tagline') }}</p>
                <a href="{{ $base }}/register"
                   class="h-14 rounded-pill bg-primary text-ink text-base font-bold shadow-btn hover:bg-primary-dark hover:-translate-y-px transition flex items-center justify-center">{{ __('webapp.hero.get_started') }}</a>
                <p class="text-sm text-muted">
                    {{ __('webapp.hero.already_in') }}
                    {{-- Login opens in place — no page load between the site and the app. --}}
                    <button type="button" @click="openLogin()" class="font-semibold text-ink hover:underline">{{ __('webapp.hero.log_in') }}</button>
                </p>
            </div>
        </div>
    </div>

    @include('webapp.partials.login-modal')
</div>

@push('scripts')
<script>
    function welcomeScreen() {
        return {
            init() {
                // Authenticated visitors go straight to the app; guests see the hero.
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
