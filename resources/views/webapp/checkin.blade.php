@extends('webapp.layout')
@section('title', __('webapp.checkin.title'))

@section('body')
{{-- Where a scanned QR lands. Deliberately not the app shell: someone is standing
     at a door with a phone, so the page is one screen, one outcome, no navigation.
     If they are not signed in it sends them to log in and comes straight back. --}}
<div class="min-h-screen flex items-center justify-center px-5 py-10 bg-cream"
     x-data="kbMerge(kbShell(), checkinPage())" x-init="init()">
    <div class="w-full max-w-[420px]">

        <a href="{{ $base }}/dashboard" class="block mb-7 text-center">
            <img src="/webapp-assets/wordmark-light.png" alt="Kolabing" class="h-7 w-auto inline-block">
        </a>

        <div class="bg-white border border-ink/[.08] rounded-[22px] p-7 shadow-card text-center kb-fade-up">

            <template x-if="state === 'working'">
                <div>
                    <div class="w-14 h-14 mx-auto rounded-full bg-primary/30 flex items-center justify-center">
                        <svg class="animate-spin" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                            <path d="M21 12a9 9 0 1 1-6.2-8.6"/>
                        </svg>
                    </div>
                    <p class="mt-4 font-bold text-ink">{{ __('webapp.checkin.working') }}</p>
                </div>
            </template>

            {{-- The one outcome that matters. --}}
            <template x-if="state === 'done'">
                <div>
                    <div class="w-16 h-16 mx-auto rounded-full bg-ok-surface text-ok-ink flex items-center justify-center">
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </div>
                    <p class="mt-4 font-anton text-[26px] tracking-[.5px] text-ink">{{ __('webapp.checkin.done') }}</p>
                    <p class="mt-1 text-[14px] text-body" x-text="eventName"></p>
                    <p class="mt-0.5 text-[12.5px] text-muted" x-text="stamp"></p>
                    <a :href="window.kbPath('/dashboard')"
                       class="kb-on-yellow mt-6 inline-flex items-center justify-center h-11 px-6 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn">{{ __('webapp.checkin.continue') }}</a>
                </div>
            </template>

            <template x-if="state === 'already'">
                <div>
                    <div class="w-16 h-16 mx-auto rounded-full bg-primary/30 text-ink flex items-center justify-center">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </div>
                    <p class="mt-4 font-bold text-[17px] text-ink">{{ __('webapp.checkin.already') }}</p>
                    <p class="mt-1 text-[13.5px] text-body">{{ __('webapp.checkin.already_body') }}</p>
                    <a :href="window.kbPath('/dashboard')"
                       class="mt-6 inline-flex items-center justify-center h-11 px-6 rounded-pill bg-white border border-line text-sm font-bold hover:border-ink transition">{{ __('webapp.checkin.continue') }}</a>
                </div>
            </template>

            {{-- Failures say what to do next, not just what went wrong. --}}
            <template x-if="state === 'failed'">
                <div>
                    <div class="w-16 h-16 mx-auto rounded-full bg-bad-surface text-bad-ink flex items-center justify-center">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </div>
                    <p class="mt-4 font-bold text-[17px] text-ink" x-text="error"></p>
                    <p class="mt-1 text-[13.5px] text-body">{{ __('webapp.checkin.failed_hint') }}</p>

                    {{-- The typed fallback: a camera that will not focus, or a code
                         read out loud, must not end the attempt here. --}}
                    <form @submit.prevent="submit(manual)" class="mt-5 flex flex-col gap-2.5">
                        <label class="text-[11px] font-semibold tracking-[.1em] uppercase text-muted text-left">{{ __('webapp.checkin.enter_code') }}</label>
                        <input x-model="manual" maxlength="8" autocapitalize="characters" autocomplete="off" spellcheck="false"
                               placeholder="ABCD1234"
                               class="h-12 px-4 rounded-xl bg-cream-low border border-transparent focus:border-ink/20 focus:bg-white text-center text-[19px] font-mono tracking-[.22em] uppercase outline-none transition">
                        <button type="submit" :disabled="manual.trim().length < 4"
                                class="kb-on-yellow h-11 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn disabled:opacity-50">{{ __('webapp.checkin.submit') }}</button>
                    </form>
                </div>
            </template>
        </div>

        <p class="mt-5 text-center text-[12px] text-muted">{{ __('webapp.checkin.privacy') }}</p>
    </div>
</div>

@push('scripts')
<script>
    function checkinPage() {
        return {
            state: 'working', error: '', eventName: '', stamp: '', manual: '',

            get token() {
                // /checkin/{token} — the token may be the 8-char code or the long one.
                const parts = location.pathname.slice((window.KB_BASE || '').length).split('/');
                return decodeURIComponent(parts[2] || '');
            },

            async init() {
                if (!window.kb.token) {
                    /*
                     * Not signed in. Someone at a door will not come back on their
                     * own, so carry the destination through login and return here.
                     */
                    window.nav('/login?next=' + encodeURIComponent('/checkin/' + this.token));
                    return;
                }
                await this.loadShell();
                await this.submit(this.token);
            },

            async submit(token) {
                const value = String(token || '').trim();
                if (value === '') return;

                this.state = 'working';
                const res = await window.kb.api('/checkin', { method: 'POST', body: { token: value } });

                if (res.ok) {
                    const data = res.json?.data || {};
                    this.eventName = data.event?.name || data.event_name || '';
                    this.stamp = data.checked_in_at ? window.kbDateTime(data.checked_in_at) : '';
                    this.state = 'done';
                    return;
                }

                // 409 is the friendly case: they are already counted.
                if (res.status === 409) {
                    this.state = 'already';
                    return;
                }

                this.error = window.kb.errorText(res, t('checkin.failed'));
                this.manual = '';
                this.state = 'failed';
            },
        };
    }
</script>
@endpush
@endsection
