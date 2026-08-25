@extends('webapp.layout')
@section('title', __('webapp.events.title'))

@section('body')
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), eventsPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'events'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[760px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        <div class="flex items-center justify-between gap-3 flex-wrap">
            <h1 class="font-anton text-[28px] tracking-[1px] text-ink">{{ __('webapp.events.title') }}</h1>
            <button type="button" @click="openCreate()" x-show="communities.length > 0" x-cloak
                    class="kb-on-yellow h-10 px-5 rounded-pill bg-primary text-ink text-[13px] font-bold shadow-btn hover:bg-primary-dark transition">
                + {{ __('webapp.events.new') }}
            </button>
        </div>
        <p class="mt-1 text-[13.5px] text-muted">{{ __('webapp.events.lede') }}</p>

        <div class="mt-5 flex p-1 bg-white border border-ink/[.12] rounded-pill w-fit">
            <template x-for="tb in [{ v: 'upcoming', l: t('events.tab_upcoming') }, { v: 'past', l: t('events.tab_past') }]" :key="tb.v">
                <button type="button" @click="setTab(tb.v)"
                        class="h-9 px-5 rounded-pill text-[13px] font-bold transition"
                        :class="tab === tb.v ? 'kb-on-yellow bg-primary text-ink' : 'text-muted hover:text-ink'"
                        x-text="tb.l"></button>
            </template>
        </div>

        <template x-if="error">
            <div class="mt-5 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
        </template>
        <template x-if="loading">
            <p class="mt-6 text-muted">{{ __('webapp.common.loading') }}</p>
        </template>
        <template x-if="!loading && events.length === 0 && !error">
            <div class="mt-6 rounded-2xl border-[1.5px] border-dashed border-ink/20 py-12 px-6 text-center">
                <p class="text-sm text-muted" x-text="communities.length === 0 ? t('events.empty_no_community') : t('events.empty')"></p>
            </div>
        </template>

        <div class="mt-5 flex flex-col gap-2.5">
            <template x-for="ev in events" :key="ev.id">
                <a :href="window.kbPath('/events/' + ev.id)"
                   class="block bg-white border border-ink/[.08] rounded-2xl p-4 hover:border-ink/25 transition">
                    <div class="flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-[14.5px] font-bold text-ink truncate" x-text="ev.name"></p>
                            <p class="mt-0.5 text-[12.5px] text-muted">
                                <span x-text="when(ev)"></span>
                                <template x-if="ev.location"><span> · <span x-text="ev.location"></span></span></template>
                            </p>
                        </div>
                        <template x-if="ev.checkin?.is_open">
                            <span class="shrink-0 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-pill bg-ok-surface text-ok-ink text-[11px] font-bold">
                                <span class="w-1.5 h-1.5 rounded-full bg-ok-ink"></span>{{ __('webapp.events.door_open') }}
                            </span>
                        </template>
                    </div>

                    {{-- The number the whole product exists to produce: claimed against
                         scanned. An organiser reads it as their own no-show rate. --}}
                    <div class="mt-3 pt-3 border-t border-ink/[.06] grid grid-cols-3 gap-3 text-center">
                        <div>
                            <p class="font-anton text-[18px] text-ink" x-text="ev.going_count ?? 0"></p>
                            <p class="text-[10px] font-semibold tracking-[.1em] uppercase text-muted">{{ __('webapp.events.signed_up') }}</p>
                        </div>
                        <div>
                            <p class="font-anton text-[18px] text-ink" x-text="ev.checkin?.checked_in_count ?? 0"></p>
                            <p class="text-[10px] font-semibold tracking-[.1em] uppercase text-muted">{{ __('webapp.events.showed_up') }}</p>
                        </div>
                        <div>
                            <p class="font-anton text-[18px]" :class="turnoutClass(ev)" x-text="turnout(ev)"></p>
                            <p class="text-[10px] font-semibold tracking-[.1em] uppercase text-muted">{{ __('webapp.events.turnout') }}</p>
                        </div>
                    </div>
                </a>
            </template>
        </div>

        <div class="mt-7 text-center" x-show="!loading && page < lastPage" x-cloak>
            <button type="button" @click="load(page + 1)"
                    class="h-11 px-6 rounded-pill bg-white border border-line text-sm font-bold hover:border-ink transition">{{ __('webapp.feed.load_more') }}</button>
        </div>
    </div>
    </main>

    {{-- ── New event ──────────────────────────────────────────────────── --}}
    <div x-show="creating" x-cloak @click="creating = false"
         class="kb-overlay fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-8">
        <div @click.stop class="bg-white rounded-[22px] w-full max-w-[460px] p-7 kb-fade-up-fast">
            <p class="text-lg font-bold text-ink">{{ __('webapp.events.new_title') }}</p>
            <p class="mt-1 text-[12.5px] text-body">{{ __('webapp.events.new_hint') }}</p>

            <form @submit.prevent="create()" class="mt-5 flex flex-col gap-3.5">
                <div x-show="communities.length > 1" x-cloak>
                    <label class="text-[11px] font-semibold tracking-[.1em] uppercase text-muted">{{ __('webapp.events.community') }}</label>
                    <select x-model="form.community_id"
                            class="mt-1.5 w-full h-11 px-3 rounded-xl bg-cream-low border border-transparent focus:border-ink/20 focus:bg-white text-[13.5px] outline-none transition">
                        <template x-for="c in communities" :key="c.id">
                            <option :value="c.id" x-text="c.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="text-[11px] font-semibold tracking-[.1em] uppercase text-muted">{{ __('webapp.events.name') }}</label>
                    <input type="text" x-model="form.name" maxlength="100" placeholder="{{ __('webapp.events.name_ph') }}"
                           class="mt-1.5 w-full h-11 px-4 rounded-xl bg-cream-low border border-transparent focus:border-ink/20 focus:bg-white text-[13.5px] outline-none transition">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[11px] font-semibold tracking-[.1em] uppercase text-muted">{{ __('webapp.events.starts') }}</label>
                        <input type="datetime-local" x-model="form.starts_at"
                               class="mt-1.5 w-full h-11 px-3 rounded-xl bg-cream-low border border-transparent focus:border-ink/20 focus:bg-white text-[13px] outline-none transition">
                    </div>
                    <div>
                        <label class="text-[11px] font-semibold tracking-[.1em] uppercase text-muted">{{ __('webapp.events.ends') }}</label>
                        <input type="datetime-local" x-model="form.ends_at"
                               class="mt-1.5 w-full h-11 px-3 rounded-xl bg-cream-low border border-transparent focus:border-ink/20 focus:bg-white text-[13px] outline-none transition">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[11px] font-semibold tracking-[.1em] uppercase text-muted">{{ __('webapp.events.location') }}</label>
                        <input type="text" x-model="form.location" maxlength="255"
                               class="mt-1.5 w-full h-11 px-4 rounded-xl bg-cream-low border border-transparent focus:border-ink/20 focus:bg-white text-[13.5px] outline-none transition">
                    </div>
                    <div>
                        <label class="text-[11px] font-semibold tracking-[.1em] uppercase text-muted">{{ __('webapp.events.capacity') }}</label>
                        <input type="number" min="1" x-model="form.capacity"
                               class="mt-1.5 w-full h-11 px-4 rounded-xl bg-cream-low border border-transparent focus:border-ink/20 focus:bg-white text-[13.5px] outline-none transition">
                    </div>
                </div>

                <template x-if="createError">
                    <p class="text-[12.5px] text-bad-ink whitespace-pre-line" x-text="createError"></p>
                </template>

                <div class="flex gap-2.5 mt-1">
                    <button type="button" @click="creating = false"
                            class="flex-1 h-11 rounded-pill bg-white border border-line text-[13px] font-bold hover:border-ink transition">{{ __('webapp.common.cancel') }}</button>
                    <button type="submit" :disabled="busy || !form.name.trim() || !form.starts_at || !form.community_id"
                            class="kb-on-yellow flex-1 h-11 rounded-pill bg-primary text-ink text-[13px] font-bold shadow-btn hover:bg-primary-dark transition disabled:opacity-50"
                            x-text="busy ? t('common.saving') : t('events.create')"></button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function eventsPage() {
        return {
            events: [], communities: [], loading: true, error: '',
            tab: 'upcoming', page: 1, lastPage: 1,
            creating: false, busy: false, createError: '',
            form: { community_id: '', name: '', starts_at: '', ends_at: '', location: '', capacity: '' },

            when(ev) {
                if (ev.starts_at) return window.kbDateTime(ev.starts_at);
                return ev.date ? window.kbDateShort(ev.date) : '';
            },
            /** Scanned over signed-up. Blank until there is something to compare. */
            turnout(ev) {
                const going = ev.going_count ?? 0;
                const came = ev.checkin?.checked_in_count ?? 0;
                if (going === 0) return came > 0 ? '—' : '—';
                return Math.round((came / going) * 100) + '%';
            },
            turnoutClass(ev) {
                const going = ev.going_count ?? 0;
                if (going === 0) return 'text-muted';
                const ratio = (ev.checkin?.checked_in_count ?? 0) / going;
                if (ratio >= 0.7) return 'text-ok-ink';
                if (ratio >= 0.4) return 'text-ink';
                return 'text-warn-ink';
            },

            async init() {
                if (!window.kb.requireAuth()) return;
                if (!await this.loadShell()) return;
                await Promise.all([this.loadCommunities(), this.load(1)]);
            },

            async loadCommunities() {
                const res = await window.kb.api('/me/communities');
                this.communities = res.ok ? window.kb.rows(res) : [];
                if (this.communities.length > 0) this.form.community_id = this.communities[0].id;
            },

            setTab(tab) {
                if (this.tab === tab) return;
                this.tab = tab;
                this.load(1);
            },

            async load(page) {
                this.loading = true;
                this.error = '';
                const res = await window.kb.api('/events?time=' + this.tab + '&limit=20&page=' + page);
                this.loading = false;
                if (!res.ok) { this.error = window.kb.errorText(res, t('events.load_error')); return; }

                // This endpoint nests under data.events with its own pagination block,
                // which kb.rows()/kb.meta() cannot find.
                const rows = res.json?.data?.events || [];
                this.events = page === 1 ? rows : this.events.concat(rows);
                const p = res.json?.data?.pagination || {};
                this.page = p.current_page || page;
                this.lastPage = p.total_pages || this.page;
            },

            openCreate() {
                this.creating = true;
                this.createError = '';
                this.form.name = '';
                this.form.starts_at = '';
                this.form.ends_at = '';
                this.form.location = '';
                this.form.capacity = '';
            },

            async create() {
                this.busy = true;
                this.createError = '';
                const body = {
                    community_id: this.form.community_id,
                    name: this.form.name.trim(),
                    starts_at: this.form.starts_at,
                };
                if (this.form.ends_at) body.ends_at = this.form.ends_at;
                if (this.form.location) body.location = this.form.location;
                if (this.form.capacity) body.capacity = Number(this.form.capacity);

                const res = await window.kb.api('/events', { method: 'POST', body });
                this.busy = false;
                if (!res.ok) { this.createError = window.kb.errorText(res, t('events.create_error')); return; }

                const created = res.json?.data;
                // Straight to the door — creating an event is never the goal in itself.
                if (created?.id) { window.nav('/events/' + created.id); return; }
                this.creating = false;
                this.load(1);
            },
        };
    }
</script>
@endpush
@endsection
