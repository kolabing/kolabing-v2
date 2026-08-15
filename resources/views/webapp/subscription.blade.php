@extends('webapp.layout')
@section('title', 'Subscription')

@section('body')
<div x-data="subscriptionPage()" x-init="init()">
    @include('webapp.partials.nav', ['active' => 'subscription'])

    <main class="max-w-2xl mx-auto px-5 py-8">
        <h1 class="font-montserrat font-black text-2xl tracking-tight">Subscription</h1>

        <template x-if="error">
            <div class="mt-4 rounded-xl bg-red-50 text-red-700 text-sm px-4 py-3" x-text="error"></div>
        </template>

        <template x-if="loading">
            <p class="mt-6 text-off-black/50">Loading…</p>
        </template>

        {{-- Not a business account --}}
        <template x-if="!loading && !isBusiness">
            <div class="mt-6 rounded-2xl border border-off-black/10 p-6">
                <p class="font-semibold">Subscriptions are for business accounts</p>
                <p class="text-sm text-off-black/60 mt-1">Community accounts join and apply to Kolabs for free — no subscription needed.</p>
                <a href="/dashboard" class="inline-block mt-4 rounded-xl bg-off-black text-off-white text-sm font-semibold px-4 py-2">Back home</a>
            </div>
        </template>

        {{-- Active subscription → manage --}}
        <template x-if="!loading && isBusiness && subActive">
            <div class="mt-6 rounded-2xl border border-off-black/10 p-6">
                <span class="inline-block rounded-full bg-green-100 text-green-700 text-xs font-semibold px-2.5 py-1">Active</span>
                <p class="mt-3 text-off-black/70" x-text="statusLine"></p>
                <button @click="openPortal()" :disabled="busy"
                        class="mt-4 rounded-xl bg-off-black text-off-white text-sm font-semibold px-4 py-2 disabled:opacity-50">
                    <span x-text="busy ? 'Opening…' : 'Manage or cancel'"></span>
                </button>
                <p class="text-xs text-off-black/50 mt-2">Opens Stripe's secure billing portal.</p>
            </div>
        </template>

        {{-- No active subscription → plans --}}
        <template x-if="!loading && isBusiness && !subActive">
            <div class="mt-6">
                <p class="text-off-black/60">Subscribe to publish Kolabs and accept applications.</p>
                <div class="mt-4 grid sm:grid-cols-2 gap-4">
                    <div class="rounded-2xl border border-off-black/10 p-5 flex flex-col">
                        <p class="font-semibold">Monthly</p>
                        <p class="mt-1"><span class="text-3xl font-black">€49</span><span class="text-off-black/50">/mo</span></p>
                        <p class="text-sm text-off-black/60 mt-2 flex-1">Billed monthly. Cancel anytime.</p>
                        <button @click="checkout('monthly')" :disabled="busy"
                                class="mt-4 rounded-xl bg-off-black text-off-white text-sm font-semibold py-2.5 disabled:opacity-50">Subscribe</button>
                    </div>
                    <div class="rounded-2xl border-2 border-primary p-5 flex flex-col relative">
                        <span class="absolute -top-2.5 right-4 rounded-full bg-primary text-off-black text-xs font-bold px-2 py-0.5">Save 12%</span>
                        <p class="font-semibold">3 months</p>
                        <p class="mt-1"><span class="text-3xl font-black">€129</span><span class="text-off-black/50">/3 mo</span></p>
                        <p class="text-sm text-off-black/60 mt-2 flex-1">€43/mo billed quarterly. Cancel anytime.</p>
                        <button @click="checkout('three_months')" :disabled="busy"
                                class="mt-4 rounded-xl bg-off-black text-off-white text-sm font-semibold py-2.5 disabled:opacity-50">Subscribe</button>
                    </div>
                </div>
                <p class="text-xs text-off-black/50 mt-3">Secure payment by Stripe. You'll be redirected to complete checkout.</p>
            </div>
        </template>
    </main>
</div>

@push('scripts')
<script>
    function subscriptionPage() {
        return {
            loading: true, busy: false, error: '',
            isBusiness: false, subActive: false, statusLine: '',
            async init() {
                if (!window.kb.requireAuth()) return;
                const me = await window.kb.api('/auth/me');
                if (!me.ok) { window.kb.logout(); return; }
                this.isBusiness = (me.json?.data?.user_type === 'business');
                if (this.isBusiness) {
                    const res = await window.kb.api('/me/subscription');
                    const sub = res.ok ? res.json?.data : null;
                    if (sub && sub.is_active) {
                        this.subActive = true;
                        this.statusLine = sub.current_period_end
                            ? ('Renews ' + new Date(sub.current_period_end).toLocaleDateString())
                            : 'Your subscription is active.';
                    }
                }
                this.loading = false;
            },
            async checkout(plan) {
                this.error = ''; this.busy = true;
                const { ok, json } = await window.kb.api('/me/subscription/checkout', {
                    method: 'POST',
                    body: {
                        plan,
                        success_url: location.origin + '/welcome?paid=1',
                        cancel_url: location.origin + '/subscription',
                    },
                });
                if (ok && json?.data?.checkout_url) {
                    location.href = json.data.checkout_url;
                } else {
                    this.busy = false;
                    this.error = json?.message || 'Could not start checkout. Please try again.';
                }
            },
            async openPortal() {
                this.error = ''; this.busy = true;
                const { ok, json } = await window.kb.api('/me/subscription/portal', {
                    method: 'POST', body: { return_url: location.origin + '/subscription' },
                });
                if (ok && json?.data?.portal_url) {
                    location.href = json.data.portal_url;
                } else {
                    this.busy = false;
                    this.error = json?.message || 'Could not open the billing portal. Please try again.';
                }
            },
        };
    }
</script>
@endpush
@endsection
