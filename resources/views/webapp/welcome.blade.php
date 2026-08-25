@extends('webapp.layout')
@section('title', __('webapp.welcome.default_heading'))

@section('body')
<div x-data="welcomePage()" x-init="init()" class="min-h-screen bg-cream-alt flex flex-col items-center justify-center px-6 py-12 text-center">
    <div class="w-full max-w-[440px]">
        <div class="w-16 h-16 rounded-full bg-success-solid mx-auto flex items-center justify-center">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        </div>
        <h1 class="font-anton text-[30px] text-ink mt-5" x-text="heading">{{ __('webapp.welcome.default_heading') }}</h1>
        <p class="text-sm text-body mt-2 leading-relaxed" x-text="subheading">{{ __('webapp.welcome.default_subheading') }}</p>

        <div class="mt-7 bg-white border border-ink/[.08] rounded-3xl p-6 text-left shadow-card">
            <p class="text-[15px] font-bold text-ink">{{ __('webapp.welcome.continue_title') }}</p>
            <p class="text-[13px] text-muted mt-1.5 leading-relaxed">{{ __('webapp.welcome.continue_desc') }}</p>

            <a :href="deepLink"
               class="kb-on-yellow mt-4 w-full h-[52px] rounded-pill bg-primary text-ink text-[15px] font-bold shadow-btn hover:bg-primary-dark transition flex items-center justify-center">{{ __('webapp.welcome.open_app') }}</a>

            <div class="mt-3 flex items-center justify-center gap-3 text-[13px]">
                <template x-if="iosUrl">
                    <a :href="iosUrl" class="font-semibold text-ink underline">{{ __('webapp.welcome.app_store') }}</a>
                </template>
                <template x-if="iosUrl && androidUrl"><span class="text-muted">·</span></template>
                <template x-if="androidUrl">
                    <a :href="androidUrl" class="font-semibold text-ink underline">{{ __('webapp.welcome.google_play') }}</a>
                </template>
            </div>
        </div>

        <a href="{{ $base }}/dashboard" class="inline-block mt-6 text-[13px] font-semibold text-muted hover:text-ink underline">{{ __('webapp.welcome.keep_web') }}</a>
    </div>
</div>

@push('scripts')
<script>
    function welcomePage() {
        return {
            deepLink: window.KB_CONFIG.deepLink,
            iosUrl: window.KB_CONFIG.iosUrl,
            androidUrl: window.KB_CONFIG.androidUrl,
            heading: t('welcome.default_heading'),
            subheading: t('welcome.default_subheading'),
            init() {
                if (new URLSearchParams(location.search).get('paid') === '1') {
                    this.heading = t('welcome.paid_heading');
                    this.subheading = t('welcome.paid_subheading');
                }
            },
        };
    }
</script>
@endpush
@endsection
