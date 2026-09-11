@extends('webapp.layout')
@section('title', __('webapp.feed.title'))

@section('body')
{{--
    Explore, as an agenda rather than a wall of cards.

    The old grid answered "what is out there"; the question people actually arrive
    with is "what could I do, and when". So the page is spined by date: each Kolab
    sits under the soonest day it can actually be booked (window.kbNextDates —
    tomorrow at the earliest, honouring recurring days), and the ones with no fixed
    window collect under "Open now" at the top because those are the ones you can
    act on immediately.

    Hierarchy inverted with it. The old card led with the poster's name and buried
    the offer in 13px text; the offer is the thing being read, so it is the heading
    now and the poster moved to a "By …" line under it.
--}}
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), kbModalMixin(), explorePage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'feed'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[940px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        {{-- ── Header + tabs ───────────────────────────────────────────── --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="font-anton text-[30px] md:text-[34px] leading-none tracking-[1px] text-ink">{{ __('webapp.feed.title') }}</h1>
                <p class="text-sm text-muted mt-2" x-text="subtitle">{{ __('webapp.feed.subtitle_community') }}</p>
            </div>
            {{-- Segmented control: the active segment lifts off the well rather than inverting. --}}
            <div class="flex p-1 bg-cream-low rounded-xl self-start sm:self-auto shrink-0">
                <button type="button" @click="setTab('forYou')"
                        class="min-w-[96px] h-9 px-4 rounded-lg text-[13px] font-bold tracking-[.3px] transition"
                        :class="tab === 'forYou' ? 'bg-white text-ink shadow-btn' : 'text-muted hover:text-body'">{{ __('webapp.feed.tab_for_you') }}</button>
                <button type="button" @click="setTab('saved')"
                        class="min-w-[96px] h-9 px-4 rounded-lg text-[13px] font-bold tracking-[.3px] transition"
                        :class="tab === 'saved' ? 'bg-white text-ink shadow-btn' : 'text-muted hover:text-body'">{{ __('webapp.feed.tab_saved') }}</button>
            </div>
        </div>

        {{-- ── Search ──────────────────────────────────────────────────── --}}
        <div class="flex gap-2 mt-6">
            <div class="relative flex-1">
                <svg class="absolute left-4 top-1/2 -translate-y-1/2 text-muted pointer-events-none" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input x-model="search" @keydown.enter="reload()" type="search" placeholder="{{ __('webapp.feed.search_placeholder') }}"
                       class="w-full h-11 rounded-xl border border-ink/[.10] bg-white pl-11 pr-4 text-sm text-ink shadow-card focus:border-ink/30">
            </div>
            <button type="button" @click="reload()"
                    class="h-11 px-5 rounded-xl bg-inverse text-on-inverse text-sm font-bold hover:-translate-y-px transition shrink-0">{{ __('webapp.common.search') }}</button>
        </div>

        {{-- ── Category chips ──────────────────────────────────────────── --}}
        <div class="flex gap-2 flex-wrap mt-4">
            <button type="button" @click="setCategory('')"
                    class="px-3.5 py-1.5 rounded-pill text-[12.5px] font-semibold border transition"
                    :class="category === '' ? 'bg-ink text-white border-ink' : 'bg-white text-body border-ink/[.12] hover:border-ink/30'">{{ __('webapp.feed.cat_all') }}</button>
            <template x-for="c in categories" :key="c.value">
                <button type="button" @click="setCategory(c.value)"
                        class="px-3.5 py-1.5 rounded-pill text-[12.5px] font-semibold border transition"
                        :class="category === c.value ? 'bg-ink text-white border-ink' : 'bg-white text-body border-ink/[.12] hover:border-ink/30'"
                        x-text="c.label"></button>
            </template>
        </div>

        <template x-if="error">
            <div class="mt-6 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
        </template>
        <template x-if="loading && cards.length === 0">
            {{-- Skeleton rows in the agenda's own shape, so the page does not jump. --}}
            <div class="mt-9 space-y-4">
                <template x-for="i in 3" :key="i">
                    <div class="md:grid md:grid-cols-[104px_1fr] md:gap-x-7">
                        <div class="space-y-2 pt-1">
                            <div class="h-3.5 w-16 rounded bg-cream-low animate-pulse"></div>
                            <div class="h-3 w-20 rounded bg-cream-low animate-pulse"></div>
                        </div>
                        <div class="mt-3 md:mt-0 flex gap-4 rounded-2xl bg-white border border-ink/[.07] p-4">
                            <div class="flex-1 space-y-2.5">
                                <div class="h-3 w-20 rounded bg-cream-low animate-pulse"></div>
                                <div class="h-4 w-3/4 rounded bg-cream-low animate-pulse"></div>
                                <div class="h-3 w-1/3 rounded bg-cream-low animate-pulse"></div>
                            </div>
                            <div class="w-[92px] h-[92px] md:w-[116px] md:h-[116px] rounded-xl bg-cream-low animate-pulse shrink-0"></div>
                        </div>
                    </div>
                </template>
            </div>
        </template>
        <template x-if="!loading && cards.length === 0 && !error">
            <div class="mt-9 rounded-2xl border-[1.5px] border-dashed border-ink/20 py-14 text-center text-sm text-muted"
                 x-text="tab === 'saved' ? t('feed.empty_saved') : t('feed.empty')"></div>
        </template>

        {{-- ── The agenda ──────────────────────────────────────────────── --}}
        <div class="mt-9">
            <template x-for="g in groups" :key="g.key">
                <section class="md:grid md:grid-cols-[104px_1fr] md:gap-x-7">
                    {{-- Rail label: date on top, weekday under it. --}}
                    <div class="pt-0.5">
                        <p class="font-anton text-[16px] leading-tight tracking-[.6px] text-ink" x-text="g.label"></p>
                        <p class="text-[13px] text-muted mt-0.5" x-text="g.sub"></p>
                    </div>

                    {{-- The spine: dashed on the cards' left edge, with a node per group. --}}
                    <div class="relative mt-3 md:mt-0 md:border-l md:border-dashed md:border-line md:pl-7 pb-6">
                        <span class="hidden md:block absolute -left-[4.5px] top-[7px] w-[9px] h-[9px] rounded-full bg-line ring-4 ring-cream"></span>

                        <div class="flex flex-col gap-3.5">
                            <template x-for="cd in g.cards" :key="cd.id">
                                <article @click="openDetail(cd.id)"
                                         class="relative flex gap-4 rounded-2xl bg-white border border-ink/[.07] p-4 cursor-pointer shadow-card hover:shadow-cardhover hover:border-ink/20 hover:-translate-y-px transition">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[12.5px] font-bold tracking-[.2px] text-body tabular-nums" x-text="cd.when"></p>

                                        <h3 class="mt-1 text-[17px] md:text-[19px] font-bold leading-snug tracking-[-.35px] text-ink line-clamp-2"
                                            x-text="cd.offer"></h3>

                                        {{-- By whoever posted it. The name links to their profile; the row itself opens the Kolab. --}}
                                        <div class="flex items-center gap-2 mt-2.5 min-w-0">
                                            <template x-if="cd.avatar">
                                                <img :src="cd.avatar" :alt="cd.host" class="w-6 h-6 rounded-full object-cover shrink-0">
                                            </template>
                                            <template x-if="!cd.avatar">
                                                <span class="w-6 h-6 rounded-full bg-peach text-peach-ink text-[11px] font-bold flex items-center justify-center shrink-0"
                                                      x-text="window.kbInitial(cd.host)"></span>
                                            </template>
                                            <p class="text-[13.5px] text-body truncate">
                                                <span class="text-muted">{{ __('webapp.feed.by') }}</span>
                                                <template x-if="cd.profileId">
                                                    <a :href="window.kbPath('/profiles/' + cd.profileId)" @click.stop
                                                       class="font-semibold text-ink hover:underline" x-text="cd.host"></a>
                                                </template>
                                                <template x-if="!cd.profileId">
                                                    <span class="font-semibold text-ink" x-text="cd.host"></span>
                                                </template>
                                            </p>
                                        </div>

                                        <div class="flex items-center gap-1.5 mt-1.5 text-[13px] text-muted min-w-0">
                                            <svg class="shrink-0" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                            <span class="truncate" x-text="cd.place"></span>
                                        </div>

                                        <div class="flex items-center gap-1.5 flex-wrap mt-3">
                                            <span x-show="cd.match > 0" x-cloak
                                                  class="px-2.5 py-1 rounded-pill bg-primary text-on-primary text-[11.5px] font-bold"
                                                  x-text="t('feed.match', { pct: cd.match })"></span>
                                            <template x-for="ch in cd.chips" :key="ch">
                                                <span class="px-2.5 py-1 rounded-pill bg-cream-low text-body text-[11.5px] font-semibold" x-text="ch"></span>
                                            </template>
                                        </div>
                                    </div>

                                    <div class="shrink-0 w-[92px] h-[92px] md:w-[116px] md:h-[116px] rounded-xl overflow-hidden bg-cream-input relative">
                                        <template x-if="cd.img">
                                            <img :src="cd.img" :alt="cd.offer" class="w-full h-full object-cover block">
                                        </template>
                                        <template x-if="!cd.img">
                                            <span class="w-full h-full flex items-center justify-center font-anton text-[22px] text-faint"
                                                  x-text="window.kbInitial(cd.host)"></span>
                                        </template>
                                        <button type="button" @click.stop="toggleSave(cd)"
                                                :title="isSaved(cd.id) ? t('feed.unsave') : t('feed.save')"
                                                class="absolute top-1.5 right-1.5 w-7 h-7 rounded-full bg-white/90 hover:bg-white transition flex items-center justify-center text-[13px] leading-none"
                                                :style="`color:${isSaved(cd.id) ? 'rgb(var(--kb-warn-ink))' : 'rgb(var(--kb-muted))'}`"
                                                x-text="isSaved(cd.id) ? '★' : '☆'"></button>
                                    </div>
                                </article>
                            </template>
                        </div>
                    </div>
                </section>
            </template>
        </div>

        <div class="mt-2 text-center" x-show="!loading && page < lastPage" x-cloak>
            <button type="button" @click="loadMore()"
                    class="h-11 px-6 rounded-xl bg-white border border-line text-sm font-bold hover:border-ink transition">{{ __('webapp.feed.load_more') }}</button>
        </div>
        <p class="mt-4 text-center text-sm text-muted" x-show="loading && cards.length > 0" x-cloak>{{ __('webapp.common.loading') }}</p>
    </div>
    </main>

    @include('webapp.partials.kolab-modals')
</div>

@push('scripts')
<script>
    function explorePage() {
        return {
            tab: 'forYou', category: '', search: '',
            cards: [], savedIds: [], categories: [],
            loading: true, error: '', page: 1, lastPage: 1,

            // Detail + apply modal state (see partials/kolab-modals.blade.php).
            dk: null, detailLoading: false, detailError: '', appliedIds: [],
            applyOpen: false, applyErr: '', applyBusy: false, applySuccess: false,
            applyDates: [], applyStart: '10:00', applyEnd: '13:00', applyMsg: '', applyNotes: '',
            timeOptions: ['7:00','8:00','9:00','10:00','11:00','12:00','13:00','14:00','16:00','17:00','18:00','19:00','20:00','21:00','22:00'],

            get subtitle() { return this.isBusiness ? t('feed.subtitle_business') : t('feed.subtitle_community'); },

            /**
             * The agenda. Kolabs bucketed by the soonest day they can be booked.
             *
             * "Open now" comes first and holds everything with no fixed window — those
             * are the only ones actionable today, so burying them under next month's
             * dates would be backwards. Everything else is strictly chronological.
             */
            get groups() {
                const locale = window.KB_LOCALE || 'en';
                const pad = (n) => String(n).padStart(2, '0');
                const today = new Date(); today.setHours(0, 0, 0, 0);
                const tomorrow = new Date(today.getTime() + 86400000);
                const tomorrowKey = `${tomorrow.getFullYear()}-${pad(tomorrow.getMonth() + 1)}-${pad(tomorrow.getDate())}`;

                const anytime = [];
                const dated = new Map();

                for (const cd of this.cards) {
                    if (!cd.soonest) { anytime.push(cd); continue; }
                    if (!dated.has(cd.soonest.value)) {
                        dated.set(cd.soonest.value, { key: cd.soonest.value, date: cd.soonest.date, cards: [] });
                    }
                    dated.get(cd.soonest.value).cards.push(cd);
                }

                const out = [];
                if (anytime.length) {
                    out.push({ key: 'anytime', label: t('feed.group_open'), sub: t('feed.group_open_sub'), cards: anytime });
                }
                [...dated.values()]
                    .sort((a, b) => a.date - b.date)
                    .forEach((g) => out.push({
                        key: g.key,
                        label: g.key === tomorrowKey
                            ? t('feed.group_tomorrow')
                            : g.date.toLocaleDateString(locale, { month: 'short', day: 'numeric' }),
                        sub: g.date.toLocaleDateString(locale, { weekday: 'long' }),
                        cards: g.cards,
                    }));

                return out;
            },

            async init() {
                if (!window.kb.requireAuth()) return;
                const me = await this.loadShell();
                if (!me) return;
                const params = new URLSearchParams(location.search);
                if (params.get('tab') === 'saved') this.tab = 'saved';
                await Promise.all([this.loadCategories(), this.loadSavedIds(), this.loadAppliedIds()]);
                await this.load(1);
            },
            async loadCategories() {
                const res = await window.kb.api('/lookup/community-types', { auth: false });
                if (res.ok) this.categories = window.kb.rows(res).slice(0, 6);
            },
            async loadSavedIds() {
                const res = await window.kb.api('/kolabs?saved=1&per_page=100');
                if (res.ok) this.savedIds = window.kb.rows(res).map(k => k.id);
            },
            isSaved(id) { return this.savedIds.includes(id); },

            setTab(tab) { if (this.tab === tab) return; this.tab = tab; this.load(1); },
            setCategory(v) { if (this.category === v) return; this.category = v; this.load(1); },
            reload() { this.load(1); },
            loadMore() { this.load(this.page + 1); },

            async load(page) {
                this.loading = true; this.error = '';
                if (page === 1) this.cards = [];
                const res = this.tab === 'saved' ? await this.fetchSaved(page) : await this.fetchDiscovery(page);
                if (!res.ok) {
                    this.error = window.kb.errorText(res, t('feed.load_error'));
                    this.loading = false;
                    return;
                }
                const { rows, meta, source } = res;
                const mapped = rows.map(r => this.normalize(r, source));
                this.cards = page === 1 ? mapped : this.cards.concat(mapped);
                this.page = meta?.current_page || page;
                this.lastPage = meta?.last_page || this.page;
                this.loading = false;
            },
            async fetchDiscovery(page) {
                const p = new URLSearchParams({ feed: 'recommended', page: String(page), per_page: '20' });
                if (this.search) p.set('search', this.search);
                if (this.category) p.append('community_types[]', this.category);
                const res = await window.kb.api('/discovery/opportunities?' + p.toString());
                if (!res.ok) return res;
                // The endpoint nests the page under data.data and repeats meta at both levels.
                const rows = window.kb.rows(res);
                return { ok: true, rows, meta: window.kb.meta(res), source: 'discovery' };
            },
            async fetchSaved(page) {
                const p = new URLSearchParams({ saved: '1', page: String(page), per_page: '20' });
                if (this.search) p.set('search', this.search);
                if (this.category) p.set('community_types', this.category);
                const res = await window.kb.api('/kolabs?' + p.toString());
                if (!res.ok) return res;
                return { ok: true, rows: window.kb.rows(res), meta: window.kb.meta(res), source: 'kolab' };
            },

            /**
             * Fold the discovery + kolab payloads into one card view-model.
             *
             * The two endpoints disagree on shape — discovery nests dates under
             * `availability`, KolabResource keeps them flat — so both are flattened
             * here and handed to kbNextDates(), which owns the bookable-date rule.
             */
            normalize(k, source) {
                const chips = source === 'discovery' ? this.discoveryChips(k) : this.kolabChips(k);
                const creator = k.creator_profile || {};
                const av = k.availability || {};
                const dates = {
                    availability_start: av.start ?? k.availability_start ?? null,
                    availability_end: av.end ?? k.availability_end ?? null,
                    recurring_days: av.recurring_days ?? k.recurring_days ?? [],
                };
                const time = av.selected_time ?? k.selected_time ?? '';
                const meta = this.metaLabel(k, source);
                const city = k.preferred_city || k.area || creator.city?.name || '';

                return {
                    id: k.id,
                    profileId: creator.id || null,
                    host: creator.display_name || t('feed.a_partner'),
                    avatar: creator.avatar_url || '',
                    img: k.cover_photo_url || k.offer_photo || (k.media || [])[0]?.url || '',
                    offer: k.offer_headline || k.title || t('feed.a_kolab'),
                    place: [meta, city].filter(Boolean).join(' · '),
                    chips: chips.slice(0, 2),
                    match: source === 'discovery' ? (k.match_score || 0) : 0,
                    soonest: window.kbNextDates(dates, 1)[0] || null,
                    when: this.whenLabel(time, dates.availability_end),
                };
            },
            /**
             * The line above the title. The group header already carries the date, so
             * this carries what the date does not: the time, or how long the window
             * stays open, or that there is no window at all.
             */
            whenLabel(time, end) {
                if (time) return time;
                if (end) return t('feed.when_until', { date: window.kbDateShort(end) });
                return t('feed.when_flexible');
            },
            metaLabel(k, source) {
                if (source === 'kolab') {
                    const c = k.creator_profile || {};
                    const raw = c.business_type || c.community_type;
                    if (raw) return window.kbHumanize(raw);
                }
                return window.kbIntentLabel(k.intent_type);
            },
            discoveryChips(k) {
                const out = [];
                const req = k.community_request, off = k.business_offer;
                if (req) {
                    (req.need_types || []).forEach(v => out.push(window.kbHumanize(v)));
                    (req.community_types || []).forEach(o => out.push(o.label || window.kbHumanize(o.key)));
                }
                if (off) {
                    (off.offer_types || []).forEach(v => out.push(window.kbHumanize(v)));
                    (off.seeking_communities || []).forEach(o => out.push(o.label || window.kbHumanize(o.key)));
                }
                return [...new Set(out.filter(Boolean))];
            },
            kolabChips(k) {
                const out = [...(k.needs || []), ...(k.community_types || []), ...(k.offering || [])];
                return [...new Set(out.filter(Boolean).map(window.kbHumanize))];
            },

            async toggleSave(cd) {
                const saved = this.isSaved(cd.id);
                const res = await window.kb.api('/kolabs/' + cd.id + '/save', { method: saved ? 'DELETE' : 'POST' });
                if (!res.ok && res.status !== 204) return;
                this.savedIds = saved ? this.savedIds.filter(i => i !== cd.id) : [...this.savedIds, cd.id];
                if (this.tab === 'saved' && saved) this.cards = this.cards.filter(c => c.id !== cd.id);
                if (this.dk && this.dk.id === cd.id) this.dk.is_saved = !saved;
            },
        };
    }
</script>
@endpush
@endsection
