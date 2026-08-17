@extends('webapp.layout')
@section('title', __('webapp.feed.title'))

@section('body')
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), kbModalMixin(), explorePage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'feed'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[880px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        {{-- ── Header + tabs ───────────────────────────────────────────── --}}
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <h1 class="font-anton text-[28px] tracking-[1px] text-ink">{{ __('webapp.feed.title') }}</h1>
                <p class="text-sm text-muted mt-1" x-text="subtitle">{{ __('webapp.feed.subtitle_community') }}</p>
            </div>
            <div class="flex p-1 bg-white border border-ink/[.12] rounded-pill self-start sm:self-auto">
                <button type="button" @click="setTab('forYou')"
                        class="min-w-[88px] h-8 rounded-pill text-[12.5px] font-bold tracking-[.4px] transition"
                        :class="tab === 'forYou' ? 'bg-ink text-white' : 'text-muted'">{{ __('webapp.feed.tab_for_you') }}</button>
                <button type="button" @click="setTab('saved')"
                        class="min-w-[88px] h-8 rounded-pill text-[12.5px] font-bold tracking-[.4px] transition"
                        :class="tab === 'saved' ? 'bg-ink text-white' : 'text-muted'">{{ __('webapp.feed.tab_saved') }}</button>
            </div>
        </div>

        {{-- ── Search ──────────────────────────────────────────────────── --}}
        <div class="flex gap-2 mt-5">
            <input x-model="search" @keydown.enter="reload()" type="search" placeholder="{{ __('webapp.feed.search_placeholder') }}"
                   class="flex-1 h-11 rounded-pill border border-transparent bg-white px-5 text-sm text-ink shadow-card">
            <button type="button" @click="reload()"
                    class="h-11 px-5 rounded-pill bg-ink text-white text-sm font-bold hover:-translate-y-px transition shrink-0">{{ __('webapp.common.search') }}</button>
        </div>

        {{-- ── Category chips ──────────────────────────────────────────── --}}
        <div class="flex gap-2 flex-wrap mt-5">
            <button type="button" @click="setCategory('')"
                    class="px-4 py-2 rounded-pill text-[12.5px] font-semibold border transition"
                    :class="category === '' ? 'bg-ink text-white border-ink' : 'bg-white text-body border-ink/[.12]'">{{ __('webapp.feed.cat_all') }}</button>
            <template x-for="c in categories" :key="c.value">
                <button type="button" @click="setCategory(c.value)"
                        class="px-4 py-2 rounded-pill text-[12.5px] font-semibold border transition"
                        :class="category === c.value ? 'bg-ink text-white border-ink' : 'bg-white text-body border-ink/[.12]'"
                        x-text="c.label"></button>
            </template>
        </div>

        <template x-if="error">
            <div class="mt-5 rounded-2xl bg-[#F8D7DA] text-[#721C24] text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
        </template>
        <template x-if="loading">
            <p class="mt-8 text-muted">{{ __('webapp.common.loading') }}</p>
        </template>
        <template x-if="!loading && cards.length === 0 && !error">
            <div class="mt-8 rounded-2xl border-[1.5px] border-dashed border-ink/20 py-12 text-center text-sm text-muted"
                 x-text="tab === 'saved' ? t('feed.empty_saved') : t('feed.empty')"></div>
        </template>

        {{-- ── Card grid ───────────────────────────────────────────────── --}}
        <div class="grid gap-5 mt-6" style="grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));">
            <template x-for="cd in cards" :key="cd.id">
                <div @click="openDetail(cd.id)"
                     class="bg-white border border-ink/[.08] rounded-2xl overflow-hidden cursor-pointer shadow-card hover:-translate-y-0.5 hover:shadow-cardhover hover:border-ink/20 transition">
                    <div class="relative bg-cream-input" style="aspect-ratio: 16/10;">
                        <template x-if="cd.img">
                            <img :src="cd.img" :alt="cd.name" class="w-full h-full object-cover block">
                        </template>
                        <template x-if="!cd.img">
                            <div class="w-full h-full flex items-center justify-center text-muted text-sm font-medium" x-text="cd.meta"></div>
                        </template>
                        <span x-show="cd.match > 0" x-cloak
                              class="absolute top-2.5 right-2.5 px-2.5 py-[5px] rounded-pill bg-ink text-white text-[11px] font-semibold"
                              x-text="t('feed.match', { pct: cd.match })"></span>
                        <button type="button" @click.stop="toggleSave(cd)" :title="isSaved(cd.id) ? t('feed.unsave') : t('feed.save')"
                                class="absolute top-2 left-2 w-8 h-8 rounded-full bg-white/90 hover:bg-white transition flex items-center justify-center text-base leading-none"
                                :style="`color:${isSaved(cd.id) ? '#D8910B' : '#8C8474'}`"
                                x-text="isSaved(cd.id) ? '★' : '☆'"></button>
                    </div>
                    <div class="p-3.5">
                        <p class="text-base font-bold tracking-[-.3px] text-ink truncate" x-text="cd.name"></p>
                        <div class="flex items-center gap-1 text-[12.5px] text-muted mt-[3px] min-w-0">
                            <span class="truncate" x-text="cd.meta"></span>
                            <template x-if="cd.city">
                                <span class="flex items-center gap-1 shrink-0">
                                    <span>·</span>
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                    <span x-text="cd.city"></span>
                                </span>
                            </template>
                        </div>
                        <div class="flex gap-1.5 items-start mt-2.5">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#19150F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-0.5"><path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><path d="M7 7h.01"/></svg>
                            <p class="text-[13px] font-semibold leading-tight text-ink line-clamp-2" x-text="cd.offer"></p>
                        </div>
                        <div class="flex gap-1.5 flex-wrap mt-2.5" x-show="cd.chips.length" x-cloak>
                            <template x-for="ch in cd.chips" :key="ch">
                                <span class="px-2.5 py-1 rounded-pill bg-peach text-peach-ink text-[11px] font-semibold" x-text="ch"></span>
                            </template>
                        </div>
                        <div class="border-t border-ink/[.08] mt-3 pt-2.5 flex items-center justify-between">
                            <span class="text-[12.5px] font-semibold text-body">{{ __('webapp.feed.view_details') }}</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8C8474" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="mt-8 text-center" x-show="!loading && page < lastPage" x-cloak>
            <button type="button" @click="loadMore()"
                    class="h-11 px-6 rounded-pill bg-white border border-line text-sm font-bold hover:border-ink transition">{{ __('webapp.feed.load_more') }}</button>
        </div>
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
                const res = this.tab === 'saved' ? await this.fetchSaved(page) : await this.fetchDiscovery(page);
                if (!res.ok) {
                    this.error = window.kb.errorText(res, t('feed.load_error'));
                    this.loading = false;
                    if (page === 1) this.cards = [];
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

            /** Fold the discovery + kolab payloads into one card view-model. */
            normalize(k, source) {
                const chips = source === 'discovery' ? this.discoveryChips(k) : this.kolabChips(k);
                const creator = k.creator_profile || {};
                return {
                    id: k.id,
                    name: creator.display_name || t('feed.a_partner'),
                    img: k.cover_photo_url || k.offer_photo || (k.media || [])[0]?.url || '',
                    meta: this.metaLabel(k, source),
                    city: k.preferred_city || k.area || creator.city?.name || '',
                    offer: k.offer_headline || k.title || '',
                    chips: chips.slice(0, 3),
                    match: source === 'discovery' ? (k.match_score || 0) : 0,
                };
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
