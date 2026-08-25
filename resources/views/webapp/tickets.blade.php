@extends('webapp.layout')
@section('title', __('webapp.tickets.title'))

@section('body')
{{--
    The wallet. Every seat this attendee holds, each with the QR that gets them in.

    Designed for the one moment that matters: standing at a door, one hand, bad
    light, worse signal. So the next ticket is opened by default and its QR is large;
    the QR is inline SVG that arrived with the list, so nothing needs to load when the
    phone comes out; and the code is printed underneath in a size someone can read
    aloud when the scanner gives up.
--}}
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), ticketsPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'tickets'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[720px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="font-anton text-[30px] md:text-[34px] leading-none tracking-[1px] text-ink">{{ __('webapp.tickets.title') }}</h1>
                <p class="text-sm text-muted mt-2" x-text="subtitle">{{ __('webapp.tickets.subtitle') }}</p>
            </div>
            <div class="flex p-1 bg-cream-low rounded-xl self-start sm:self-auto shrink-0">
                <button type="button" @click="setTab('upcoming')"
                        class="min-w-[96px] h-9 px-4 rounded-lg text-[13px] font-bold tracking-[.3px] transition"
                        :class="tab === 'upcoming' ? 'bg-white text-ink shadow-btn' : 'text-muted hover:text-body'">{{ __('webapp.tickets.tab_upcoming') }}</button>
                <button type="button" @click="setTab('past')"
                        class="min-w-[96px] h-9 px-4 rounded-lg text-[13px] font-bold tracking-[.3px] transition"
                        :class="tab === 'past' ? 'bg-white text-ink shadow-btn' : 'text-muted hover:text-body'">{{ __('webapp.tickets.tab_past') }}</button>
            </div>
        </div>

        <template x-if="error">
            <div class="mt-6 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
        </template>

        <p x-show="loading" x-cloak class="mt-8 text-sm text-muted">{{ __('webapp.common.loading') }}</p>

        <template x-if="!loading && tickets.length === 0 && !error">
            <div class="mt-8 rounded-2xl border-[1.5px] border-dashed border-ink/20 py-14 px-6 text-center">
                <p class="text-sm text-muted" x-text="tab === 'past' ? t('tickets.empty_past') : t('tickets.empty')"></p>
                <a href="{{ $base }}/dashboard" x-show="tab === 'upcoming'"
                   class="kb-on-yellow inline-flex items-center h-11 px-6 mt-5 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark transition">{{ __('webapp.tickets.find_something') }}</a>
            </div>
        </template>

        <div class="mt-7 flex flex-col gap-4">
            <template x-for="tk in tickets" :key="tk.id">
                <article class="rounded-[22px] bg-white border border-ink/[.08] shadow-card overflow-hidden">
                    {{-- Header row: what and when, always visible. --}}
                    <button type="button" @click="toggle(tk.id)" class="w-full flex items-start gap-4 p-5 text-left">
                        <span class="w-[52px] shrink-0 rounded-xl border border-ink/[.10] overflow-hidden text-center">
                            <span class="block text-[9.5px] font-bold tracking-[.8px] uppercase text-white bg-ink py-[3px]" x-text="monthOf(tk)"></span>
                            <span class="block text-[19px] font-bold text-ink leading-none py-1.5 tabular-nums" x-text="dayOf(tk)"></span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[16px] font-bold leading-snug text-ink" x-text="tk.event?.name || t('tickets.an_event')"></span>
                            <span class="block text-[13px] text-muted mt-1" x-text="whenLine(tk)"></span>
                            <span class="block text-[13px] text-muted" x-show="tk.event?.address || tk.event?.location" x-cloak
                                  x-text="tk.event?.address || tk.event?.location"></span>
                            <span class="inline-flex items-center gap-1.5 mt-2.5 px-2.5 py-1 rounded-pill text-[11.5px] font-bold"
                                  :class="tk.used_at ? 'bg-ok-surface text-ok-ink' : 'bg-primary text-on-primary'"
                                  x-text="tk.used_at ? t('tickets.admitted') : t('tickets.valid')"></span>
                        </span>
                        <svg class="shrink-0 mt-1 transition" :class="open === tk.id ? 'rotate-180' : ''" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                    </button>

                    {{-- The ticket itself. --}}
                    <div x-show="open === tk.id" x-cloak class="px-5 pb-6 border-t border-ink/[.08]">
                        <p class="text-[12.5px] text-muted mt-5 text-center">{{ __('webapp.tickets.show_at_door') }}</p>

                        {{-- White plate behind the QR regardless of theme: a scanner
                             needs the contrast, and in dark mode the card is not white. --}}
                        <div class="mx-auto mt-4 w-[232px] p-4 rounded-2xl bg-white border border-ink/[.08]">
                            <div class="w-full" x-html="tk.qr_svg"></div>
                        </div>

                        <div class="mt-5 text-center">
                            <p class="text-[11px] font-bold tracking-[1px] uppercase text-muted">{{ __('webapp.tickets.code_label') }}</p>
                            <p class="font-anton text-[26px] tracking-[3px] text-ink mt-1 select-all" x-text="tk.code"></p>
                            <p class="text-[12px] text-muted mt-1">{{ __('webapp.tickets.code_hint') }}</p>
                        </div>

                        <div class="flex gap-2 justify-center mt-5 flex-wrap">
                            <button type="button" @click="copyCode(tk)"
                                    class="h-10 px-4 rounded-pill bg-cream-low hover:bg-cream-low-hover transition text-[12.5px] font-bold text-body"
                                    x-text="copied === tk.id ? t('detail.link_copied') : t('tickets.copy_code')"></button>
                            <button type="button" @click="cancel(tk)" x-show="!tk.used_at" x-cloak :disabled="busy"
                                    class="h-10 px-4 rounded-pill bg-white border border-line hover:border-danger transition text-[12.5px] font-bold text-body disabled:opacity-50">{{ __('webapp.tickets.cancel') }}</button>
                        </div>
                    </div>
                </article>
            </template>
        </div>
    </div>
    </main>
</div>

@push('scripts')
<script>
    function ticketsPage() {
        return {
            tickets: [], loading: true, error: '', busy: false,
            tab: 'upcoming', open: null, copied: null,

            get subtitle() {
                if (this.loading) return '';
                const n = this.tickets.length;
                return n === 0 ? '' : t('tickets.count', { count: n });
            },

            async init() {
                if (!window.kb.requireAuth()) return;
                // loadShell() returns null and redirects if onboarding is unfinished.
                const me = await this.loadShell();
                if (!me) return;
                await this.load();

                /*
                 * Open the ticket the visitor came for. The confirmation email links
                 * to /tickets?t=CODE, and someone following it at a door should not
                 * have to find their own ticket in a list.
                 */
                const wanted = new URLSearchParams(location.search).get('t');
                const match = wanted ? this.tickets.find(x => x.code === wanted.toUpperCase()) : null;
                this.open = match ? match.id : (this.tickets[0]?.id ?? null);
            },

            setTab(tab) { if (this.tab === tab) return; this.tab = tab; this.open = null; this.load(); },

            async load() {
                this.loading = true; this.error = '';
                const res = await window.kb.api('/me/tickets' + (this.tab === 'past' ? '?past=1' : ''));
                this.loading = false;
                if (!res.ok) { this.error = window.kb.errorText(res, t('tickets.load_error')); this.tickets = []; return; }
                this.tickets = res.json?.data || [];
                if (this.tab === 'upcoming' && this.open === null) this.open = this.tickets[0]?.id ?? null;
            },

            toggle(id) { this.open = this.open === id ? null : id; },

            when(tk) { return tk.event?.starts_at || tk.event?.event_date || null; },
            monthOf(tk) {
                const w = this.when(tk);
                if (!w) return '—';
                return new Date(w).toLocaleDateString(window.KB_LOCALE || 'en', { month: 'short' });
            },
            dayOf(tk) {
                const w = this.when(tk);
                return w ? String(new Date(w).getDate()) : '—';
            },
            whenLine(tk) {
                const w = this.when(tk);
                if (!w) return t('feed.when_flexible');
                const d = new Date(w);
                const day = d.toLocaleDateString(window.KB_LOCALE || 'en', { weekday: 'long', day: 'numeric', month: 'long' });
                const time = d.toLocaleTimeString(window.KB_LOCALE || 'en', { hour: '2-digit', minute: '2-digit' });
                const host = tk.event?.host_name;
                return [day + (tk.event?.starts_at ? ' · ' + time : ''), host].filter(Boolean).join(' · ');
            },

            async copyCode(tk) {
                try {
                    if (navigator.clipboard?.writeText) await navigator.clipboard.writeText(tk.code);
                    this.copied = tk.id;
                    setTimeout(() => { this.copied = null; }, 1800);
                } catch (e) { /* the code is on screen either way */ }
            },

            /**
             * Give the seat back. Cancelling promotes whoever is next on the
             * waitlist, which is the reason to encourage it rather than let someone
             * simply not turn up.
             */
            async cancel(tk) {
                if (!tk.event?.id) return;
                this.busy = true;
                const res = await window.kb.api('/events/' + tk.event.id + '/signup', { method: 'DELETE' });
                this.busy = false;
                if (!res.ok) { this.error = window.kb.errorText(res, t('tickets.cancel_error')); return; }
                this.tickets = this.tickets.filter(x => x.id !== tk.id);
                this.open = this.tickets[0]?.id ?? null;
            },
        };
    }
</script>
@endpush
@endsection
