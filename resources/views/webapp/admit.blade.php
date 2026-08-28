@extends('webapp.layout')
@section('title', __('webapp.admit.title'))

@section('body')
{{--
    The door, from the host's side. This is what a ticket QR points at.

    Two things shape the whole page. It is used standing up, in a doorway, on a phone
    the host is also using for something else — so it says one thing at a time, in the
    largest type on the site, and the answer is legible at arm's length without
    reading a sentence. And the QR was scanned by the *host's* camera, so the person
    being admitted is not the person signed in: the page has to make clear whose
    ticket it just accepted.

    No sidebar. A doorkeeper is not navigating.
--}}
<div class="min-h-screen bg-cream-alt flex flex-col" x-data="kbMerge(kbShell(), admitPage())" x-init="init()">
    <div class="w-full max-w-[460px] mx-auto px-5 py-8 flex-1 flex flex-col">

        <div class="flex items-center justify-between">
            <x-k-mark :size="22" />
            <a href="{{ $base }}/dashboard" class="text-[12.5px] font-bold text-muted hover:text-ink transition">{{ __('webapp.admit.done') }}</a>
        </div>

        <div class="flex-1 flex flex-col justify-center py-8">

            <p x-show="state === 'working'" x-cloak class="text-center text-sm text-muted">{{ __('webapp.common.loading') }}</p>

            {{-- Admitted. Green, big, and it names who came in. --}}
            <template x-if="state === 'in'">
                <div class="text-center">
                    <div class="w-20 h-20 rounded-full bg-success-solid mx-auto flex items-center justify-center">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </div>
                    <h1 class="font-anton text-[34px] leading-none tracking-[1px] text-ink mt-6">{{ __('webapp.admit.in') }}</h1>
                    <p class="text-[17px] font-bold text-ink mt-3" x-text="holderName"></p>
                    <p class="text-sm text-muted mt-1" x-text="eventName"></p>
                    <p class="text-[12.5px] text-muted mt-4" x-text="t('admit.at_time', { time: admittedAt })"></p>
                </div>
            </template>

            {{-- Already used. Amber, not red: it is usually a double scan, not a fraud. --}}
            <template x-if="state === 'already'">
                <div class="text-center">
                    <div class="w-20 h-20 rounded-full bg-warn-surface mx-auto flex items-center justify-center">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="rgb(var(--kb-warn-ink))" stroke-width="2.4" stroke-linecap="round"><path d="M12 8v5"/><path d="M12 17h.01"/></svg>
                    </div>
                    <h1 class="font-anton text-[30px] leading-none tracking-[1px] text-ink mt-6">{{ __('webapp.admit.already') }}</h1>
                    <p class="text-sm text-body mt-3">{{ __('webapp.admit.already_body') }}</p>
                </div>
            </template>

            {{-- Refused. The only red state, and it says which of the two reasons it is. --}}
            <template x-if="state === 'no'">
                <div class="text-center">
                    <div class="w-20 h-20 rounded-full bg-bad-surface mx-auto flex items-center justify-center">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="rgb(var(--kb-bad-ink))" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </div>
                    <h1 class="font-anton text-[30px] leading-none tracking-[1px] text-ink mt-6">{{ __('webapp.admit.no') }}</h1>
                    <p class="text-sm text-body mt-3" x-text="message"></p>
                </div>
            </template>

            {{-- Manual entry: the QR failed, so someone is reading the code out. --}}
            <template x-if="state === 'manual'">
                <div>
                    <h1 class="font-anton text-[26px] leading-tight tracking-[.6px] text-ink text-center">{{ __('webapp.admit.manual_title') }}</h1>
                    <p class="text-sm text-muted mt-2 text-center">{{ __('webapp.admit.manual_sub') }}</p>
                    <input x-model="typed" @keydown.enter="admit(typed)" type="text" maxlength="16"
                           autocapitalize="characters" autocomplete="off" spellcheck="false"
                           placeholder="{{ __('webapp.admit.code_ph') }}"
                           class="mt-6 w-full h-14 px-4 rounded-xl bg-white border border-ink/[.10] focus:border-ink/30 text-center font-anton text-[24px] tracking-[3px] uppercase outline-none transition">
                    <button type="button" @click="admit(typed)" :disabled="typed.trim().length < 4"
                            class="kb-on-yellow mt-4 w-full h-12 rounded-pill bg-primary text-ink text-[15px] font-bold shadow-btn hover:bg-primary-dark transition disabled:opacity-40">{{ __('webapp.admit.let_in') }}</button>
                </div>
            </template>
        </div>

        {{-- After any outcome, the useful next action is the next person. --}}
        <div class="flex flex-col gap-2 pb-4" x-show="state !== 'working' && state !== 'manual'" x-cloak>
            <button type="button" @click="reset()"
                    class="kb-on-yellow w-full h-12 rounded-pill bg-primary text-ink text-[15px] font-bold shadow-btn hover:bg-primary-dark transition">{{ __('webapp.admit.next') }}</button>
            <a href="{{ $base }}/dashboard" class="w-full h-11 rounded-pill bg-white border border-line text-sm font-bold flex items-center justify-center hover:border-ink transition">{{ __('webapp.admit.done') }}</a>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function admitPage() {
        return {
            state: 'working', message: '', typed: '',
            holderName: '', eventName: '', admittedAt: '',

            async init() {
                /*
                 * The host may well not be signed in on this device — a QR opens in
                 * whatever browser the camera hands it to. requireAuth() carries the
                 * destination in ?next=, so signing in lands back here and the scan
                 * completes instead of being lost.
                 */
                if (!window.kb.requireAuth()) return;
                const me = await this.loadShell();
                if (!me) return;

                const code = this.codeFromPath();
                if (!code) { this.state = 'manual'; return; }
                await this.admit(code);
            },

            /** The URL is /admit/{code}; the locale prefix is stripped by KB_BASE. */
            codeFromPath() {
                const base = window.KB_BASE || '';
                const path = location.pathname.startsWith(base)
                    ? location.pathname.slice(base.length)
                    : location.pathname;
                const parts = path.split('/').filter(Boolean);
                return parts[0] === 'admit' && parts[1] ? decodeURIComponent(parts[1]) : '';
            },

            async admit(code) {
                const clean = String(code || '').trim();
                if (clean === '') { this.state = 'manual'; return; }

                this.state = 'working';
                const res = await window.kb.api('/tickets/' + encodeURIComponent(clean) + '/admit', { method: 'POST' });

                if (res.ok) {
                    const data = res.json?.data || {};
                    this.holderName = data.ticket?.holder_name || t('admit.a_guest');
                    this.eventName = data.ticket?.event?.name || '';
                    this.admittedAt = data.checked_in_at
                        ? new Date(data.checked_in_at).toLocaleTimeString(window.KB_LOCALE || 'en', { hour: '2-digit', minute: '2-digit' })
                        : '';
                    this.state = 'in';
                    return;
                }

                // 409 is the everyday case at a busy door, so it gets its own state
                // rather than being lumped in with a refusal.
                if (res.status === 409) { this.state = 'already'; return; }

                this.message = window.kb.errorText(res, t('admit.error'));
                this.state = 'no';
            },

            reset() {
                this.typed = '';
                this.message = '';
                this.state = 'manual';
            },
        };
    }
</script>
@endpush
@endsection
