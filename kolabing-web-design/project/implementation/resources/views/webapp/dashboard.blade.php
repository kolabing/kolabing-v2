@extends('webapp.layout')
@section('title', __('webapp.nav.home'))

@section('body')
<div class="min-h-screen md:flex" x-data="dashboardPage()" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'dashboard'])

    <main class="flex-1 min-w-0">
    <div class="max-w-4xl mx-auto px-5 py-8 md:py-10">
        <div class="flex items-start gap-3">
            <div class="flex-1">
                <h1 class="font-anton text-[28px]" x-text="isBusiness ? 'Business dashboard' : 'Community dashboard'"></h1>
                <p class="text-sm text-muted mt-1" x-text="greeting"></p>
            </div>
            <a href="{{ $base }}/notifications" class="relative w-10 h-10 rounded-full bg-white border border-ink/10 hover:border-ink/30 flex items-center justify-center">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            </a>
            <a href="{{ $base }}/account" class="w-10 h-10 rounded-full bg-primary/50 border border-ink/10 flex items-center justify-center text-sm font-semibold" x-text="initial"></a>
        </div>

        <template x-if="loading"><p class="mt-8 text-muted">{{ __('webapp.common.loading') }}</p></template>

        {{-- ══ Community ══ --}}
        <template x-if="!loading && !isBusiness">
            <div class="mt-7 space-y-7">
                <div class="flex gap-3">
                    <a href="{{ $base }}/feed" class="flex-1 h-12 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark hover:-translate-y-px transition flex items-center justify-center gap-2">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        Find a Kolab
                    </a>
                    <a href="{{ $base }}/kolabs?tab=requests" class="flex-1 h-12 rounded-pill bg-white border border-ink/15 text-sm font-bold hover:border-ink transition flex items-center justify-center">My applications</a>
                </div>

                <div>
                    <p class="text-[13px] font-semibold tracking-wide uppercase mb-3">Upcoming Kolabs</p>
                    <template x-if="upcoming.length === 0">
                        <div class="rounded-2xl border-2 border-dashed border-ink/15 py-10 text-center text-muted text-sm">
                            No upcoming Kolabs yet — <a href="{{ $base }}/feed" class="font-semibold text-ink underline">find one</a>
                        </div>
                    </template>
                    <div class="space-y-2.5">
                        <template x-for="c in upcoming" :key="c.id">
                            <div class="flex items-center gap-3 rounded-2xl bg-white border border-ink/10 shadow-card p-4 hover:border-ink/25 transition">
                                <div class="w-10 h-10 rounded-full bg-primary/40 flex items-center justify-center text-[15px] font-semibold shrink-0" x-text="(c.partner?.name || '?')[0]"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold truncate" x-text="c.partner?.name || 'Partner'"></p>
                                    <p class="text-[13px] text-body truncate" x-text="c.kolab?.title"></p>
                                    <span class="inline-block mt-1.5 px-2 py-0.5 rounded-md bg-cream-input text-[11px] font-medium text-body" x-text="c.scheduled_date || '—'"></span>
                                </div>
                                <span class="px-3 py-1 rounded-xl text-[11px] font-bold tracking-wide uppercase" :class="statusClass(c.status)" x-text="c.status"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="flex gap-2">
                    <template x-for="s in commStats" :key="s.label">
                        <div class="flex-1 rounded-xl bg-white border border-ink/10 py-3 text-center">
                            <p class="font-anton text-xl" x-text="s.n"></p>
                            <p class="text-[9px] font-medium tracking-wider text-muted mt-0.5" x-text="s.label"></p>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        {{-- ══ Business ══ --}}
        <template x-if="!loading && isBusiness">
            <div class="mt-6 space-y-7">
                <div class="max-w-xl">
                    <p class="text-sm font-bold">Turn local partnerships into repeatable customer growth.</p>
                    <p class="text-xs text-muted mt-0.5">Post a Kolab, pick a community, and bring real people through the door.</p>
                </div>

                <div class="grid md:grid-cols-2 gap-5 items-start">
                    <div class="rounded-3xl bg-primary p-5 flex flex-col items-start gap-2.5">
                        <span class="px-3 py-1 rounded-pill bg-ink text-primary text-[11px] font-bold tracking-wider">BUSINESS ACTIVITY</span>
                        <div>
                            <p class="font-anton text-[40px] leading-none" x-text="d.opportunities?.published ?? 0"></p>
                            <p class="text-[11px] font-bold tracking-widest text-amber mt-1">LIVE KOLABS</p>
                        </div>
                        <div class="flex gap-2 w-full">
                            <template x-for="s in bizStats" :key="s.label">
                                <div class="flex-1 rounded-[14px] bg-ink/5 py-2 text-center">
                                    <p class="font-anton text-[17px]" x-text="s.n"></p>
                                    <p class="text-[9px] font-bold tracking-wide text-amber" x-text="s.label"></p>
                                </div>
                            </template>
                        </div>
                        <a href="{{ $base }}/kolabs/create" class="w-full h-[42px] rounded-pill bg-ink text-primary text-sm font-bold hover:-translate-y-px transition flex items-center justify-center">Create a Kolab</a>
                    </div>

                    <div>
                        <p class="text-[13px] font-semibold tracking-wide uppercase mb-2.5">Grow your business</p>
                        <div class="space-y-2">
                            <template x-if="d.next_action">
                                <div class="rounded-2xl bg-primary-tint border border-primary p-3">
                                    <p class="text-[13px] font-bold" x-text="d.next_action?.title"></p>
                                    <p class="text-xs text-muted mt-0.5" x-text="d.next_action?.body"></p>
                                </div>
                            </template>
                            <a href="{{ $base }}/kolabs?tab=requests" class="flex items-center gap-3 rounded-2xl bg-white border border-ink/10 p-3 hover:border-ink/25 transition">
                                <span class="w-9 h-9 rounded-xl bg-cream-low flex items-center justify-center shrink-0">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-[13px] font-bold">Review applications</span>
                                    <span class="block text-xs text-muted" x-text="(d.applications_received?.pending ?? 0) + ' communities waiting'"></span>
                                </span>
                                <span class="px-2 py-0.5 rounded-pill bg-ink text-primary text-[11px] font-bold" x-show="(d.applications_received?.pending ?? 0) > 0" x-text="d.applications_received?.pending"></span>
                            </a>
                            <a href="{{ $base }}/feed" class="flex items-center gap-3 rounded-2xl bg-white border border-ink/10 p-3 hover:border-ink/25 transition">
                                <span class="w-9 h-9 rounded-xl bg-cream-low flex items-center justify-center shrink-0">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-[13px] font-bold">Find a community</span>
                                    <span class="block text-xs text-muted">Browse community requests</span>
                                </span>
                            </a>
                            <a href="{{ $base }}/kolabs" class="flex items-center gap-3 rounded-2xl bg-white border border-ink/10 p-3 hover:border-ink/25 transition">
                                <span class="w-9 h-9 rounded-xl bg-cream-low flex items-center justify-center shrink-0">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-[13px] font-bold">View my Kolabs</span>
                                    <span class="block text-xs text-muted" x-text="(d.collaborations?.active ?? 0) + ' active · ' + (d.collaborations?.completed ?? 0) + ' completed'"></span>
                                </span>
                            </a>
                        </div>
                    </div>
                </div>

                <div>
                    <p class="text-[13px] font-semibold tracking-wide uppercase mb-3">Upcoming Kolabs</p>
                    <template x-if="upcoming.length === 0">
                        <div class="rounded-2xl border-2 border-dashed border-ink/15 py-10 text-center text-muted text-sm">No upcoming Kolabs yet</div>
                    </template>
                    <div class="space-y-2.5">
                        <template x-for="c in upcoming" :key="c.id">
                            <div class="flex items-center gap-3 rounded-2xl bg-white border border-ink/10 shadow-card p-4 hover:border-ink/25 transition">
                                <div class="w-10 h-10 rounded-full bg-primary/40 flex items-center justify-center text-[15px] font-semibold shrink-0" x-text="(c.partner?.name || '?')[0]"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold truncate" x-text="c.partner?.name || 'Partner'"></p>
                                    <p class="text-[13px] text-body truncate" x-text="c.kolab?.title"></p>
                                    <span class="inline-block mt-1.5 px-2 py-0.5 rounded-md bg-cream-input text-[11px] font-medium text-body" x-text="c.scheduled_date || '—'"></span>
                                </div>
                                <span class="px-3 py-1 rounded-xl text-[11px] font-bold tracking-wide uppercase" :class="statusClass(c.status)" x-text="c.status"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </div>
    </main>
</div>

@push('scripts')
<script>
    function dashboardPage() {
        return {
            loading: true, isBusiness: false, greeting: '', initial: '?', d: {}, upcoming: [],
            get commStats() {
                const a = this.d.applications_sent || {}, c = this.d.collaborations || {};
                return [
                    { n: a.pending ?? 0, label: 'PENDING' }, { n: c.active ?? 0, label: 'ACTIVE' },
                    { n: c.completed ?? 0, label: 'DONE' }, { n: a.accepted ?? 0, label: 'ACCEPTED' },
                ];
            },
            get bizStats() {
                const a = this.d.applications_received || {}, c = this.d.collaborations || {};
                return [
                    { n: a.pending ?? 0, label: 'NEW APPS' }, { n: c.active ?? 0, label: 'ACTIVE' },
                    { n: c.completed ?? 0, label: 'COMPLETED' },
                ];
            },
            statusClass(s) {
                return {
                    scheduled: 'bg-[#FFDDAC] text-[#D8910B]', pending_confirmation: 'bg-[#FFDDAC] text-[#D8910B]',
                    active: 'bg-[#D4EDDA] text-[#155724]',
                    completed: 'bg-[#EDEAE0] text-[#4C4638]', cancelled: 'bg-[#F8D7DA] text-[#721C24]',
                }[s] || 'bg-cream-input text-body';
            },
            async init() {
                if (!window.kb.requireAuth()) return;
                const [me, dash] = await Promise.all([
                    window.kb.api('/auth/me'),
                    window.kb.api('/me/dashboard'),
                ]);
                if (!me.ok) { window.kb.logout(); return; }
                const u = me.json?.data || {};
                this.isBusiness = u.user_type === 'business';
                const name = u.business_profile?.name || u.community_profile?.name || u.display_name || u.name || '';
                this.greeting = name ? t('dashboard.welcome_name', { name }) : t('dashboard.welcome');
                this.initial = (name || '?')[0].toUpperCase();
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
