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
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <span x-show="unread > 0" x-cloak class="absolute top-[9px] right-[10px] w-2 h-2 rounded-full bg-accent ring-[1.5px] ring-white"></span>
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
                       class="kb-on-yellow flex-1 h-12 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark hover:-translate-y-px transition flex items-center justify-center gap-2.5">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        {{ __('webapp.dashboard.find_kolab') }}
                    </a>
                    <a href="{{ $base }}/kolabs?tab=requests"
                       class="flex-1 h-12 rounded-pill bg-white border border-line text-ink text-sm font-bold shadow-card hover:-translate-y-px transition flex items-center justify-center">{{ __('webapp.dashboard.my_applications') }}</a>
                </div>

                {{-- Profile strength: a thin profile is the main reason a community's
                     applications get passed over, and every missing piece here is a
                     field the API actually exposes. --}}
                <div x-show="profileScore.percent < 100" x-cloak
                     class="rounded-3xl bg-primary-tint border border-primary p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-ink">{{ __('webapp.dashboard.profile_strength') }}</p>
                            <p class="text-xs text-body mt-1" x-text="t('dashboard.profile_strength_hint', { missing: profileScore.missing.join(', ') })"></p>
                        </div>
                        <span class="font-anton text-[28px] text-ink shrink-0" x-text="profileScore.percent + '%'"></span>
                    </div>
                    <div class="h-2 rounded-pill bg-ink/10 mt-3 overflow-hidden">
                        <div class="h-full rounded-pill bg-ink transition-all" :style="`width:${profileScore.percent}%`"></div>
                    </div>
                    <a href="{{ $base }}/account?edit=1"
                       class="inline-flex items-center justify-center h-10 px-5 mt-4 rounded-pill bg-inverse text-on-inverse text-[13px] font-bold hover:-translate-y-px transition">{{ __('webapp.dashboard.complete_profile') }}</a>
                </div>

                {{-- Next up. The server now answers this for a community too; it
                     used to be business-only, which is why this dashboard had nothing
                     to say. Suppressed while the profile-strength card above is
                     showing, so the two never argue about the same thing. --}}
                <template x-if="d.next_action && !duplicateProfilePrompt">
                    <a :href="kbPath(nextActionHref)"
                       class="flex items-center gap-4 rounded-3xl bg-primary-tint border border-primary p-5 hover:-translate-y-px transition">
                        <span class="w-11 h-11 rounded-2xl bg-white/70 flex items-center justify-center shrink-0">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg>
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-[11px] font-bold tracking-[1px] uppercase text-amber">{{ __('webapp.dashboard.next_up') }}</span>
                            <span class="block text-sm font-bold text-ink mt-0.5" x-text="nextActionTitle"></span>
                            <span class="block text-xs text-body mt-0.5" x-text="nextActionBody"></span>
                        </span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted shrink-0"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                </template>

                {{-- The community they run. The shell already fetched these numbers for
                     the nav badge, so this costs no extra request. Hidden entirely for
                     a community that has not created one yet — the Hub is where that
                     happens, and nagging here would be a second front door. --}}
                <div x-show="communityStats && activeCommunity" x-cloak
                     class="rounded-3xl bg-white border border-ink/[.08] p-5 shadow-card">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold tracking-[1px] uppercase text-muted">{{ __('webapp.dashboard.your_community') }}</p>
                            <p class="text-[17px] font-bold text-ink mt-1 truncate" x-text="activeCommunity?.name"></p>
                        </div>
                        <a :href="kbPath('/community/members')"
                           class="shrink-0 h-9 px-4 rounded-pill bg-white border border-line text-[12.5px] font-bold text-ink hover:border-ink transition flex items-center">{{ __('webapp.dashboard.manage') }}</a>
                    </div>
                    <div class="flex gap-2 mt-4">
                        <template x-for="s in communityTiles" :key="s.label">
                            <a :href="kbPath(s.href)"
                               class="flex-1 rounded-xl bg-cream-input px-2 py-3 text-center hover:bg-ink/[.06] transition">
                                <p class="font-anton text-xl text-ink" x-text="s.n"></p>
                                <p class="text-[9px] font-medium tracking-[.5px] text-muted mt-0.5" x-text="s.label"></p>
                            </a>
                        </template>
                    </div>
                </div>

                @include('webapp.partials.upcoming')

                <div class="flex gap-2 flex-wrap">
                    <template x-for="s in commStats" :key="s.label">
                        <a :href="kbPath(s.href)"
                           class="flex-1 min-w-[80px] rounded-xl bg-white border border-ink/[.08] px-2 py-3 text-center hover:border-ink/25 transition">
                            <p class="font-anton text-xl text-ink" x-text="s.n"></p>
                            <p class="text-[9px] font-medium tracking-[.5px] text-muted mt-0.5" x-text="s.label"></p>
                        </a>
                    </template>
                </div>

                @include('webapp.partials.dashboard-widgets')
            </div>
        </template>

        {{-- ── Business dashboard ──────────────────────────────────────── --}}
        <template x-if="!loading && isBusiness">
            <div class="flex flex-col gap-7 mt-5 kb-fade-up">
                <div class="max-w-[560px]">
                    <p class="text-sm font-bold text-ink">{{ __('webapp.dashboard.biz_pitch') }}</p>
                    <p class="text-xs text-muted mt-0.5">{{ __('webapp.dashboard.biz_pitch_sub') }}</p>
                </div>

                {{-- Next action — the server already decides what this business should
                     do next (complete profile / first Kolab / review applications /
                     leave a review); surface it instead of leaving it in the payload. --}}
                <template x-if="d.next_action">
                    <a :href="kbPath(nextActionHref)"
                       class="flex items-center gap-4 rounded-3xl bg-primary-tint border border-primary p-5 hover:-translate-y-px transition">
                        <span class="w-11 h-11 rounded-2xl bg-white/70 flex items-center justify-center shrink-0">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg>
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-[11px] font-bold tracking-[1px] uppercase text-amber">{{ __('webapp.dashboard.next_up') }}</span>
                            <span class="block text-sm font-bold text-ink mt-0.5" x-text="nextActionTitle"></span>
                            <span class="block text-xs text-body mt-0.5" x-text="nextActionBody"></span>
                        </span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted shrink-0"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                </template>

                <div class="grid sm:grid-cols-2 gap-3">
                    {{-- Monthly goal — completed Kolabs this month against the target. --}}
                    <template x-if="d.monthly_goal">
                        <div class="rounded-3xl bg-white border border-ink/[.08] p-5 shadow-card">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-[11px] font-bold tracking-[1px] uppercase text-muted">{{ __('webapp.dashboard.monthly_goal') }}</p>
                                <span x-show="d.monthly_goal?.met" x-cloak
                                      class="px-2.5 py-1 rounded-xl bg-ok-surface text-ok-ink text-[10px] font-bold tracking-[.4px]">{{ __('webapp.dashboard.goal_met') }}</span>
                            </div>
                            <p class="font-anton text-[32px] text-ink mt-2 leading-none">
                                <span x-text="d.monthly_goal?.completed ?? 0"></span><span class="text-muted text-[20px]"> / <span x-text="d.monthly_goal?.goal ?? 0"></span></span>
                            </p>
                            <div class="h-2 rounded-pill bg-ink/10 mt-3 overflow-hidden">
                                <div class="kb-on-yellow h-full rounded-pill bg-primary transition-all" :style="`width:${goalPercent}%`"></div>
                            </div>
                            <p class="text-xs text-muted mt-2" x-text="goalHint"></p>
                        </div>
                    </template>

                    {{-- Partner status — the reputation the server computes from real
                         completed Kolabs and reviews. --}}
                    <template x-if="d.partner_status">
                        <div class="rounded-3xl bg-white border border-ink/[.08] p-5 shadow-card">
                            <p class="text-[11px] font-bold tracking-[1px] uppercase text-muted">{{ __('webapp.dashboard.partner_status') }}</p>
                            <p class="text-[17px] font-bold text-ink mt-2" x-text="d.partner_status?.label"></p>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-3 text-[12px] text-body">
                                <template x-for="b in partnerBreakdown" :key="b.label">
                                    <span><span class="font-bold text-ink" x-text="b.value"></span> <span x-text="b.label"></span></span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="grid md:grid-cols-2 gap-5 items-start">
                    <div class="kb-on-yellow rounded-3xl bg-primary p-5 flex flex-col items-start gap-2.5">
                        <span class="px-3 py-[5px] rounded-pill bg-inverse text-on-inverse text-[11px] font-bold tracking-[.8px]">{{ __('webapp.dashboard.biz_activity') }}</span>
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
                        <a href="{{ $base }}/kolabs/create" class="w-full h-[42px] rounded-pill bg-inverse text-on-inverse text-sm font-bold hover:-translate-y-px transition flex items-center justify-center">{{ __('webapp.nav.create') }}</a>
                    </div>

                    <div>
                        <p class="text-[13px] font-semibold tracking-[1px] uppercase text-ink mb-2.5">{{ __('webapp.dashboard.grow') }}</p>
                        <div class="flex flex-col gap-2">
                            <a href="{{ $base }}/kolabs/create" class="flex items-center gap-3 p-3 rounded-2xl bg-white border border-ink/[.08] hover:border-ink/25 transition text-left">
                                <span class="w-9 h-9 rounded-xl bg-cream-low flex items-center justify-center shrink-0">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-[13px] font-bold text-ink">{{ __('webapp.nav.create') }}</span>
                                    <span class="block text-xs text-muted">{{ __('webapp.dashboard.create_sub') }}</span>
                                </span>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="m9 18 6-6-6-6"/></svg>
                            </a>

                            <a href="{{ $base }}/kolabs?tab=requests" class="flex items-center gap-3 p-3 rounded-2xl bg-primary-tint border border-primary hover:-translate-y-px transition text-left">
                                <span class="w-9 h-9 rounded-xl bg-white/70 flex items-center justify-center shrink-0">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-[13px] font-bold text-ink">{{ __('webapp.dashboard.review_applications') }}</span>
                                    <span class="block text-xs text-muted" x-text="t('dashboard.waiting', { count: d.applications_received?.pending ?? 0 })"></span>
                                </span>
                                <span x-show="(d.applications_received?.pending ?? 0) > 0" x-cloak
                                      class="px-2 py-[3px] rounded-pill bg-inverse text-on-inverse text-[11px] font-bold shrink-0" x-text="d.applications_received?.pending"></span>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="m9 18 6-6-6-6"/></svg>
                            </a>

                            <a href="{{ $base }}/feed" class="flex items-center gap-3 p-3 rounded-2xl bg-white border border-ink/[.08] hover:border-ink/25 transition text-left">
                                <span class="w-9 h-9 rounded-xl bg-cream-low flex items-center justify-center shrink-0">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-[13px] font-bold text-ink">{{ __('webapp.dashboard.find_community') }}</span>
                                    <span class="block text-xs text-muted">{{ __('webapp.dashboard.find_community_sub') }}</span>
                                </span>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="m9 18 6-6-6-6"/></svg>
                            </a>

                            <a href="{{ $base }}/kolabs" class="flex items-center gap-3 p-3 rounded-2xl bg-white border border-ink/[.08] hover:border-ink/25 transition text-left">
                                <span class="w-9 h-9 rounded-xl bg-cream-low flex items-center justify-center shrink-0">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-[13px] font-bold text-ink">{{ __('webapp.dashboard.view_kolabs') }}</span>
                                    <span class="block text-xs text-muted" x-text="t('dashboard.active_completed', { active: d.collaborations?.active ?? 0, completed: d.collaborations?.completed ?? 0 })"></span>
                                </span>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="m9 18 6-6-6-6"/></svg>
                            </a>
                        </div>
                    </div>
                </div>

                @include('webapp.partials.upcoming')

                @include('webapp.partials.dashboard-widgets')
            </div>
        </template>
    </div>
    </main>
</div>

@push('scripts')
<script>
    function dashboardPage() {
        /*
         * BE-NF-39. Server-side, like the sidebar entry and the route itself: with
         * suggestions off the API 404s, so the dashboard does not ask. The block in
         * dashboard-widgets.blade.php is gated on the same config value, so with the
         * flag off there is neither markup nor a request.
         */
        const suggestionsEnabled = @json((bool) config('suggestions.enabled'));

        return {
            loading: true, loadingExtras: true, greeting: '', d: {}, upcoming: [],
            recommended: [], activity: [], savedCount: 0,
            // Null until there is a real suggestion to name — the block renders
            // nothing rather than saying "0 suggestions".
            suggestionTop: null, suggestionCount: 0,
            get dashTitle() { return this.isBusiness ? t('dashboard.title_business') : t('dashboard.title_community'); },
            get commStats() {
                const a = this.d.applications_sent || {}, c = this.d.collaborations || {};
                const r = this.d.applications_received || {};
                // Each tile links to the screen that explains the number.
                const tiles = [
                    { n: a.pending ?? 0, label: t('status.pending').toUpperCase(), href: '/kolabs?tab=requests' },
                    { n: c.active ?? 0, label: t('status.active').toUpperCase(), href: '/kolabs?tab=active' },
                    { n: c.completed ?? 0, label: t('status.completed').toUpperCase(), href: '/kolabs?tab=finished' },
                    { n: this.savedCount, label: t('feed.tab_saved'), href: '/feed?tab=saved' },
                ];
                /*
                 * Communities post Kolabs too, and then receive applications. The
                 * backend only started reporting this for communities in BE-FX-29, so
                 * the tile appears only once there is something in it — a permanent
                 * "0 RECEIVED" would be noise for the majority who only apply.
                 */
                if ((r.total ?? 0) > 0) {
                    tiles.splice(1, 0, {
                        n: r.pending ?? 0,
                        label: t('dashboard.received').toUpperCase(),
                        href: '/kolabs?tab=requests&sub=received',
                    });
                }
                return tiles;
            },

            /** The community they run, from the shell's already-loaded stats. */
            get communityTiles() {
                const m = this.communityStats?.members || {};
                const p = this.communityStats?.pending || {};
                return [
                    { n: m.active ?? 0, label: t('dashboard.members').toUpperCase(), href: '/community/members' },
                    { n: p.join_requests ?? 0, label: t('dashboard.requests').toUpperCase(), href: '/community/requests' },
                    { n: m.new_this_month ?? 0, label: t('dashboard.new_this_month').toUpperCase(), href: '/community/members' },
                ];
            },

            /*
             * The server's `complete_profile` and the profile-strength card above are
             * the same advice measured two ways (four fields vs seven). Showing both
             * reads as nagging, and they can even disagree — so while the meter is up,
             * the card stands down.
             */
            get duplicateProfilePrompt() {
                return this.d.next_action?.key === 'complete_profile' && this.profileScore.percent < 100;
            },

            /*
             * next_action's title/body are English strings built server-side (and read
             * by mobile in that form), so translating them here — by key, falling back
             * to whatever the server sent — is what makes the card speak Spanish and
             * Catalan without changing the API contract. An unknown key still renders.
             */
            get nextActionTitle() {
                const na = this.d.next_action;
                if (!na) return '';
                const count = this.d.applications_received?.pending ?? 0;
                // One waiting application is a different sentence from three.
                const one = na.key === 'review_pending_applications' && count === 1 ? '_one' : '';
                return window.tOr('dashboard.na_' + na.key + one + '_title', na.title).replace(':count', count);
            },
            get nextActionBody() {
                const na = this.d.next_action;
                if (!na) return '';
                return window.tOr('dashboard.na_' + na.key + '_body', na.body);
            },

            /**
             * Profile strength for a community. Every item is a field the API exposes
             * and that a business weighs when picking who to work with.
             */
            get profileScore() {
                const p = this.profile || {};
                const checks = [
                    [!!p.name, t('account.community_name')],
                    [!!(p.about || '').trim(), t('account.about')],
                    [!!p.community_type, t('account.community_type')],
                    [!!p.community_size, t('account.community_size')],
                    [!!p.city, t('account.city')],
                    [!!(p.logo_url || p.profile_photo), t('account.photo')],
                    [!!(p.instagram || p.tiktok || p.website), t('account.instagram')],
                ];
                const done = checks.filter(([ok]) => ok).length;
                return {
                    percent: Math.round((done / checks.length) * 100),
                    missing: checks.filter(([ok]) => !ok).map(([, label]) => label),
                };
            },

            // ── Business-only widgets, from data /me/dashboard already returns ──
            get goalPercent() {
                const g = this.d.monthly_goal || {};
                if (!g.goal) return 0;
                return Math.min(100, Math.round(((g.completed || 0) / g.goal) * 100));
            },
            get goalHint() {
                const g = this.d.monthly_goal || {};
                const left = Math.max(0, (g.goal || 0) - (g.completed || 0));
                return g.met ? t('dashboard.goal_met_hint') : t('dashboard.goal_left', { count: left });
            },
            get partnerBreakdown() {
                const b = this.d.partner_status?.breakdown || {};
                const rows = [
                    { value: b.completed_kolabs ?? 0, label: t('dashboard.bd_completed') },
                    { value: b.review_count ?? 0, label: t('dashboard.bd_reviews') },
                    { value: b.repeat_partner_count ?? 0, label: t('dashboard.bd_repeat') },
                ];
                if (b.average_rating) rows.unshift({ value: Number(b.average_rating).toFixed(1) + '★', label: t('dashboard.bd_rating') });
                return rows;
            },
            get nextActionHref() {
                return {
                    complete_profile: '/account?edit=1',
                    apply_to_first: '/feed',
                    create_first_offer: '/kolabs/create',
                    create_second_offer: '/kolabs/create',
                    review_pending_applications: '/kolabs?tab=requests&sub=received',
                    leave_review: '/kolabs?tab=finished',
                }[this.d.next_action?.key] || '/kolabs';
            },
            get recommendedTitle() {
                return this.isBusiness ? t('dashboard.rec_business') : t('dashboard.rec_community');
            },
            /** Singular form for the one-card case, which is the common one. */
            get suggestionCountLabel() {
                const key = this.suggestionCount === 1
                    ? 'suggestions.dashboard_block_title_one'
                    : 'suggestions.dashboard_block_title';
                return t(key, { count: this.suggestionCount });
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
            kbPath(p) { return window.kbPath(p); },
            ago(iso) {
                if (!iso) return '';
                const secs = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000);
                if (secs < 3600) return t('notifications.minutes', { n: Math.max(1, Math.floor(secs / 60)) });
                if (secs < 86400) return t('notifications.hours', { n: Math.floor(secs / 3600) });
                if (secs < 604800) return t('notifications.days', { n: Math.floor(secs / 86400) });
                return window.kbDateShort(iso);
            },
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
                // Secondary panels load after the numbers are on screen, so a slow
                // discovery query never holds up the dashboard itself.
                this.loadExtras();
            },

            async loadExtras() {
                const [rec, notes, saved, sugg] = await Promise.all([
                    window.kb.api('/discovery/opportunities?feed=recommended&page=1&per_page=3'),
                    window.kb.api('/me/notifications?per_page=4'),
                    this.isCommunity ? window.kb.api('/kolabs?saved=1&per_page=100') : Promise.resolve(null),
                    // One card is all the block shows; `meta.total` carries the count,
                    // so there is no reason to fetch a page to count it.
                    suggestionsEnabled ? window.kb.api('/suggestions?per_page=1') : Promise.resolve(null),
                ]);
                if (rec?.ok) this.recommended = window.kb.rows(rec).map(k => this.recCard(k));
                if (notes?.ok) this.activity = window.kb.rows(notes);
                if (saved?.ok) this.savedCount = window.kb.rows(saved).length;
                if (sugg?.ok) {
                    const rows = window.kb.rows(sugg);
                    this.suggestionTop = rows.length ? this.suggestionCard(rows[0]) : null;
                    this.suggestionCount = window.kb.meta(sugg).total || rows.length;
                }
                this.loadingExtras = false;
            },
            /**
             * The top suggestion, reduced to what the dashboard shows. Same rules as
             * the /suggestions card: `blurred` is SuggestionResource's
             * `is_identity_blurred` and is never re-derived from the viewer's role (a
             * community is never blurred), the identity fields are read only when it
             * is false, and the reason is already a sentence in the reader's locale.
             */
            suggestionCard(s) {
                const blurred = !!s.is_identity_blurred;
                const name = s.counterpart?.name || '';
                return {
                    blurred,
                    name: blurred ? '' : (name || t('feed.a_partner')),
                    initial: blurred ? '' : window.kbInitial(name || t('feed.a_partner')),
                    avatar: blurred ? '' : (s.counterpart?.avatar_url || ''),
                    scoreLabel: t('suggestions.score_badge', { score: s.score ?? 0 }),
                    reason: (s.signals || []).find(g => g && g.reason)?.reason || '',
                };
            },
            recCard(k) {
                return {
                    id: k.id,
                    name: k.creator_profile?.display_name || t('feed.a_partner'),
                    img: k.cover_photo_url || k.offer_photo || '',
                    meta: window.kbIntentLabel(k.intent_type),
                    city: k.preferred_city || k.area || '',
                    offer: k.offer_headline || k.title || '',
                    match: k.match_score || 0,
                };
            },
        };
    }
</script>
@endpush
@endsection
