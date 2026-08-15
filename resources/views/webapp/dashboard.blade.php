@extends('webapp.layout')
@section('title', 'Home')

@section('body')
<div x-data="dashboardPage()" x-init="init()">
    @include('webapp.partials.nav', ['active' => 'dashboard'])

    <main class="max-w-4xl mx-auto px-5 py-8">
        <h1 class="font-montserrat font-black text-2xl tracking-tight">
            <span x-text="greeting"></span>
        </h1>

        <template x-if="isBusiness">
            <div class="mt-5 rounded-2xl border border-off-black/10 p-5">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="font-semibold">Subscription</p>
                        <p class="text-sm text-off-black/60" x-text="subLabel"></p>
                    </div>
                    <a href="/subscription"
                       class="rounded-xl bg-off-black text-off-white text-sm font-semibold px-4 py-2 whitespace-nowrap"
                       x-text="subActive ? 'Manage' : 'Subscribe'"></a>
                </div>
            </div>
        </template>

        <div class="mt-6 grid sm:grid-cols-2 gap-4">
            <a href="/feed" class="rounded-2xl border border-off-black/10 p-5 hover:border-off-black/30">
                <p class="font-semibold">Feed</p>
                <p class="text-sm text-off-black/60 mt-1">Discover Kolabs from communities and businesses near you.</p>
            </a>
            <a href="/kolabs" class="rounded-2xl border border-off-black/10 p-5 hover:border-off-black/30">
                <p class="font-semibold">Your Kolabs</p>
                <p class="text-sm text-off-black/60 mt-1">Create and manage your collaborations.</p>
            </a>
        </div>

        <div class="mt-8 rounded-2xl bg-off-black text-off-white p-5 flex items-center justify-between gap-4">
            <div>
                <p class="font-semibold">Get the full experience</p>
                <p class="text-sm text-off-white/70">Chat, notifications and check-ins live in the app.</p>
            </div>
            <a href="/welcome" class="rounded-xl bg-primary text-off-black text-sm font-semibold px-4 py-2 whitespace-nowrap">Open the app</a>
        </div>
    </main>
</div>

@push('scripts')
<script>
    function dashboardPage() {
        return {
            greeting: 'Welcome', isBusiness: false, subActive: false, subLabel: 'No active subscription',
            async init() {
                if (!window.kb.requireAuth()) return;
                const me = await window.kb.api('/auth/me');
                if (!me.ok) { window.kb.logout(); return; }
                const u = me.json?.data || {};
                const name = u.display_name || u.name || u.email || '';
                this.greeting = name ? ('Welcome, ' + name) : 'Welcome';
                this.isBusiness = (u.user_type === 'business');
                if (this.isBusiness) await this.loadSubscription();
            },
            async loadSubscription() {
                const res = await window.kb.api('/me/subscription');
                if (!res.ok) return;
                const sub = res.json?.data;
                if (sub && sub.is_active) {
                    this.subActive = true;
                    this.subLabel = 'Active' + (sub.current_period_end ? ' · renews ' + new Date(sub.current_period_end).toLocaleDateString() : '');
                } else {
                    this.subLabel = 'No active subscription — subscribe to publish Kolabs.';
                }
            },
        };
    }
</script>
@endpush
@endsection
