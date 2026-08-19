@extends('webapp.layout')
@section('title', __('webapp.suggestions.title'))

{{--
    Suggested partners (BE-NF-28) — the first human-facing surface of the nightly
    pairing batch. Reads GET /api/v1/suggestions; every sentence on a card
    (`signals[].reason`, `suggested_format.title`) already arrives rendered in the
    caller's locale from SuggestionResource, so this page never touches a signal
    key. Anything that renders blank is dropped by the API before it gets here,
    and dropped again below, so a dotted lang path can never reach the screen.

    The route is behind `feature:suggestions` (routes/web.php): with the flag off
    this page 404s exactly like the API it reads, rather than showing an empty
    state that would read as "you have no suggestions" — which would be a lie.
--}}

@section('body')
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), suggestionsPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'suggestions'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[760px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        {{-- ── Header ──────────────────────────────────────────────────── --}}
        <div>
            <h1 class="font-anton text-[28px] tracking-[1px] text-ink">{{ __('webapp.suggestions.title') }}</h1>
            <p class="text-sm text-muted mt-1" x-text="subtitle">{{ __('webapp.suggestions.subtitle_community') }}</p>
        </div>

        <template x-if="error">
            <div class="mt-5 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
        </template>
        <template x-if="loading && cards.length === 0">
            <p class="mt-8 text-muted">{{ __('webapp.common.loading') }}</p>
        </template>

        {{-- ── Empty state ─────────────────────────────────────────────────
             The common case for a new profile, so it says what would produce a
             suggestion instead of apologising — and never stands in for a card. --}}
        <template x-if="!loading && cards.length === 0 && !error">
            <div class="mt-8 rounded-[22px] border-[1.5px] border-dashed border-ink/20 px-6 py-12 text-center">
                <h2 class="font-anton text-[20px] tracking-[.5px] text-ink">{{ __('webapp.suggestions.empty_title') }}</h2>
                <p class="text-sm text-body mt-2.5 max-w-[460px] mx-auto leading-relaxed" x-text="emptyBody">&nbsp;</p>
                <a href="{{ $base }}/account"
                   class="mt-6 inline-flex h-11 px-6 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark hover:-translate-y-px transition items-center">{{ __('webapp.suggestions.empty_cta') }}</a>
            </div>
        </template>

        {{-- ── Cards ───────────────────────────────────────────────────── --}}
        <div class="flex flex-col gap-5 mt-6">
            <template x-for="c in cards" :key="c.id">
                <article class="bg-white border border-ink/[.08] rounded-[22px] shadow-card p-5 md:p-6">

                    {{-- Score + confidence, then the counterpart. --}}
                    <div class="flex items-start gap-3">
                        <div class="w-12 h-12 rounded-full bg-primary/35 flex items-center justify-center overflow-hidden shrink-0 text-lg font-semibold text-ink">
                            {{-- A blurred card has no avatar_url and no id: the placeholder is
                                 visibly withheld rather than substituted. --}}
                            <template x-if="c.blurred">
                                <div class="w-full h-full bg-primary/60 blur-sm select-none" aria-hidden="true"></div>
                            </template>
                            <template x-if="!c.blurred && c.avatar">
                                <img :src="c.avatar" :alt="c.name" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!c.blurred && !c.avatar">
                                <span x-text="c.initial">&nbsp;</span>
                            </template>
                        </div>

                        <div class="flex-1 min-w-0">
                            <template x-if="c.blurred">
                                {{-- The name is held back, so the accessible name comes from the
                                     sales copy below and the bar itself is hidden from readers. --}}
                                <p class="text-[17px] font-bold tracking-[-.3px] text-ink blur-sm select-none" aria-hidden="true">●●●●●●●●●●●</p>
                            </template>
                            <template x-if="!c.blurred">
                                <p class="text-[17px] font-bold tracking-[-.3px] text-ink truncate" x-text="c.name">&nbsp;</p>
                            </template>
                            <p class="text-[12.5px] text-muted mt-0.5" x-show="c.typeLabel" x-cloak x-text="c.typeLabel"></p>
                        </div>

                        <div class="flex flex-col items-end gap-1.5 shrink-0">
                            <span class="px-3 py-[5px] rounded-pill bg-ink text-primary text-[12px] font-bold" x-text="c.scoreLabel"></span>
                            <span x-show="c.confidenceLabel" x-cloak
                                  class="px-2.5 py-[3px] rounded-pill bg-cream-low text-[11px] font-semibold text-body" x-text="c.confidenceLabel"></span>
                        </div>
                    </div>

                    {{-- The blur is a sales moment: everything else on this card stays
                         fully readable, which is what proves the value of the name.
                         `c.blurred` is SuggestionResource's `is_identity_blurred` and
                         nothing else — the page never re-derives it from the viewer's
                         role, because a community is never blurred. --}}
                    <template x-if="c.blurred">
                        <div class="mt-4 rounded-2xl bg-primary-tint border border-primary/70 px-4 py-3.5">
                            <p class="text-[13.5px] font-bold text-ink">{{ __('webapp.suggestions.blurred_title') }}</p>
                            <p class="text-[12.5px] text-body mt-1 leading-relaxed">{{ __('webapp.suggestions.blurred_body') }}</p>
                            <a href="{{ $base }}/subscription?reason=suggestion"
                               class="mt-3 inline-flex h-9 px-4 rounded-pill bg-ink text-primary text-[12.5px] font-bold items-center hover:-translate-y-px transition">{{ __('webapp.suggestions.blurred_cta') }}</a>
                        </div>
                    </template>

                    {{-- Why this partner — sentences with real numbers, straight from the API. --}}
                    <div class="mt-5" x-show="c.signals.length" x-cloak>
                        <p class="text-[10px] font-semibold tracking-[.16em] uppercase text-muted">{{ __('webapp.suggestions.why_this') }}</p>
                        <ul class="mt-2 flex flex-col gap-2">
                            <template x-for="(sig, i) in c.signals" :key="i">
                                <li class="flex gap-2 items-start text-[13px] text-body leading-snug">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-[3px] text-amber"><path d="M20 6 9 17l-5-5"/></svg>
                                    <span>
                                        <span class="font-semibold text-ink" x-show="sig.label" x-cloak x-text="sig.label + ' — '"></span><span x-text="sig.reason"></span>
                                    </span>
                                </li>
                            </template>
                        </ul>
                    </div>

                    {{-- The proposed event. --}}
                    <div class="mt-5 rounded-2xl bg-cream-low px-4 py-3.5" x-show="c.hasFormat" x-cloak>
                        <p class="text-[10px] font-semibold tracking-[.16em] uppercase text-muted">{{ __('webapp.suggestions.format_title') }}</p>
                        <p class="text-[14.5px] font-bold text-ink mt-1.5" x-show="c.formatTitle" x-cloak x-text="c.formatTitle"></p>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1.5 text-[12.5px] text-body">
                            <span class="flex items-center gap-1.5" x-show="c.whenLine" x-cloak>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                <span x-text="c.whenLine"></span>
                            </span>
                            <span class="flex items-center gap-1.5" x-show="c.attendanceLine" x-cloak>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg>
                                <span x-text="c.attendanceLine"></span>
                            </span>
                        </div>

                        <div class="mt-3" x-show="c.offer.length" x-cloak>
                            <p class="text-[10px] font-semibold tracking-[.16em] uppercase text-muted">{{ __('webapp.suggestions.offer_title') }}</p>
                            <div class="flex gap-1.5 flex-wrap mt-1.5">
                                <template x-for="chip in c.offer" :key="chip">
                                    <span class="px-2.5 py-1 rounded-pill bg-peach text-peach-ink text-[11px] font-semibold" x-text="chip"></span>
                                </template>
                            </div>
                        </div>

                        <div class="mt-3" x-show="c.expects.length" x-cloak>
                            <p class="text-[10px] font-semibold tracking-[.16em] uppercase text-muted">{{ __('webapp.suggestions.expects_title') }}</p>
                            <div class="flex gap-1.5 flex-wrap mt-1.5">
                                <template x-for="chip in c.expects" :key="chip">
                                    <span class="px-2.5 py-1 rounded-pill bg-white border border-ink/[.12] text-[11px] font-semibold text-body" x-text="chip"></span>
                                </template>
                            </div>
                        </div>

                        <template x-for="(note, i) in c.notes" :key="i">
                            <p class="text-[12px] text-muted mt-2 leading-snug" x-text="note"></p>
                        </template>
                    </div>

                    {{-- ── Actions ─────────────────────────────────────────── --}}
                    <div class="mt-5 flex flex-col sm:flex-row gap-2.5">
                        <a :href="'{{ $base }}/kolabs/create?suggestion=' + encodeURIComponent(c.id)"
                           class="flex-1 h-11 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark hover:-translate-y-px transition flex items-center justify-center">{{ __('webapp.suggestions.create_cta') }}</a>
                        <button type="button" @click="dismiss(c)" :disabled="dismissing === c.id"
                                class="h-11 px-5 rounded-pill bg-white border border-line text-sm font-bold text-body hover:border-ink transition disabled:opacity-50">{{ __('webapp.suggestions.dismiss_cta') }}</button>
                    </div>
                </article>
            </template>
        </div>

        <div class="mt-8 text-center" x-show="!loading && page < lastPage" x-cloak>
            <button type="button" @click="load(page + 1)"
                    class="h-11 px-6 rounded-pill bg-white border border-line text-sm font-bold hover:border-ink transition">{{ __('webapp.suggestions.load_more') }}</button>
        </div>
    </div>
    </main>
</div>

@push('scripts')
<script>
    function suggestionsPage() {
        return {
            cards: [], loading: true, error: '', dismissing: '', page: 1, lastPage: 1,

            get subtitle() {
                return this.isBusiness ? t('suggestions.subtitle_business') : t('suggestions.subtitle_community');
            },
            get emptyBody() {
                return this.isBusiness ? t('suggestions.empty_body_business') : t('suggestions.empty_body_community');
            },

            async init() {
                if (!window.kb.requireAuth()) return;
                if (!await this.loadShell()) return;
                await this.load(1);
            },

            async load(page) {
                this.loading = true; this.error = '';
                const res = await window.kb.api('/suggestions?per_page=10&page=' + page);
                if (!res.ok) {
                    this.error = window.kb.errorText(res, t('suggestions.load_error'));
                    this.loading = false;
                    if (page === 1) this.cards = [];
                    return;
                }
                const mapped = window.kb.rows(res).map(r => this.normalize(r));
                this.cards = page === 1 ? mapped : this.cards.concat(mapped);
                const meta = window.kb.meta(res);
                this.page = meta.current_page || page;
                this.lastPage = meta.last_page || this.page;
                this.loading = false;
            },

            /**
             * One card view-model. Every string here is either already-rendered copy
             * from the API or a `webapp.suggestions.*` sentence — never a signal key.
             */
            normalize(s) {
                const cp = s.counterpart || {};
                const fmt = s.suggested_format || {};
                const blurred = !!s.is_identity_blurred;
                return {
                    id: s.id,
                    // A blurred card carries `id: null` for the counterpart by design
                    // (it would resolve the identity in one more request), so there is
                    // nothing to link to — and the web app has no profile page anyway.
                    blurred,
                    name: blurred ? '' : (cp.name || t('feed.a_partner')),
                    initial: blurred ? '' : window.kbInitial(cp.name || t('feed.a_partner')),
                    avatar: blurred ? '' : (cp.avatar_url || ''),
                    typeLabel: window.tOr('nav.role_' + (cp.user_type || ''), ''),
                    scoreLabel: t('suggestions.score_badge', { score: s.score ?? 0 }),
                    confidenceLabel: window.tOr('suggestions.confidence_' + (s.confidence || ''), ''),
                    // The API already drops a signal whose copy rendered blank; filter
                    // again so nothing here can ever print an empty bullet.
                    signals: (s.signals || []).filter(g => g && g.reason),
                    formatTitle: fmt.title || '',
                    whenLine: this.whenLine(fmt),
                    attendanceLine: fmt.expected_attendance
                        ? t('suggestions.expected_attendance', { count: fmt.expected_attendance })
                        : '',
                    offer: (fmt.offer || []).map(window.kbHumanize).filter(Boolean),
                    expects: (fmt.expects || []).map(window.kbHumanize).filter(Boolean),
                    notes: (fmt.notes || []).filter(Boolean),
                    get hasFormat() {
                        return !!(this.formatTitle || this.whenLine || this.attendanceLine
                            || this.offer.length || this.expects.length || this.notes.length);
                    },
                };
            },

            /** ":weekday at :time" when both are known, otherwise whichever is. */
            whenLine(fmt) {
                const day = this.weekdayLabel(fmt.weekday);
                const time = fmt.time_of_day || '';
                if (day && time) return t('suggestions.weekday_time', { weekday: day, time });
                return day || time;
            },

            /**
             * `suggested_format.weekday` is an ISO weekday (1 = Monday … 7 = Sunday),
             * the same convention as `Kolab.recurring_days`. 2024-01-01 was a Monday,
             * so the day-of-month doubles as the ISO index and Intl does the naming —
             * no seven more strings per locale to keep in sync.
             */
            weekdayLabel(iso) {
                const n = Number(iso);
                if (!Number.isInteger(n) || n < 1 || n > 7) return '';
                return new Date(Date.UTC(2024, 0, n))
                    .toLocaleDateString(window.KB_LOCALE || 'en', { weekday: 'long', timeZone: 'UTC' });
            },

            /**
             * Optimistic: the card leaves now, because a dismissal the user has to
             * watch a spinner for is worse than one that rolls back. A failure puts
             * it back where it was and says why.
             */
            async dismiss(card) {
                this.dismissing = card.id;
                this.error = '';
                const at = this.cards.findIndex(c => c.id === card.id);
                this.cards = this.cards.filter(c => c.id !== card.id);

                const res = await window.kb.api('/suggestions/' + card.id + '/dismiss', { method: 'POST' });
                this.dismissing = '';
                if (!res.ok) {
                    this.cards.splice(at < 0 ? this.cards.length : at, 0, card);
                    this.error = window.kb.errorText(res, t('suggestions.dismiss_error'));
                }
            },
        };
    }
</script>
@endpush
@endsection
