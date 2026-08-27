@extends('webapp.layout')
@section('title', __('webapp.mke.detail_title'))

@section('body')
{{--
    The organizer's board for one multi-Kolab event.

    Two calls, because the API splits them and each answers a different question:
    `GET /multi-kolab-events/{id}` is what the event IS (title, date, city, the
    roles), and `GET /multi-kolab-events/{id}/dashboard` is where it STANDS (roles
    open vs filled, and per role how many applications sit at each stage). The page
    is laid out in that order for the same reason.

    Read-only in this pass. Reviewing an applicant, editing a role and
    publish/confirm/complete are write flows the API already has and this page
    deliberately does not: shipping the board first means an organizer can see where
    things stand today, and every action arrives against a surface that already
    proved it reads the right numbers.
--}}
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), multiKolabEventDetailPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'multi-kolab-events'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[820px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        <a :href="window.kbPath('/multi-kolab-events')"
           class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-muted hover:text-ink transition">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            {{ __('webapp.mke.back') }}
        </a>

        <template x-if="error">
            <div class="mt-6 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
        </template>

        <p x-show="loading" x-cloak class="mt-8 text-sm text-muted">{{ __('webapp.common.loading') }}</p>

        <template x-if="!loading && event">
            <div>
                {{-- ── What it is ──────────────────────────────────────── --}}
                <header class="mt-5">
                    <div class="flex items-start justify-between gap-4">
                        <h1 class="font-anton text-[28px] md:text-[32px] leading-tight tracking-[1px] text-ink" x-text="event.title"></h1>
                        <span class="px-3 py-1 rounded-xl text-[11px] font-bold tracking-[.4px] shrink-0 mt-1"
                              :style="`background:${window.kbStatus(event.status).bg};color:${window.kbStatus(event.status).c}`"
                              x-text="window.kbStatus(event.status).label"></span>
                    </div>

                    <p class="mt-2 text-[14px] text-body leading-relaxed" x-show="event.value_summary" x-cloak x-text="event.value_summary"></p>

                    <div class="mt-4 flex items-center gap-2 flex-wrap">
                        <span class="px-2.5 py-[5px] rounded-lg bg-cream-input text-[12px] font-medium text-body"
                              x-show="event.event_date" x-cloak x-text="window.kbDate(event.event_date)"></span>
                        <span class="px-2.5 py-[5px] rounded-lg bg-cream-input text-[12px] font-medium text-body"
                              x-show="event.city" x-cloak x-text="event.city"></span>
                        <span class="px-2.5 py-[5px] rounded-lg bg-cream-input text-[12px] font-medium text-body"
                              x-show="event.category" x-cloak x-text="event.category"></span>
                    </div>
                </header>

                {{-- ── Where it stands ─────────────────────────────────── --}}
                <div class="mt-7 grid grid-cols-3 gap-3">
                    <template x-for="tile in roleTiles" :key="tile.key">
                        <div class="bg-white border border-ink/[.08] rounded-2xl px-4 py-4 shadow-card">
                            <p class="font-anton text-[26px] leading-none text-ink" x-text="tile.value"></p>
                            <p class="text-[11px] font-semibold tracking-[.1em] uppercase text-muted mt-2" x-text="tile.label"></p>
                        </div>
                    </template>
                </div>

                {{-- ── The roles ───────────────────────────────────────── --}}
                <p class="mt-8 text-[13px] font-semibold tracking-[1px] uppercase text-ink">{{ __('webapp.mke.roles') }}</p>

                <template x-if="roles.length === 0">
                    <div class="mt-3 rounded-2xl border-[1.5px] border-dashed border-ink/20 py-10 px-5 text-center text-sm text-muted">
                        {{ __('webapp.mke.roles_empty') }}
                    </div>
                </template>

                <div class="mt-3 flex flex-col gap-3">
                    <template x-for="r in roles" :key="r.role_id">
                        <div class="bg-white border border-ink/[.08] rounded-2xl p-5 shadow-card">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="text-[15px] font-bold text-ink truncate" x-text="r.title"></p>
                                    {{-- Positions as a fraction: "1 of 2 filled" is the
                                         only form that says whether this role still needs
                                         somebody. --}}
                                    <p class="text-[12.5px] text-muted mt-1"
                                       x-text="t('mke.positions', { filled: r.positions_filled ?? 0, needed: r.positions_needed ?? 0 })"></p>
                                </div>
                                <span class="px-3 py-1 rounded-xl text-[11px] font-bold tracking-[.4px] shrink-0"
                                      :style="`background:${window.kbStatus(r.status).bg};color:${window.kbStatus(r.status).c}`"
                                      x-text="window.kbStatus(r.status).label"></span>
                            </div>

                            {{-- Application counts, and only the stages that have
                                 anything in them: four zeroes in a row is noise, and
                                 the point of this line is to show where a decision is
                                 waiting. --}}
                            <div class="mt-4 flex items-center gap-2 flex-wrap" x-show="applicationChips(r).length" x-cloak>
                                <template x-for="chip in applicationChips(r)" :key="chip.key">
                                    <span class="px-2.5 py-[5px] rounded-lg text-[12px] font-semibold"
                                          :style="`background:${chip.bg};color:${chip.c}`"
                                          x-text="chip.label"></span>
                                </template>
                            </div>

                            <p class="mt-4 text-[12.5px] text-muted" x-show="!applicationChips(r).length" x-cloak>
                                {{ __('webapp.mke.no_applications') }}
                            </p>
                        </div>
                    </template>
                </div>
            </div>
        </template>

    </div>
    </main>
</div>
@endsection

@push('scripts')
<script>
    function multiKolabEventDetailPage() {
        return {
            t: (key, params) => window.t(key, params),

            /** The id sits after the locale prefix, so slice KB_BASE off first. */
            id: location.pathname.slice((window.KB_BASE || '').length).split('/')[2],

            event: null,
            board: null,
            loading: true,
            error: '',

            async init() {
                if (!window.kb.requireAuth()) return;
                const me = await this.loadShell();
                if (!me) return;
                await this.load();
            },

            async load() {
                this.loading = true;

                const [detail, board] = await Promise.all([
                    window.kb.api('/multi-kolab-events/' + this.id),
                    window.kb.api('/multi-kolab-events/' + this.id + '/dashboard'),
                ]);

                this.loading = false;

                if (!detail.ok) {
                    this.error = window.kb.errorText(detail, window.t('mke.load_error'));
                    return;
                }

                this.event = detail.json?.data || null;

                /*
                 * The board is the organizer's view and 403s for anyone else, which is
                 * not an error worth showing: the page still has the event. Roles then
                 * come from the detail payload, without the application counts — see
                 * `roles` below.
                 */
                this.board = board.ok ? (board.json?.data || null) : null;
            },

            get roleTiles() {
                const counts = this.board?.role_counts ?? this.event?.role_counts ?? {};
                return [
                    { key: 'total', value: counts.total ?? 0, label: window.t('mke.tile_roles') },
                    { key: 'open', value: counts.open ?? 0, label: window.t('mke.tile_open') },
                    { key: 'filled', value: counts.filled ?? 0, label: window.t('mke.tile_filled') },
                ];
            },

            /**
             * Prefer the board's roles: they are the same roles plus the application
             * counts. Falling back to the detail payload means a viewer who is not the
             * organizer still sees the roles, just without counts they should not have.
             */
            get roles() {
                if (Array.isArray(this.board?.roles) && this.board.roles.length) return this.board.roles;
                return (this.event?.roles ?? []).map((r) => ({
                    role_id: r.id,
                    title: r.title,
                    positions_needed: r.positions_needed,
                    positions_filled: r.positions_filled,
                    status: r.status,
                    application_counts: null,
                }));
            },

            /**
             * One chip per application stage that actually has applications.
             *
             * `withdrawn` and `declined` are included when non-zero — an organizer
             * reading a role with two declines and nothing pending is looking at a
             * role that needs re-advertising, and hiding that tells them nothing is
             * happening when something already did.
             */
            applicationChips(role) {
                const counts = role?.application_counts;
                if (!counts) return [];

                return ['pending', 'shortlisted', 'accepted', 'declined', 'withdrawn']
                    .filter((stage) => (counts[stage] ?? 0) > 0)
                    .map((stage) => {
                        const tone = window.kbStatus(stage);
                        return {
                            key: stage,
                            bg: tone.bg,
                            c: tone.c,
                            label: counts[stage] + ' ' + window.tOr('status.' + stage, stage),
                        };
                    });
            },
        };
    }
</script>
@endpush
