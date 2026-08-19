@extends('webapp.layout')
@section('title', __('webapp.profile.title'))

@section('body')
{{-- Public profile of a business or a community, as seen from inside the app:
     everything the marketing teaser withholds (full reviews with their authors,
     contact links, past-event detail, partners) lives here. --}}
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), profilePage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => ''])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[820px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        <template x-if="pageError">
            <div class="rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="pageError"></div>
        </template>
        <template x-if="loading">
            <p class="text-muted">{{ __('webapp.common.loading') }}</p>
        </template>

        <template x-if="!loading && p">
            <div>
                {{-- ── Identity ─────────────────────────────────────────── --}}
                <header class="flex flex-col sm:flex-row sm:items-center gap-5">
                    <div class="w-[92px] h-[92px] rounded-3xl bg-primary/40 overflow-hidden shrink-0 flex items-center justify-center text-3xl font-semibold text-ink">
                        <template x-if="p.avatar_url"><img :src="p.avatar_url" :alt="p.display_name" class="w-full h-full object-cover"></template>
                        <template x-if="!p.avatar_url"><span x-text="initialOf(p.display_name)"></span></template>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="font-anton text-[28px] tracking-[1px] text-ink" x-text="p.display_name"></h1>
                            <span x-show="p.is_verified" x-cloak
                                  class="inline-flex items-center gap-1 px-2 py-[3px] rounded-pill bg-ok-surface text-ok-ink text-[11px] font-bold"
                                  :title="t('profile.verified')">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                {{ __('webapp.profile.verified') }}
                            </span>
                        </div>
                        <p class="mt-1 text-[13.5px] text-muted">
                            <span x-text="typeLabel"></span><template x-if="p.city_name"><span> · <span x-text="p.city_name"></span></span></template>
                        </p>

                        <div class="mt-3 flex items-center gap-2 flex-wrap">
                            <a x-show="isMe" x-cloak :href="window.kbPath('/account')"
                               class="h-9 px-4 rounded-pill bg-white border border-line text-[13px] font-bold hover:border-ink transition inline-flex items-center">{{ __('webapp.profile.edit') }}</a>
                            <button type="button" @click="copyPublicLink()" x-show="p.public_url" x-cloak
                                    class="h-9 px-4 rounded-pill bg-white border border-line text-[13px] font-bold hover:border-ink transition"
                                    x-text="copied ? t('profile.shared') : t('profile.share')"></button>
                        </div>
                        <p x-show="isMe" x-cloak class="mt-2 text-[12px] text-muted">{{ __('webapp.profile.public_hint') }}</p>
                    </div>
                </header>

                {{-- ── Headline numbers ─────────────────────────────────── --}}
                <div class="mt-7 grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    <template x-for="stat in stats" :key="stat.label">
                        <div class="rounded-2xl border border-ink/[.08] bg-white px-3 py-4 text-center">
                            <p class="font-anton text-[22px] text-ink" x-text="stat.value"></p>
                            <p class="mt-0.5 text-[10.5px] font-semibold tracking-[.1em] uppercase text-muted" x-text="stat.label"></p>
                        </div>
                    </template>
                </div>

                {{-- ── Links (withheld from the public page) ────────────── --}}
                <template x-if="p.instagram || p.tiktok || p.website">
                    <section class="mt-7">
                        <h2 class="font-anton text-[17px] tracking-[.5px] text-ink">{{ __('webapp.profile.contact') }}</h2>
                        <div class="mt-2.5 flex flex-wrap gap-2">
                            <template x-if="p.instagram">
                                <a :href="'https://instagram.com/' + String(p.instagram).replace('@','')" target="_blank" rel="noopener noreferrer"
                                   class="h-9 px-4 rounded-pill bg-white border border-line text-[13px] font-semibold hover:border-ink transition inline-flex items-center gap-1.5">
                                    Instagram <span class="text-muted" x-text="'@' + String(p.instagram).replace('@','')"></span>
                                </a>
                            </template>
                            <template x-if="p.tiktok">
                                <a :href="'https://tiktok.com/@' + String(p.tiktok).replace('@','')" target="_blank" rel="noopener noreferrer"
                                   class="h-9 px-4 rounded-pill bg-white border border-line text-[13px] font-semibold hover:border-ink transition inline-flex items-center">TikTok</a>
                            </template>
                            <template x-if="p.website">
                                <a :href="p.website" target="_blank" rel="noopener noreferrer"
                                   class="h-9 px-4 rounded-pill bg-white border border-line text-[13px] font-semibold hover:border-ink transition inline-flex items-center">{{ __('webapp.profile.website') }}</a>
                            </template>
                        </div>
                    </section>
                </template>

                {{-- ── About ────────────────────────────────────────────── --}}
                <template x-if="p.about">
                    <section class="mt-7">
                        <h2 class="font-anton text-[17px] tracking-[.5px] text-ink">{{ __('webapp.profile.about') }}</h2>
                        <p class="mt-2 text-[14px] leading-relaxed text-body whitespace-pre-line" x-text="p.about"></p>
                    </section>
                </template>

                {{-- ── Rating breakdown ─────────────────────────────────── --}}
                <template x-if="breakdown.length > 0">
                    <section class="mt-7">
                        <h2 class="font-anton text-[17px] tracking-[.5px] text-ink">{{ __('webapp.profile.breakdown') }}</h2>
                        <div class="mt-3 flex flex-col gap-2">
                            <template x-for="row in breakdown" :key="row.key">
                                <div class="flex items-center gap-3">
                                    <span class="w-[104px] shrink-0 text-[12.5px] text-body" x-text="row.label"></span>
                                    <div class="flex-1 h-2 rounded-pill bg-cream-low overflow-hidden">
                                        <div class="h-full rounded-pill bg-primary" :style="`width:${(row.value / 5) * 100}%`"></div>
                                    </div>
                                    <span class="w-8 shrink-0 text-right text-[12.5px] font-semibold text-ink" x-text="row.value.toFixed(1)"></span>
                                </div>
                            </template>
                        </div>
                    </section>
                </template>

                {{-- ── Reviews & feedback ───────────────────────────────── --}}
                <section class="mt-8">
                    <h2 class="font-anton text-[17px] tracking-[.5px] text-ink">{{ __('webapp.profile.reviews') }}</h2>

                    <template x-if="reviewsError">
                        <div class="mt-3 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3" x-text="reviewsError"></div>
                    </template>
                    <template x-if="!reviewsLoading && reviews.length === 0 && !reviewsError">
                        <p class="mt-3 text-sm text-muted">{{ __('webapp.profile.reviews_empty') }}</p>
                    </template>

                    <div class="mt-3 flex flex-col gap-2.5">
                        <template x-for="r in reviews" :key="r.id">
                            <article class="rounded-2xl border border-ink/[.08] bg-white p-4">
                                <div class="flex items-start gap-3">
                                    <div class="w-9 h-9 rounded-full bg-primary/40 overflow-hidden shrink-0 flex items-center justify-center text-[13px] font-semibold text-ink">
                                        <template x-if="r.reviewer?.avatar_url"><img :src="r.reviewer.avatar_url" alt="" class="w-full h-full object-cover"></template>
                                        <template x-if="!r.reviewer?.avatar_url"><span x-text="initialOf(r.reviewer?.display_name)"></span></template>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-baseline gap-2 flex-wrap">
                                            <a :href="r.reviewer?.id ? window.kbPath('/profiles/' + r.reviewer.id) : '#'"
                                               class="text-[13.5px] font-semibold text-ink hover:underline" x-text="r.reviewer?.display_name || '—'"></a>
                                            <span class="text-[11px] text-muted" x-text="dateShort(r.created_at)"></span>
                                        </div>
                                        <p class="mt-0.5 text-[13px]" :aria-label="(r.overall_rating || r.rating) + ' / 5'">
                                            <span class="text-amber" x-text="stars(r.overall_rating || r.rating)"></span>
                                        </p>
                                        <p x-show="r.public_comment" x-cloak class="mt-1.5 text-[13.5px] leading-relaxed text-body whitespace-pre-line" x-text="r.public_comment"></p>
                                        <div class="mt-2 flex items-center gap-2 flex-wrap">
                                            <span class="px-2 py-[3px] rounded-pill bg-cream-low text-[10.5px] font-semibold text-muted">{{ __('webapp.profile.review_verified') }}</span>
                                            <span x-show="r.would_collaborate_again" x-cloak
                                                  class="px-2 py-[3px] rounded-pill bg-ok-surface text-ok-ink text-[10.5px] font-semibold">{{ __('webapp.profile.review_again') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </template>
                    </div>

                    <div class="mt-4 text-center" x-show="reviewsPage < reviewsLastPage" x-cloak>
                        <button type="button" @click="loadReviews(reviewsPage + 1)" :disabled="reviewsLoading"
                                class="h-10 px-5 rounded-pill bg-white border border-line text-[13px] font-bold hover:border-ink transition disabled:opacity-50">{{ __('webapp.profile.load_more') }}</button>
                    </div>
                </section>

                {{-- ── Past events ──────────────────────────────────────── --}}
                <section class="mt-8">
                    <h2 class="font-anton text-[17px] tracking-[.5px] text-ink">{{ __('webapp.profile.past_events') }}</h2>
                    <template x-if="pastEvents.length === 0">
                        <p class="mt-3 text-sm text-muted">{{ __('webapp.profile.past_events_empty') }}</p>
                    </template>
                    <div class="mt-3 grid sm:grid-cols-2 gap-2.5">
                        <template x-for="(ev, i) in pastEvents" :key="i">
                            <article class="rounded-2xl border border-ink/[.08] bg-white overflow-hidden">
                                <template x-if="eventImages(ev).length > 0">
                                    <div class="flex gap-px">
                                        <template x-for="(src, j) in eventImages(ev).slice(0, 3)" :key="j">
                                            <img :src="src" alt="" class="flex-1 min-w-0 h-24 object-cover cursor-zoom-in" @click="lightbox = src">
                                        </template>
                                    </div>
                                </template>
                                <div class="p-3.5">
                                    <p class="text-[13.5px] font-semibold text-ink" x-text="ev.name || '—'"></p>
                                    <p class="mt-0.5 text-[12px] text-muted">
                                        <span x-text="ev.date ? dateShort(ev.date) : ''"></span>
                                        <template x-if="ev.partner_name">
                                            <span x-text="(ev.date ? ' · ' : '') + t('profile.with_partner', { name: ev.partner_name })"></span>
                                        </template>
                                    </p>
                                </div>
                            </article>
                        </template>
                    </div>
                </section>

                {{-- ── Past collaborations ──────────────────────────────── --}}
                <section class="mt-8">
                    <h2 class="font-anton text-[17px] tracking-[.5px] text-ink">{{ __('webapp.profile.collaborations') }}</h2>
                    <template x-if="!collabsLoading && collabs.length === 0">
                        <p class="mt-3 text-sm text-muted">{{ __('webapp.profile.collaborations_empty') }}</p>
                    </template>
                    <div class="mt-3 flex flex-col gap-2">
                        <template x-for="c in collabs" :key="c.id">
                            <div class="flex items-center gap-3 rounded-2xl border border-ink/[.08] bg-white px-4 py-3">
                                <div class="w-9 h-9 rounded-full bg-primary/40 overflow-hidden shrink-0 flex items-center justify-center text-[13px] font-semibold text-ink">
                                    <template x-if="c.partner_avatar_url"><img :src="c.partner_avatar_url" alt="" class="w-full h-full object-cover"></template>
                                    <template x-if="!c.partner_avatar_url"><span x-text="initialOf(c.partner_name)"></span></template>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[13.5px] font-semibold text-ink truncate" x-text="c.title || '—'"></p>
                                    <p class="text-[12px] text-muted truncate" x-text="c.partner_name ? t('profile.with_partner', { name: c.partner_name }) : ''"></p>
                                </div>
                                <span class="shrink-0 text-[11px] text-muted" x-text="c.completed_at ? dateShort(c.completed_at) : ''"></span>
                            </div>
                        </template>
                    </div>
                    <div class="mt-4 text-center" x-show="collabsPage < collabsLastPage" x-cloak>
                        <button type="button" @click="loadCollabs(collabsPage + 1)" :disabled="collabsLoading"
                                class="h-10 px-5 rounded-pill bg-white border border-line text-[13px] font-bold hover:border-ink transition disabled:opacity-50">{{ __('webapp.profile.load_more') }}</button>
                    </div>
                </section>

                {{-- ── Photos ───────────────────────────────────────────── --}}
                <section class="mt-8">
                    <h2 class="font-anton text-[17px] tracking-[.5px] text-ink">{{ __('webapp.profile.photos') }}</h2>
                    <template x-if="photos.length === 0">
                        <p class="mt-3 text-sm text-muted">{{ __('webapp.profile.photos_empty') }}</p>
                    </template>
                    <div class="mt-3 grid grid-cols-3 sm:grid-cols-4 gap-2">
                        <template x-for="(ph, i) in photos" :key="i">
                            <img :src="ph.url" alt="" loading="lazy" @click="lightbox = ph.url"
                                 class="aspect-square w-full rounded-xl object-cover cursor-zoom-in hover:opacity-90 transition">
                        </template>
                    </div>
                </section>
            </div>
        </template>
    </div>
    </main>

    {{-- Lightbox --}}
    <div x-show="lightbox" x-cloak @click="lightbox = ''" class="kb-overlay fixed inset-0 z-[80] flex items-center justify-center p-6">
        <img :src="lightbox" alt="" class="max-h-[86vh] max-w-full rounded-2xl object-contain">
    </div>
</div>

@push('scripts')
<script>
    function profilePage() {
        return {
            p: null, loading: true, pageError: '',
            reviews: [], reviewsPage: 1, reviewsLastPage: 1, reviewsLoading: false, reviewsError: '',
            collabs: [], collabsPage: 1, collabsLastPage: 1, collabsLoading: false,
            reputation: null, lightbox: '', copied: false,

            id: location.pathname.slice((window.KB_BASE || '').length).split('/')[2],

            get isMe() { return !!this.me && !!this.p && this.me.id === this.p.id; },
            get photos() { return this.p?.photos || []; },
            get pastEvents() { return this.p?.past_events || []; },
            get typeLabel() {
                const raw = this.p?.business_type || this.p?.community_type || this.p?.type;
                if (!raw) return this.p?.user_type === 'business' ? t('nav.role_business') : t('nav.role_community');
                return String(raw).replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
            },
            get stats() {
                const s = this.p?.public_stats || {};
                const rating = this.reputation?.average_rating;
                return [
                    { label: t('profile.stat_kolabs'), value: s.completed_collaborations_count ?? 0 },
                    { label: t('profile.stat_events'), value: s.past_events_count ?? 0 },
                    { label: t('profile.stat_reviews'), value: this.reputation?.review_count ?? 0 },
                    { label: t('profile.stat_rating'), value: rating ? Number(rating).toFixed(1) : '—' },
                ];
            },
            /** The five category averages, only when the backend actually has them. */
            get breakdown() {
                const b = this.reputation?.breakdown;
                if (!b) return [];
                return ['communication', 'reliability', 'fit', 'value', 'repeat']
                    .filter(k => typeof b[k] === 'number')
                    .map(k => ({ key: k, label: t('profile.breakdown_' + k), value: b[k] }));
            },

            initialOf(v) { return window.kbInitial(v); },
            dateShort(iso) { return iso ? window.kbDateShort(iso) : ''; },
            stars(n) {
                const filled = Math.round(Number(n) || 0);
                return '★'.repeat(Math.max(0, Math.min(5, filled)));
            },
            /** Past-event media is a mixed image/video collection; show the stills. */
            eventImages(ev) {
                return (ev.media || [])
                    .map(m => (m && m.type === 'image' ? m.url : m?.thumbnail_url))
                    .filter(Boolean);
            },

            async init() {
                if (!window.kb.requireAuth()) return;
                if (!await this.loadShell()) return;

                const [detail, base] = await Promise.all([
                    window.kb.api('/profiles/' + this.id + '/public-profile'),
                    window.kb.api('/profiles/' + this.id),
                ]);

                if (!detail.ok) {
                    this.loading = false;
                    this.pageError = detail.status === 404
                        ? t('profile.not_found')
                        : window.kb.errorText(detail, t('profile.load_error'));
                    return;
                }

                this.p = detail.json?.data || null;
                // The rich endpoint has no reputation block; /profiles/{id} does.
                this.reputation = base.ok ? (base.json?.data?.reputation || null) : null;
                this.loading = false;

                await Promise.all([this.loadReviews(1), this.loadCollabs(1)]);
            },

            async loadReviews(page) {
                this.reviewsLoading = true;
                this.reviewsError = '';
                const res = await window.kb.api('/profiles/' + this.id + '/reviews?per_page=10&page=' + page);
                this.reviewsLoading = false;
                if (!res.ok) { this.reviewsError = window.kb.errorText(res, t('profile.reviews_error')); return; }
                const rows = window.kb.rows(res);
                this.reviews = page === 1 ? rows : this.reviews.concat(rows);
                const meta = window.kb.meta(res);
                this.reviewsPage = meta.current_page || page;
                this.reviewsLastPage = meta.last_page || this.reviewsPage;
            },

            async loadCollabs(page) {
                this.collabsLoading = true;
                const res = await window.kb.api('/profiles/' + this.id + '/collaborations?per_page=10&page=' + page);
                this.collabsLoading = false;
                if (!res.ok) return;
                const rows = window.kb.rows(res);
                this.collabs = page === 1 ? rows : this.collabs.concat(rows);
                const meta = window.kb.meta(res);
                this.collabsPage = meta.current_page || page;
                this.collabsLastPage = meta.last_page || this.collabsPage;
            },

            async copyPublicLink() {
                const url = this.p?.public_url;
                if (!url) return;
                try {
                    await navigator.clipboard.writeText(url);
                } catch (e) {
                    // Clipboard is blocked in some contexts; showing the URL still
                    // lets the user copy it by hand.
                    window.prompt(t('profile.share'), url);
                }
                this.copied = true;
                setTimeout(() => { this.copied = false; }, 2000);
            },
        };
    }
</script>
@endpush
@endsection
