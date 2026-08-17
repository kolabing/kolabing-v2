@extends('webapp.layout')
@section('title', __('webapp.meta.login_title'))
@section('description', __('webapp.meta.login_description'))
@section('robots', 'index,follow')

@section('body')
{{-- /login stays a real URL (deep links, `kb.requireAuth()` redirects, SEO), but it
     presents as the same overlay the hero opens — never a bare standalone form.
     Closing it drops onto the hero behind. --}}
<div class="min-h-screen bg-cream-alt" x-data="kbMerge(kbLoginModal(), loginPage())" x-init="init()">
    <div class="min-h-screen flex flex-col">
        <div class="bg-primary h-[42vh] min-h-[220px] flex items-center justify-center kb-hero-curve">
            <img src="/webapp-assets/wordmark-dark.png" alt="Kolabing" class="w-[240px] max-w-[70%]">
        </div>
        <div class="flex-1 flex flex-col items-center px-6 pt-14 pb-12">
            <div class="max-w-[480px] w-full flex flex-col gap-7 text-center">
                <h1 class="font-anton text-[34px] sm:text-[44px] leading-[1.05] text-ink">{{ __('webapp.hero.headline') }}</h1>
                <p class="text-sm text-body leading-relaxed">{{ __('webapp.hero.tagline') }}</p>
                <button type="button" @click="openLogin()"
                        class="h-14 rounded-pill bg-primary text-ink text-base font-bold shadow-btn hover:bg-primary-dark hover:-translate-y-px transition flex items-center justify-center">{{ __('webapp.login.submit') }}</button>
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
