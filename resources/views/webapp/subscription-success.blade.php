@extends('webapp.layout')
@section('title', __('webapp.subscription.success.active_title'))

@section('body')
{{-- Return-from-Stripe landing. Confirms the Checkout Session synchronously so a
     buyer is never told "you're subscribed" before the row actually exists, and is
     never left on the paywall because the webhook lagged. --}}
<div x-data="kbMerge(kbShell(), successPage())" x-init="init()"
     class="min-h-screen bg-cream-alt flex flex-col items-center justify-center px-6 py-12 text-center">
    <div class="w-full max-w-[460px] kb-fade-up">

        {{-- ── Confirming ──────────────────────────────────────────────── --}}
        <template x-if="state === 'confirming'">
            <div>
                <div class="w-16 h-16 rounded-full bg-primary mx-auto flex items-center justify-center">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#19150F" stroke-width="2.4" stroke-linecap="round" class="animate-spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                </div>
                <h1 class="font-anton text-[28px] text-ink mt-5">{{ __('webapp.subscription.success.confirming') }}</h1>
                <p class="text-sm text-body mt-2 leading-relaxed">{{ __('webapp.subscription.success.confirming_desc') }}</p>
            </div>
        </template>

        {{-- ── Active ──────────────────────────────────────────────────── --}}
        <template x-if="state === 'active'">
            <div>
                <div class="w-16 h-16 rounded-full bg-[#56624D] mx-auto flex items-center justify-center">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                </div>
                <h1 class="font-anton text-[30px] text-ink mt-5">{{ __('webapp.subscription.success.active_title') }}</h1>
                <p class="text-sm text-body mt-2 leading-relaxed" x-text="activeLine"></p>

                <div class="mt-7 bg-white border border-ink/[.08] rounded-3xl p-6 text-left shadow-card">
                    <p class="text-[15px] font-bold text-ink">{{ __('webapp.subscription.success.next_step_title') }}</p>
                    <p class="text-[13px] text-muted mt-1.5 leading-relaxed">{{ __('webapp.subscription.success.next_step_desc') }}</p>
                    <a href="{{ $base }}/kolabs/create"
                       class="mt-4 w-full h-[52px] rounded-pill bg-primary text-ink text-[15px] font-bold shadow-btn hover:bg-primary-dark transition flex items-center justify-center">{{ __('webapp.subscription.success.create_kolab') }}</a>
                    <a :href="deepLink" class="mt-2.5 w-full h-11 rounded-pill bg-white border border-line text-ink text-[13px] font-bold hover:border-ink transition flex items-center justify-center">{{ __('webapp.welcome.open_app') }}</a>
                </div>

                <a href="{{ $base }}/subscription" class="inline-block mt-6 text-[13px] font-semibold text-muted hover:text-ink underline">{{ __('webapp.subscription.success.view_plan') }}</a>
            </div>
        </template>

        {{-- ── Paid, activation still in flight ────────────────────────── --}}
        <template x-if="state === 'pending'">
            <div>
                <div class="w-16 h-16 rounded-full bg-primary mx-auto flex items-center justify-center">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#19150F" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                </div>
                <h1 class="font-anton text-[28px] text-ink mt-5">{{ __('webapp.subscription.success.pending_title') }}</h1>
                <p class="text-sm text-body mt-2 leading-relaxed">{{ __('webapp.subscription.success.pending_desc') }}</p>

                <button type="button" @click="retry()" :disabled="busy"
                        class="mt-6 w-full h-[52px] rounded-pill bg-ink text-primary text-[15px] font-bold hover:-translate-y-px transition disabled:opacity-50">
                    <span x-text="busy ? t('subscription.success.confirming') : t('subscription.success.retry')">{{ __('webapp.subscription.success.retry') }}</span>
                </button>
                <p class="text-[12px] text-muted mt-3">{{ __('webapp.subscription.success.support') }}</p>
            </div>
        </template>

        {{-- ── Could not confirm ───────────────────────────────────────── --}}
        <template x-if="state === 'failed'">
            <div>
                <div class="w-16 h-16 rounded-full bg-[#F8D7DA] mx-auto flex items-center justify-center">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#721C24" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v5"/><path d="M12 16h.01"/></svg>
                </div>
                <h1 class="font-anton text-[28px] text-ink mt-5">{{ __('webapp.subscription.success.failed_title') }}</h1>
                <p class="text-sm text-body mt-2 leading-relaxed" x-text="error || t('subscription.success.failed_desc')"></p>
                <a href="{{ $base }}/subscription"
                   class="mt-6 inline-flex items-center justify-center h-[52px] px-8 rounded-pill bg-ink text-primary text-[15px] font-bold hover:-translate-y-px transition">{{ __('webapp.subscription.success.view_plan') }}</a>
                <p class="text-[12px] text-muted mt-3">{{ __('webapp.subscription.success.support') }}</p>
            </div>
        </template>
    </div>
</div>

@push('scripts')
<script>
    function successPage() {
        return {
            state: 'confirming', busy: false, error: '', activeLine: '',
            deepLink: window.KB_CONFIG.deepLink,
            sessionId: '',

            async init() {
                if (!window.kb.requireAuth()) return;
                if (!await this.loadShell()) return;

                this.sessionId = new URLSearchParams(location.search).get('session_id') || '';

                // Opened without a session id (bookmark, back button): just report
                // whatever the account's real subscription state is.
                if (!this.sessionId) { await this.readSubscription(); return; }

                await this.confirm(5);
            },

            /**
             * Confirm the Checkout Session, retrying while Stripe still reports the
             * payment as pending. Backoff is ~1s, 2s, 3s, 4s — about 10s total,
             * after which we hand the user an explicit retry rather than spinning.
             */
            async confirm(attempts) {
                for (let i = 0; i < attempts; i++) {
                    const res = await window.kb.api('/me/subscription/checkout/confirm', {
                        method: 'POST', body: { session_id: this.sessionId },
                    });

                    if (res.ok && res.json?.data) { this.activate(res.json.data); return; }

                    if (res.status === 409) {
                        if (i < attempts - 1) { await new Promise((r) => setTimeout(r, 1000 * (i + 1))); continue; }
                        this.state = 'pending';
                        return;
                    }

                    // 403 / 422 / 502 — a retry will not help. Fall back to the
                    // account's real state before declaring failure, because the
                    // webhook may already have activated the plan.
                    this.error = window.kb.errorText(res, '');
                    await this.readSubscription();
                    return;
                }
            },

            async readSubscription() {
                const res = await window.kb.api('/me/subscription');
                const sub = res.ok ? res.json?.data : null;
                if (sub && sub.is_active) { this.activate(sub); return; }
                this.state = this.error ? 'failed' : 'pending';
            },

            activate(sub) {
                this.error = '';
                this.state = 'active';
                this.activeLine = sub.current_period_end
                    ? t('subscription.success.active_desc_until', { date: window.kbDate(sub.current_period_end) })
                    : t('subscription.success.active_desc');
            },

            async retry() {
                this.busy = true; this.error = '';
                if (this.sessionId) { await this.confirm(1); } else { await this.readSubscription(); }
                this.busy = false;
            },
        };
    }
</script>
@endpush
@endsection
