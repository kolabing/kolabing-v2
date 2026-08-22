@extends('webapp.layout')
@section('title', __('webapp.meta.login_title'))
@section('description', __('webapp.meta.login_description'))
@section('robots', 'index,follow')

@section('body')
{{-- The app host's only front door: the sign-in sheet opens on load, over a
     brand backdrop rather than a pitch — kolabing.com does the pitching. Closing
     the sheet leaves the two ways forward (sign in again, or create an account)
     on screen. `/` redirects here; `/register` is its own page. --}}
<div class="min-h-screen bg-cream-alt" x-data="kbMerge(kbThemeState(), kbLoginModal(), loginPage())" x-init="init()">
    <div class="min-h-screen flex flex-col">
        <div class="kb-on-yellow bg-primary h-[42vh] min-h-[220px] flex items-center justify-center kb-hero-curve">
            <img src="/webapp-assets/wordmark-dark.png" alt="Kolabing" class="w-[240px] max-w-[70%]">
        </div>
        <div class="flex-1 flex flex-col items-center px-6 pt-14 pb-12">
            <div class="max-w-[480px] w-full flex flex-col gap-7 text-center">
                <h1 class="font-anton text-[34px] sm:text-[44px] leading-[1.05] text-ink">{{ __('webapp.hero.headline') }}</h1>
                <p class="text-sm text-body leading-relaxed">{{ __('webapp.hero.tagline') }}</p>
                <button type="button" @click="openLogin()"
                        class="kb-on-yellow h-14 rounded-pill bg-primary text-ink text-base font-bold shadow-btn hover:bg-primary-dark hover:-translate-y-px transition flex items-center justify-center">{{ __('webapp.login.submit') }}</button>
                <p class="text-sm text-muted">
                    {{ __('webapp.login.new_here') }}
                    <a href="{{ $base }}/register" class="font-semibold text-ink hover:underline">{{ __('webapp.login.create_account') }}</a>
                </p>
            </div>
        </div>
    </div>

    @include('webapp.partials.login-modal')
</div>

@push('scripts')
<script>
    function loginPage() {
        return {
            init() {
                if (!window.kb.requireGuest()) return;
                this.openLogin();
            },
        };
    }
</script>
@endpush
@endsection
