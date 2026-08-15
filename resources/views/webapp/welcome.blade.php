@extends('webapp.layout')
@section('title', __('webapp.welcome.default_heading'))

@section('body')
<div x-data="welcomePage()" x-init="init()" class="min-h-screen flex flex-col items-center justify-center px-5 py-12 text-center">
    <div class="w-full max-w-md">
        <div class="mx-auto w-14 h-14 rounded-full bg-primary flex items-center justify-center text-2xl">🎉</div>
        <h1 class="font-montserrat font-black text-2xl tracking-tight mt-5" x-text="heading"></h1>
        <p class="text-off-black/60 mt-2" x-text="subheading"></p>

        <div class="mt-7 rounded-2xl border border-off-black/10 p-5">
            <p class="font-semibold">{{ __('webapp.welcome.continue_title') }}</p>
            <p class="text-sm text-off-black/60 mt-1">{{ __('webapp.welcome.continue_desc') }}</p>

            <a :href="deepLink" class="mt-4 inline-block w-full rounded-xl bg-off-black text-off-white font-semibold py-3">{{ __('webapp.welcome.open_app') }}</a>

            <div class="mt-3 flex items-center justify-center gap-3">
                <template x-if="iosUrl">
                    <a :href="iosUrl" class="text-sm font-semibold underline">{{ __('webapp.welcome.app_store') }}</a>
                </template>
                <template x-if="iosUrl && androidUrl"><span class="text-off-black/30">·</span></template>
                <template x-if="androidUrl">
                    <a :href="androidUrl" class="text-sm font-semibold underline">{{ __('webapp.welcome.google_play') }}</a>
                </template>
            </div>
        </div>

        <a href="{{ $base }}/dashboard" class="inline-block mt-6 text-sm text-off-black/60 underline">{{ __('webapp.welcome.keep_web') }}</a>
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
                const paid = new URLSearchParams(location.search).get('paid') === '1';
                if (paid) {
                    this.heading = t('welcome.paid_heading');
                    this.subheading = t('welcome.paid_subheading');
                }
            },
        };
    }
</script>
@endpush
@endsection
