@extends('webapp.layout')
@section('title', __('webapp.events.door'))

@section('body')
{{-- The door. An organiser holds this open on a laptop or a phone at the entrance,
     so the QR and the code are the largest things on the page and the count updates
     without being asked. --}}
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), eventDoorPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'events'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[720px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        <a :href="window.kbPath('/events')" class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-muted hover:text-ink transition">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            {{ __('webapp.events.title') }}
        </a>

        <template x-if="pageError">
            <div class="mt-5 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="pageError"></div>
        </template>
        <template x-if="loading">
            <p class="mt-6 text-muted">{{ __('webapp.common.loading') }}</p>
        </template>

        <template x-if="!loading && ev">
            <div>
                <h1 class="mt-4 font-anton text-[28px] tracking-[1px] text-ink" x-text="ev.name"></h1>
                <p class="mt-1 text-[13.5px] text-muted">
                    <span x-text="when()"></span>
                    <template x-if="ev.location"><span> · <span x-text="ev.location"></span></span></template>
                </p>

                {{-- Not the host: no door, no numbers. --}}
                <template x-if="!isHost">
                    <p class="mt-6 text-sm text-muted">{{ __('webapp.events.host_only') }}</p>
                </template>

                <template x-if="isHost">
                    <div>
                        {{-- ── The door ────────────────────────────────────── --}}
                        <section class="mt-6 rounded-[22px] border border-ink/[.08] bg-white p-6">
                            <template x-if="!door.is_open">
                                <div class="text-center py-4">
                                    <p class="font-bold text-ink">{{ __('webapp.events.door_closed') }}</p>
                                    <p class="mt-1 text-[13px] text-body max-w-[38ch] mx-auto">{{ __('webapp.events.door_closed_hint') }}</p>
                                    <button type="button" @click="openDoor()" :disabled="busy"
                                            class="kb-on-yellow mt-5 h-12 px-7 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark transition disabled:opacity-50"
                                            x-text="busy ? t('common.saving') : t('events.open_door')"></button>
                                </div>
                            </template>

                            <template x-if="door.is_open">
                                <div class="flex flex-col items-center">
                                    <div class="flex items-center gap-2 text-[11px] font-bold tracking-[.12em] uppercase text-ok-ink">
                                        <span class="w-2 h-2 rounded-full bg-ok-ink"></span>{{ __('webapp.events.door_open') }}
                                    </div>

                                    {{-- Drawn server-side and inlined: the panel signs its
                                         requests with a bearer token, which an <img src>
                                         cannot carry. --}}
                                    <div class="mt-4 w-[248px] h-[248px] [&>svg]:w-full [&>svg]:h-full" x-html="door.qr_svg"></div>

                                    <p class="mt-4 text-[12px] text-muted">{{ __('webapp.events.or_type') }}</p>
                                    <p class="mt-1 font-mono text-[30px] tracking-[.22em] font-bold text-ink select-all" x-text="door.code"></p>
                                    <p class="mt-1 text-[12px] text-muted" x-text="door.url"></p>

                                    <div class="mt-5 flex items-center gap-2 flex-wrap justify-center">
                                        <button type="button" @click="copyUrl()"
                                                class="h-9 px-4 rounded-pill bg-white border border-line text-[12.5px] font-bold hover:border-ink transition"
                                                x-text="copied ? t('profile.shared') : t('events.copy_link')"></button>
                                        <button type="button" @click="openDoor()" :disabled="busy"
                                                class="h-9 px-4 rounded-pill bg-white border border-line text-[12.5px] font-bold hover:border-ink transition disabled:opacity-50">{{ __('webapp.events.new_code') }}</button>
                                    </div>
                                    <p class="mt-3 text-[11.5px] text-muted" x-show="door.expires_at" x-cloak
                                       x-text="t('events.closes_at', { time: window.kbDateTime(door.expires_at) })"></p>
                                </div>
                            </template>
                        </section>

                        {{-- ── Turnout ─────────────────────────────────────── --}}
                        <div class="mt-4 grid grid-cols-3 gap-2.5">
                            <div class="rounded-2xl border border-ink/[.08] bg-white px-3 py-4 text-center">
                                <p class="font-anton text-[22px] text-ink" x-text="ev.going_count ?? 0"></p>
                                <p class="mt-0.5 text-[10px] font-semibold tracking-[.1em] uppercase text-muted">{{ __('webapp.events.signed_up') }}</p>
                            </div>
                            <div class="rounded-2xl border border-ink/[.08] bg-white px-3 py-4 text-center">
                                <p class="font-anton text-[22px] text-ink" x-text="checkins.length"></p>
                                <p class="mt-0.5 text-[10px] font-semibold tracking-[.1em] uppercase text-muted">{{ __('webapp.events.showed_up') }}</p>
                            </div>
                            <div class="rounded-2xl border border-ink/[.08] bg-white px-3 py-4 text-center">
                                <p class="font-anton text-[22px]" :class="turnoutClass" x-text="turnout"></p>
                                <p class="mt-0.5 text-[10px] font-semibold tracking-[.1em] uppercase text-muted">{{ __('webapp.events.turnout') }}</p>
                            </div>
                        </div>

                        {{-- ── Who came ────────────────────────────────────── --}}
                        <section class="mt-6">
                            <h2 class="font-anton text-[17px] tracking-[.5px] text-ink">{{ __('webapp.events.who_came') }}</h2>
                            <template x-if="checkins.length === 0">
                                <p class="mt-3 text-sm text-muted">{{ __('webapp.events.nobody_yet') }}</p>
                            </template>
                            <div class="mt-3 flex flex-col gap-1.5">
                                <template x-for="c in checkins" :key="c.id">
                                    <div class="flex items-center gap-3 rounded-2xl border border-ink/[.08] bg-white px-4 py-2.5">
                                        <div class="w-8 h-8 rounded-full bg-primary/40 overflow-hidden shrink-0 flex items-center justify-center text-[12px] font-semibold text-ink">
                                            <template x-if="c.profile?.avatar_url"><img :src="c.profile.avatar_url" alt="" class="w-full h-full object-cover"></template>
                                            <template x-if="!c.profile?.avatar_url"><span x-text="initialOf(c.profile?.display_name)"></span></template>
                                        </div>
                                        <p class="flex-1 min-w-0 text-[13.5px] font-semibold text-ink truncate" x-text="c.profile?.display_name || '—'"></p>
                                        <span class="shrink-0 text-[11.5px] text-muted" x-text="clock(c.checked_in_at)"></span>
                                    </div>
                                </template>
                            </div>
                        </section>
                    </div>
                </template>
            </div>
        </template>
    </div>
    </main>
</div>

@push('scripts')
<script>
    function eventDoorPage() {
        return {
            ev: null, door: {}, checkins: [], loading: true, pageError: '',
            busy: false, copied: false, ticker: null,

            id: location.pathname.slice((window.KB_BASE || '').length).split('/')[2],

            get isHost() { return !!this.me && !!this.ev && this.me.id === this.ev.host_profile_id; },
            get turnout() {
                const going = this.ev?.going_count ?? 0;
                if (going === 0) return '—';
                return Math.round((this.checkins.length / going) * 100) + '%';
            },
            get turnoutClass() {
                const going = this.ev?.going_count ?? 0;
                if (going === 0) return 'text-muted';
                const ratio = this.checkins.length / going;
                if (ratio >= 0.7) return 'text-ok-ink';
                if (ratio >= 0.4) return 'text-ink';
                return 'text-warn-ink';
            },

            initialOf(v) { return window.kbInitial(v); },
            clock(iso) { return iso ? window.kbDateTime(iso) : ''; },
            when() {
                if (this.ev?.starts_at) return window.kbDateTime(this.ev.starts_at);
                return this.ev?.date ? window.kbDateShort(this.ev.date) : '';
            },

            async init() {
                if (!window.kb.requireAuth()) return;
                if (!await this.loadShell()) return;
                await this.load();
                if (this.isHost) {
                    await this.loadCheckins();
                    // A door is watched, not refreshed: the count has to move on its own.
                    this.ticker = setInterval(() => {
                        if (!document.hidden && this.door.is_open) this.loadCheckins();
                    }, 6000);
                }
            },

            async load() {
                const res = await window.kb.api('/events/' + this.id);
                this.loading = false;
                if (!res.ok) { this.pageError = window.kb.errorText(res, t('events.load_error')); return; }
                this.ev = res.json?.data || null;
                this.door = this.ev?.checkin || {};
            },

            async loadCheckins() {
                const res = await window.kb.api('/events/' + this.id + '/checkins?per_page=100');
                if (!res.ok) return;
                this.checkins = res.json?.data?.checkins || window.kb.rows(res);
            },

            async openDoor() {
                this.busy = true;
                const res = await window.kb.api('/events/' + this.id + '/generate-qr', { method: 'POST' });
                this.busy = false;
                if (!res.ok) { this.pageError = window.kb.errorText(res, t('events.door_error')); return; }
                const d = res.json?.data || {};
                // generate-qr returns the code and the URL; the SVG comes with the event.
                await this.load();
                this.door = { ...this.door, is_open: true, code: d.checkin_code, url: d.checkin_url, expires_at: d.checkin_expires_at };
                await this.loadCheckins();
            },

            async copyUrl() {
                if (!this.door.url) return;
                try { await navigator.clipboard.writeText(this.door.url); }
                catch (e) { window.prompt(t('events.copy_link'), this.door.url); }
                this.copied = true;
                setTimeout(() => { this.copied = false; }, 2000);
            },
        };
    }
</script>
@endpush
@endsection
