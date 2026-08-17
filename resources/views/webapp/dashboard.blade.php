@extends('webapp.layout')
@section('title', __('webapp.nav.home'))

@section('body')
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), dashboardPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'dashboard'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[880px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20">

        {{-- ── Page header ─────────────────────────────────────────────── --}}
        <div class="flex items-start gap-3">
            <div class="flex-1 min-w-0">
                <h1 class="font-anton text-[28px] tracking-[1px] text-ink" x-text="dashTitle">{{ __('webapp.dashboard.title') }}</h1>
                <p class="text-sm text-muted mt-1" x-text="greeting">&nbsp;</p>
            </div>
            <a href="{{ $base }}/notifications" class="relative w-[42px] h-[42px] rounded-full bg-white border border-ink/10 hover:border-ink/30 transition flex items-center justify-center shrink-0" aria-label="{{ __('webapp.nav.notifications') }}">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#19150F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <span x-show="unread > 0" x-cloak class="absolute top-[9px] right-[10px] w-2 h-2 rounded-full bg-[#FF6114] ring-[1.5px] ring-white"></span>
            </a>
            <a href="{{ $base }}/account" class="w-[42px] h-[42px] rounded-full bg-primary/50 border border-ink/10 flex items-center justify-center text-[15px] font-semibold text-ink shrink-0" x-text="initial">&nbsp;</a>
        </div>

        <template x-if="loading">
            <p class="mt-8 text-muted">{{ __('webapp.common.loading') }}</p>
        </template>

        {{-- ── Community dashboard ─────────────────────────────────────── --}}
        <template x-if="!loading && isCommunity">
            <div class="flex flex-col gap-7 mt-7 kb-fade-up">
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="{{ $base }}/feed"
                       class="flex-1 h-12 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark hover:-translate-y-px transition flex items-center justify-center gap-2.5">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        {{ __('webapp.dashboard.find_kolab') }}
                    </a>
                    <a href="{{ $base }}/kolabs?tab=requests"
                       class="flex-1 h-12 rounded-pill bg-white border border-line text-ink text-sm font-bold shadow-card hover:-translate-y-px transition flex items-center justify-center">{{ __('webapp.dashboard.my_applications') }}</a>
                </div>

                @include('webapp.partials.upcoming')

                <div class="flex gap-2">
                    <template x-for="s in commStats" :key="s.label">
                        <div class="flex-1 rounded-xl bg-white border border-ink/[.08] px-2 py-3 text-center">
                            <p class="font-anton text-xl text-ink" x-text="s.n"></p>
                            <p class="text-[9px] font-medium tracking-[.5px] text-muted mt-0.5" x-text="s.label"></p>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        {{-- ── Business dashboard ──────────────────────────────────────── --}}
        <template x-if="!loading && isBusiness">
            <div class="flex flex-col gap-7 mt-5 kb-fade-up">
                <div class="max-w-[560px]">
                    <p class="text-sm font-bold text-ink">{{ __('webapp.dashboard.biz_pitch') }}</p>
                    <p class="text-xs text-muted mt-0.5">{{ __('webapp.dashboard.biz_pitch_sub') }}</p>
                </div>

                <div class="grid md:grid-cols-2 gap-5 items-start">
                    <div class="rounded-3xl bg-primary p-5 flex flex-col items-start gap-2.5">
                        <span class="px-3 py-[5px] rounded-pill bg-ink text-primary text-[11px] font-bold tracking-[.8px]">{{ __('webapp.dashboard.biz_activity') }}</span>
                        <div>
                            <p class="font-anton text-[40px] leading-[.95] text-ink" x-text="d.opportunities?.published ?? 0">0</p>
                            <p class="text-[11px] font-bold tracking-[1.2px] text-amber mt-[3px]">{{ __('webapp.dashboard.live_kolabs') }}</p>
                        </div>
                        <div class="flex gap-2 w-full">
                            <template x-for="s in bizStats" :key="s.label">
                                <div class="flex-1 rounded-[14px] bg-ink/[.06] p-2 text-center">
                                    <p class="font-anton text-[17px] text-ink" x-text="s.n"></p>
                                    <p class="text-[9px] font-bold tracking-[.4px] text-amber mt-px" x-text="s.label"></p>
                                </div>
                            </template>
                        </div>
                        <a href="{{ $base }}/kolabs/create" class="w-full h-[42px] rounded-pill bg-ink text-primary text-sm font-bold hover:-translate-y-px transition flex items-center justify-center">{{ __('webapp.nav.create') }}</a>
                    </div>

                    <div>
                        <p class="text-[13px] font-semibold tracking-[1px] uppercase text-ink mb-2.5">{{ __('webapp.dashboard.grow') }}</p>
                        <div class="flex flex-col gap-2">
                            <a href="{{ $base }}/kolabs/create" class="flex items-center gap-3 p-3 rounded-2xl bg-white border border-ink/[.08] hover:border-ink/25 transition text-left">
                                <span class="w-9 h-9 rounded-xl bg-cream-low flex items-center justify-center shrink-0">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#19150F" stroke-width="2" stroke-linecap="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-[13px] font-bold text-ink">{{ __('webapp.nav.create') }}</span>
                                    <span class="block text-xs text-muted">{{ __('webapp.dashboard.create_sub') }}</span>
                                </span>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8C8474" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="m9 18 6-6-6-6"/></svg>
                            </a>

                            <a href="{{ $base }}/kolabs?tab=requests" class="flex items-center gap-3 p-3 rounded-2xl bg-primary-tint border border-primary hover:-translate-y-px transition text-left">
                                <span class="w-9 h-9 rounded-xl bg-white/70 flex items-center justify-center shrink-0">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#19150F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-[13px] font-bold text-ink">{{ __('webapp.dashboard.review_applications') }}</span>
                                    <span class="block text-xs text-muted" x-text="t('dashboard.waiting', { count: d.applications_received?.pending ?? 0 })"></span>
                                </span>
                                <span x-show="(d.applications_received?.pending ?? 0) > 0" x-cloak
                                      class="px-2 py-[3px] rounded-pill bg-ink text-primary text-[11px] font-bold shrink-0" x-text="d.applications_received?.pending"></span>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8C8474" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="m9 18 6-6-6-6"/></svg>
                            </a>

                            <a href="{{ $base }}/feed" class="flex items-center gap-3 p-3 rounded-2xl bg-white border border-ink/[.08] hover:border-ink/25 transition text-left">
                                <span class="w-9 h-9 rounded-xl bg-cream-low flex items-center justify-center shrink-0">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#19150F" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-[13px] font-bold text-ink">{{ __('webapp.dashboard.find_community') }}</span>
                                    <span class="block text-xs text-muted">{{ __('webapp.dashboard.find_community_sub') }}</span>
                                </span>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8C8474" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="m9 18 6-6-6-6"/></svg>
                            </a>

                            <a href="{{ $base }}/kolabs" class="flex items-center gap-3 p-3 rounded-2xl bg-white border border-ink/[.08] hover:border-ink/25 transition text-left">
                                <span class="w-9 h-9 rounded-xl bg-cream-low flex items-center justify-center shrink-0">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#19150F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-[13px] font-bold text-ink">{{ __('webapp.dashboard.view_kolabs') }}</span>
                                    <span class="block text-xs text-muted" x-text="t('dashboard.active_completed', { active: d.collaborations?.active ?? 0, completed: d.collaborations?.completed ?? 0 })"></span>
                                </span>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8C8474" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="m9 18 6-6-6-6"/></svg>
                            </a>
                        </div>
                    </div>
                </div>

                @include('webapp.partials.upcoming')
            </div>
        </template>
    </div>
    </main>
</div>

@push('scripts')
<script>
    function dashboardPage() {
        return {
            loading: true, greeting: '', d: {}, upcoming: [],
            get dashTitle() { return this.isBusiness ? t('dashboard.title_business') : t('dashboard.title_community'); },
            get commStats() {
                const a = this.d.applications_sent || {}, c = this.d.collaborations || {};
                return [
                    { n: a.pending ?? 0, label: t('status.pending').toUpperCase() },
                    { n: c.active ?? 0, label: t('status.active').toUpperCase() },
                    { n: c.completed ?? 0, label: t('status.completed').toUpperCase() },
                    { n: a.accepted ?? 0, label: t('status.accepted').toUpperCase() },
                ];
            },
            get bizStats() {
                const a = this.d.applications_received || {}, c = this.d.collaborations || {};
                return [
                    { n: a.pending ?? 0, label: t('dashboard.stat_new_apps') },
                    { n: c.active ?? 0, label: t('status.active').toUpperCase() },
                    { n: c.completed ?? 0, label: t('status.completed').toUpperCase() },
                ];
            },
            statusPill(s) { return window.kbStatus(s); },
            fmtDate(v) { return window.kbDate(v); },
            initialOf(name) { return window.kbInitial(name); },
            async init() {
                if (!window.kb.requireAuth()) return;
                const me = await this.loadShell();
                if (!me) return;
                this.greeting = this.displayName
                    ? t('dashboard.welcome_name', { name: this.displayName })
                    : t('dashboard.welcome');
                const dash = await window.kb.api('/me/dashboard');
                if (dash.ok) {
                    this.d = dash.json?.data || {};
                    this.upcoming = this.d.upcoming_collaborations || [];
                }
                this.loading = false;
            },
        };
    }
</script>
@endpush
@endsection
