@extends('webapp.layout')
@section('title', __('webapp.subscription.title'))

@php
    // Display-only prices, read from the same config the Stripe checkout bills.
    $monthlyPrice = (int) config('subscriptions.business.stripe.monthly.price');
    $quarterPrice = (int) config('subscriptions.business.stripe.three_months.price');

    // What a quarterly buyer effectively pays per month, and the saving against
    // the monthly plan — derived, never hardcoded, so a price change is one edit.
    $quarterPerMonth = (int) round($quarterPrice / 3);
    $savePercent = $monthlyPrice > 0
        ? (int) round((1 - ($quarterPrice / 3) / $monthlyPrice) * 100)
        : 0;

    $configured = [
        'monthly' => filled(config('subscriptions.business.stripe.monthly.stripe_price_id')),
        'three_months' => filled(config('subscriptions.business.stripe.three_months.stripe_price_id')),
    ];

    // Plans without a Stripe price would 502 on click, so they are hidden — unless
    // nothing is configured at all (local dev / CI), where hiding everything would
    // leave a blank page instead of a working preview.
    $showAll = ! in_array(true, $configured, true);

    $plans = [
        'monthly' => [
            'name' => __('webapp.subscription.plan_monthly'),
            'amount' => $monthlyPrice,
            'per' => __('webapp.subscription.per_month'),
            'note' => __('webapp.subscription.billed_monthly'),
            'badge' => null,
        ],
        'three_months' => [
            'name' => __('webapp.subscription.plan_quarterly'),
            'amount' => $quarterPerMonth,
            'per' => __('webapp.subscription.per_month'),
            'note' => __('webapp.subscription.billed_quarterly', ['price' => '€'.$quarterPrice]),
            'badge' => $savePercent > 0 ? __('webapp.subscription.save_badge', ['percent' => $savePercent]) : null,
        ],
    ];
@endphp

@section('body')
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), planPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'subscription'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[640px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        <h1 class="font-anton text-[28px] tracking-[1px] text-ink">{{ __('webapp.subscription.title') }}</h1>

        {{-- Why the user landed here, when a paywalled action sent them. --}}
        <template x-if="reasonText">
            <div class="mt-5 rounded-2xl bg-primary-tint border border-primary px-4 py-3 flex items-start gap-2.5">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-px"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                <p class="text-[13px] font-semibold text-ink leading-relaxed" x-text="reasonText"></p>
            </div>
        </template>

        <template x-if="error">
            <div class="mt-5 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
        </template>
        <template x-if="loading">
            <p class="mt-8 text-muted">{{ __('webapp.common.loading') }}</p>
        </template>

        {{-- ── Communities never pay ───────────────────────────────────── --}}
        <template x-if="!loading && !isBusiness">
            <div class="mt-5 bg-white border border-ink/[.08] rounded-3xl p-[26px] shadow-card">
                <p class="text-[17px] font-bold text-ink">{{ __('webapp.subscription.not_business_title') }}</p>
                <p class="text-sm text-body mt-2 leading-relaxed">{{ __('webapp.subscription.not_business_desc') }}</p>
                <a href="{{ $base }}/dashboard"
                   class="inline-flex items-center justify-center h-11 px-6 mt-5 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark transition">{{ __('webapp.subscription.back_home') }}</a>
            </div>
        </template>

        {{-- ── No active plan → the offer ──────────────────────────────── --}}
        <template x-if="!loading && isBusiness && !subActive">
            <div>
                <p class="text-[13px] font-semibold tracking-[.12em] uppercase text-muted mt-6">{{ __('webapp.subscription.choose_plan') }}</p>

                <div class="grid gap-3 sm:grid-cols-2 mt-3">
                    @foreach ($plans as $key => $plan)
                        @if ($showAll || $configured[$key])
                            <button type="button" @click="plan = '{{ $key }}'"
                                    :class="plan === '{{ $key }}' ? 'border-ink bg-primary ring-2 ring-ink' : 'border-ink/[.12] bg-white hover:border-ink/40'"
                                    class="text-left rounded-3xl border p-5 transition">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-[13px] font-bold tracking-[.06em] uppercase text-ink">{{ $plan['name'] }}</span>
                                    @if ($plan['badge'])
                                        <span class="px-2.5 py-1 rounded-pill bg-ink text-primary text-[10px] font-bold tracking-[.6px]">{{ $plan['badge'] }}</span>
                                    @endif
                                </div>
                                <div class="flex items-baseline gap-1 mt-3">
                                    <span class="font-anton text-[36px] leading-none text-ink">€{{ $plan['amount'] }}</span>
                                    <span class="text-[13px] font-semibold text-amber">{{ $plan['per'] }}</span>
                                </div>
                                <p class="text-[12px] text-body mt-1.5">{{ $plan['note'] }}</p>
                            </button>
                        @endif
                    @endforeach
                </div>

                <div class="bg-white border border-ink/[.08] rounded-3xl p-[26px] mt-3 shadow-card">
                    <span class="inline-block px-3 py-[5px] rounded-pill bg-ink text-primary text-[11px] font-bold tracking-[.8px]">{{ __('webapp.subscription.badge') }}</span>

                    <div class="flex flex-col gap-2.5 mt-4">
                        @foreach (__('webapp.subscription.benefits') as $benefit)
                            <div class="flex items-center gap-2.5 text-sm font-medium text-ink">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="M20 6 9 17l-5-5"/></svg>
                                {{ $benefit }}
                            </div>
                        @endforeach
                    </div>

                    <div class="flex flex-col gap-2 mt-5">
                        <input x-model="referralCode" type="text" maxlength="64" placeholder="{{ __('webapp.subscription.referral_placeholder') }}"
                               class="h-12 rounded-2xl border border-ink/15 bg-cream-input px-4 text-sm text-ink uppercase">
                        <p x-show="referralNote" x-cloak class="text-xs font-semibold px-1" :class="referralOk ? 'text-ok-ink' : 'text-bad-ink'" x-text="referralNote"></p>
                        <button type="button" @click="checkout()" :disabled="busy"
                                class="h-[52px] rounded-pill bg-ink text-primary text-[15px] font-bold hover:-translate-y-px transition disabled:opacity-50">
                            <span x-text="busy ? t('subscription.opening') : t('subscription.subscribe')">{{ __('webapp.subscription.subscribe') }}</span>
                        </button>
                    </div>
                </div>

                <p class="text-[12.5px] text-muted mt-3 text-center">{{ __('webapp.subscription.footnote') }}</p>
            </div>
        </template>

        {{-- ── Active plan → manage ────────────────────────────────────── --}}
        <template x-if="!loading && isBusiness && subActive">
            <div class="bg-white border border-ink/[.08] rounded-3xl p-[26px] mt-5 shadow-card">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <span class="text-[17px] font-bold text-ink">{{ __('webapp.subscription.plan_name') }}</span>
                            <span class="px-3 py-1 rounded-xl text-[11px] font-bold tracking-[.4px]"
                                  :style="`background:${statusPill(sub.status).bg};color:${statusPill(sub.status).c}`"
                                  x-text="statusPill(sub.status).label"></span>
                        </div>
                        <p class="text-[13px] text-muted mt-1.5" x-text="statusLine"></p>
                    </div>
                    <span class="font-anton text-[34px] text-ink shrink-0" x-text="planPriceLabel"></span>
                </div>

                {{-- Winding down: say so plainly instead of just showing "active". --}}
                <template x-if="sub.cancel_at_period_end">
                    <div class="mt-4 rounded-2xl bg-cream-low px-4 py-3">
                        <p class="text-[13px] font-semibold text-ink" x-text="endsLine"></p>
                        <p class="text-[12px] text-muted mt-1">{{ __('webapp.subscription.resume_hint') }}</p>
                    </div>
                </template>

                <div class="border-t border-ink/[.08] mt-[18px] pt-[18px] flex flex-col sm:flex-row gap-2.5">
                    <button type="button" @click="portal()" :disabled="busy"
                            class="flex-1 h-11 rounded-pill bg-white border border-line text-ink text-[13px] font-bold hover:border-ink transition disabled:opacity-50">
                        <span x-text="busy ? t('subscription.opening') : t('subscription.manage_billing')">{{ __('webapp.subscription.manage_billing') }}</span>
                    </button>
                    <button type="button" @click="portal()" :disabled="busy"
                            class="flex-1 h-11 rounded-pill bg-bad-surface text-bad-ink text-[13px] font-bold hover:-translate-y-px transition disabled:opacity-50">{{ __('webapp.subscription.cancel_plan') }}</button>
                </div>
                <p class="text-[11px] text-muted mt-3">{{ __('webapp.subscription.portal_note') }}</p>
            </div>
        </template>
    </div>
    </main>
</div>

@push('scripts')
<script>
    function planPage() {
        return {
            loading: true, busy: false, error: '',
            subActive: false, sub: {}, statusLine: '', endsLine: '',
            referralCode: '', referralNote: '', referralOk: false,
            plan: 'monthly', reasonText: '',

            statusPill(s) { return window.kbStatus(s || 'active'); },

            /**
             * Which plan the active subscription is actually on. `/me/subscription`
             * does not expose it, so infer it from the billing period — a quarterly
             * subscriber must never be shown the monthly price.
             */
            get activePlanIsQuarterly() {
                const start = this.sub.current_period_start, end = this.sub.current_period_end;
                if (!start || !end) return false;
                const days = (new Date(end) - new Date(start)) / 86400000;
                return days > 45;
            },
            get activePlanPrice() {
                return this.activePlanIsQuarterly ? {{ $quarterPrice }} : {{ $monthlyPrice }};
            },
            get planPriceLabel() {
                // No period dates → no reliable price; show nothing rather than a wrong one.
                if (!this.sub.current_period_start || !this.sub.current_period_end) return '';
                return '€' + this.activePlanPrice;
            },

            async init() {
                if (!window.kb.requireAuth()) return;

                const params = new URLSearchParams(location.search);

                // Preselect the plan the buyer picked on the public pricing page
                // (?plan=…, or stashed at registration before they had an account).
                const wanted = params.get('plan') || localStorage.getItem('kolabing_plan');
                if (wanted === 'monthly' || wanted === 'three_months') this.plan = wanted;
                localStorage.removeItem('kolabing_plan');

                // Which paywalled action sent them here. Allowlisted, not passed
                // through: `reason` is user-controlled, and t() on an unknown key
                // would print the dotted path straight onto the page.
                //
                // `suggestion` is the blurred counterpart on a suggestion card
                // (BE-NF-28). It is not a third gate — ROLES §2.7 has exactly two,
                // and the blur is a downstream effect of them, which is what the
                // copy for it has to say.
                const reason = params.get('reason');
                if (['publish', 'accept', 'apply', 'create', 'welcome', 'suggestion'].includes(reason)) {
                    this.reasonText = t('subscription.reason.' + reason);
                }

                if (!await this.loadShell()) return;
                // A code captured at signup can only be validated once authenticated.
                this.referralCode = localStorage.getItem('kolabing_referral') || '';
                if (this.isBusiness) {
                    const res = await window.kb.api('/me/subscription');
                    const sub = res.ok ? res.json?.data : null;
                    if (sub && sub.is_active) {
                        this.subActive = true;
                        this.sub = sub;
                        // `sub` must be assigned before reading activePlanPrice — it
                        // derives the plan from this subscription's billing period.
                        this.statusLine = sub.current_period_end
                            ? t(this.activePlanIsQuarterly ? 'subscription.next_billing_quarterly' : 'subscription.next_billing', {
                                price: '€' + this.activePlanPrice,
                                date: window.kbDate(sub.current_period_end),
                            })
                            : t('subscription.active_generic');
                        this.endsLine = t('subscription.ends_on', { date: window.kbDate(sub.current_period_end) });
                    }
                }
                this.loading = false;
            },

            async validateReferral() {
                const code = (this.referralCode || '').trim().toUpperCase();
                this.referralNote = ''; this.referralOk = false;
                if (!code) return null;
                const res = await window.kb.api('/referrals/validate', { method: 'POST', body: { referral_code: code } });
                if (res.ok) {
                    this.referralOk = true;
                    this.referralNote = t('subscription.referral_ok');
                    return code;
                }
                this.referralNote = window.kb.errorText(res, t('subscription.referral_invalid'));
                return false; // invalid — stop before checkout
            },

            async checkout() {
                this.error = ''; this.busy = true;
                const code = await this.validateReferral();
                if (code === false) { this.busy = false; return; }

                const origin = location.origin + (window.KB_BASE || '');
                const body = {
                    plan: this.plan,
                    // Stripe substitutes the session id on redirect; /subscription/success
                    // confirms the purchase there and then, without waiting on the webhook.
                    success_url: origin + '/subscription/success?session_id={CHECKOUT_SESSION_ID}',
                    cancel_url: origin + '/subscription',
                };
                if (code) body.referral_code = code;

                const res = await window.kb.api('/me/subscription/checkout', { method: 'POST', body });
                if (res.ok && res.json?.data?.checkout_url) {
                    localStorage.removeItem('kolabing_referral');
                    location.href = res.json.data.checkout_url;
                    return;
                }
                this.busy = false;
                this.error = window.kb.errorText(res, t('subscription.checkout_error'));
            },

            async portal() {
                this.error = ''; this.busy = true;
                const err = await this.openBillingPortal();
                if (err) { this.busy = false; this.error = err; }
            },
        };
    }
</script>
@endpush
@endsection
