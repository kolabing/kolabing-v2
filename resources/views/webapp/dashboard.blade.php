@extends('webapp.layout')
@section('title', __('webapp.nav.home'))

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
                        <p class="font-semibold">{{ __('webapp.dashboard.subscription') }}</p>
                        <p class="text-sm text-off-black/60" x-text="subLabel"></p>
                    </div>
                    <a href="{{ $base }}/subscription"
                       class="rounded-xl bg-off-black text-off-white text-sm font-semibold px-4 py-2 whitespace-nowrap"
                       x-text="subActive ? t('dashboard.manage') : t('dashboard.subscribe')"></a>
                </div>
            </div>
        </template>

        <div class="mt-6 grid sm:grid-cols-2 gap-4">
            <a href="{{ $base }}/feed" class="rounded-2xl border border-off-black/10 p-5 hover:border-off-black/30">
                <p class="font-semibold">{{ __('webapp.dashboard.feed_title') }}</p>
                <p class="text-sm text-off-black/60 mt-1">{{ __('webapp.dashboard.feed_desc') }}</p>
            </a>
            <a href="{{ $base }}/kolabs" class="rounded-2xl border border-off-black/10 p-5 hover:border-off-black/30">
                <p class="font-semibold">{{ __('webapp.dashboard.kolabs_title') }}</p>
                <p class="text-sm text-off-black/60 mt-1">{{ __('webapp.dashboard.kolabs_desc') }}</p>
            </a>
        </div>

        <div class="mt-8 rounded-2xl bg-off-black text-off-white p-5 flex items-center justify-between gap-4">
            <div>
                <p class="font-semibold">{{ __('webapp.dashboard.app_title') }}</p>
                <p class="text-sm text-off-white/70">{{ __('webapp.dashboard.app_desc') }}</p>
            </div>
            <a href="{{ $base }}/welcome" class="rounded-xl bg-primary text-off-black text-sm font-semibold px-4 py-2 whitespace-nowrap">{{ __('webapp.common.open_app') }}</a>
        </div>
    </main>
</div>

@push('scripts')
<script>
    function dashboardPage() {
        return {
            greeting: t('dashboard.welcome'), isBusiness: false, subActive: false, subLabel: t('dashboard.sub_none'),
            async init() {
                if (!window.kb.requireAuth()) return;
                const me = await window.kb.api('/auth/me');
                if (!me.ok) { window.kb.logout(); return; }
                const u = me.json?.data || {};
                const name = u.display_name || u.name || u.email || '';
                this.greeting = name ? t('dashboard.welcome_name', { name }) : t('dashboard.welcome');
                this.isBusiness = (u.user_type === 'business');
                if (this.isBusiness) await this.loadSubscription();
            },
            async loadSubscription() {
                const res = await window.kb.api('/me/subscription');
                if (!res.ok) return;
                const sub = res.json?.data;
                if (sub && sub.is_active) {
                    this.subActive = true;
                    this.subLabel = sub.current_period_end
                        ? t('dashboard.sub_renews', { date: new Date(sub.current_period_end).toLocaleDateString() })
                        : t('dashboard.sub_active');
                } else {
                    this.subLabel = t('dashboard.sub_none');
                }
            },
        };
    }
</script>
@endpush
@endsection
