@extends('webapp.layout')
@section('title', __('webapp.community.overview.title'))

@section('body')
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), communityOverviewPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'community'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[1080px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        @include('webapp.partials.community-nav', ['communityActive' => 'overview'])

        <template x-if="canManageCommunity">
        <div>
            <template x-if="error">
                <div class="mt-5 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3" x-text="error"></div>
            </template>

            <template x-if="loading">
                <p class="mt-8 text-muted">{{ __('webapp.common.loading') }}</p>
            </template>

            <template x-if="!loading && stats">
            <div>
                {{-- ── Health strip ────────────────────────────────────── --}}
                <div class="mt-6 grid grid-cols-2 lg:grid-cols-5 gap-2.5">
                    <div class="bg-white border border-ink/[.08] rounded-2xl p-4 shadow-card">
                        <p class="text-[11px] font-semibold uppercase tracking-[.14em] text-muted">{{ __('webapp.community.overview.stat_members') }}</p>
                        <p class="mt-1 font-anton text-[26px] tabular-nums" x-text="stats.members.total"></p>
                        <p class="text-[11px] text-muted" x-text="t('community.overview.stat_active', { count: stats.members.active })"></p>
                    </div>
                    <div class="bg-white border border-ink/[.08] rounded-2xl p-4 shadow-card">
                        <p class="text-[11px] font-semibold uppercase tracking-[.14em] text-muted">{{ __('webapp.community.overview.stat_new') }}</p>
                        <p class="mt-1 font-anton text-[26px] tabular-nums" x-text="stats.members.new_this_month"></p>
                    </div>
                    <div class="bg-white border border-ink/[.08] rounded-2xl p-4 shadow-card">
                        <p class="text-[11px] font-semibold uppercase tracking-[.14em] text-muted">{{ __('webapp.community.overview.stat_dormant') }}</p>
                        <p class="mt-1 font-anton text-[26px] tabular-nums" x-text="stats.members.dormant_30d"></p>
                    </div>
                    <a :href="kbPath('/community/requests')" class="bg-white border border-ink/[.08] rounded-2xl p-4 shadow-card hover:border-ink/25 transition">
                        <p class="text-[11px] font-semibold uppercase tracking-[.14em] text-muted">{{ __('webapp.community.overview.stat_pending') }}</p>
                        <p class="mt-1 font-anton text-[26px] tabular-nums" x-text="stats.pending.join_requests + stats.pending.invitations"></p>
                    </a>
                    <div class="bg-white border border-ink/[.08] rounded-2xl p-4 shadow-card">
                        <p class="text-[11px] font-semibold uppercase tracking-[.14em] text-muted">{{ __('webapp.community.overview.stat_attendance') }}</p>
                        <p class="mt-1 font-anton text-[26px] tabular-nums" x-text="Math.round((stats.engagement.attendance_rate_30d || 0) * 100) + '%'"></p>
                    </div>
                </div>

                {{-- ── Quick actions ───────────────────────────────────── --}}
                <div class="mt-5 flex flex-wrap items-center gap-2.5">
                    <a :href="kbPath('/community/members')" class="h-11 px-5 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark transition flex items-center">
                        {{ __('webapp.community.overview.quick_invite') }}
                    </a>
                    <a :href="kbPath('/community/tiers')" class="h-11 px-4 rounded-pill bg-white border border-ink/[.12] text-sm font-bold hover:border-ink/30 transition flex items-center">
                        {{ __('webapp.community.overview.quick_tier') }}
                    </a>
                    <button type="button" @click="copyInviteLink()" class="h-11 px-4 rounded-pill bg-white border border-ink/[.12] text-sm font-bold hover:border-ink/30 transition">
                        {{ __('webapp.community.overview.quick_link') }}
                    </button>
                    <span x-show="copied" x-cloak class="text-sm font-semibold text-good-ink">{{ __('webapp.community.overview.copied') }}</span>
                </div>

                <div class="mt-6 grid lg:grid-cols-2 gap-2.5">
                    {{-- ── Tier distribution ───────────────────────────── --}}
                    <div class="bg-white border border-ink/[.08] rounded-2xl p-5 shadow-card">
                        <p class="text-[11px] font-semibold uppercase tracking-[.14em] text-muted">{{ __('webapp.community.overview.tiers_title') }}</p>

                        <template x-if="tieredMembers === 0">
                            <div class="mt-4">
                                <p class="text-sm text-muted">{{ __('webapp.community.overview.tiers_empty') }}</p>
                                <a :href="kbPath('/community/tiers')" class="mt-3 inline-flex h-9 items-center px-4 rounded-pill bg-cream-low text-sm font-bold">{{ __('webapp.community.overview.tiers_cta') }}</a>
                            </div>
                        </template>

                        <template x-if="tieredMembers > 0">
                            <div class="mt-4">
                                <div class="flex h-3 rounded-pill overflow-hidden bg-cream-low">
                                    <template x-for="tier in stats.tiers" :key="tier.tier_id">
                                        <div :style="'width:' + ((tier.member_count / tieredMembers) * 100) + '%; background:' + (tier.color || '#FFE28C')"
                                             :title="tier.name"></div>
                                    </template>
                                </div>
                                <ul class="mt-4 flex flex-col gap-1.5">
                                    <template x-for="tier in stats.tiers" :key="tier.tier_id">
                                        <li class="flex items-center gap-2.5 text-sm">
                                            <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="'background:' + (tier.color || '#FFE28C')"></span>
                                            <span class="font-semibold text-ink" x-text="tier.name"></span>
                                            <span class="flex-1"></span>
                                            <span class="text-muted tabular-nums" x-text="tier.member_count"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </template>
                    </div>

                    {{-- ── Top members ─────────────────────────────────── --}}
                    <div class="bg-white border border-ink/[.08] rounded-2xl p-5 shadow-card">
                        <p class="text-[11px] font-semibold uppercase tracking-[.14em] text-muted">{{ __('webapp.community.overview.top_title') }}</p>

                        <template x-if="stats.top_members.length === 0">
                            <p class="mt-4 text-sm text-muted">{{ __('webapp.community.overview.top_empty') }}</p>
                        </template>

                        <ul class="mt-4 flex flex-col gap-2">
                            <template x-for="(row, i) in stats.top_members" :key="row.profile_id">
                                <li class="flex items-center gap-3">
                                    <span class="w-6 text-center font-anton text-muted tabular-nums" x-text="i + 1"></span>
                                    <template x-if="row.avatar_url">
                                        <img :src="row.avatar_url" alt="" class="w-8 h-8 rounded-full object-cover shrink-0">
                                    </template>
                                    <template x-if="!row.avatar_url">
                                        <div class="w-8 h-8 rounded-full bg-primary/50 flex items-center justify-center text-xs font-bold shrink-0" x-text="window.kbInitial(row.name)"></div>
                                    </template>
                                    <span class="flex-1 min-w-0 font-semibold text-ink truncate" x-text="row.name"></span>
                                    <span class="shrink-0 font-bold tabular-nums" x-text="row.points"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>
            </template>
        </div>
        </template>
    </div>
    </main>
</div>
@endsection

@push('scripts')
<script>
    function communityOverviewPage() {
        return {
            t: (key, params) => window.t(key, params),
            kbPath: (p) => window.kbPath(p),
            loading: true,
            error: '',
            stats: null,
            copied: false,

            get communityId() { return this.activeCommunity?.id || null; },

            /** Denominator for the distribution bar: only tiered members. */
            get tieredMembers() {
                if (!this.stats) return 0;
                return this.stats.tiers.reduce((sum, tier) => sum + (tier.member_count || 0), 0);
            },

            async init() {
                await this.loadShell();
                await this.loadManagedCommunities();
                if (!this.communityId) { this.loading = false; return; }
                await this.load();
            },

            async load() {
                this.loading = true;
                const res = await window.kb.api('/communities/' + this.communityId + '/stats');
                this.loading = false;
                if (!res.ok) { this.error = window.kb.errorText(res, window.t('community.overview.load_error')); return; }
                this.stats = res.json?.data || null;
                const pending = this.stats?.pending || {};
                this.communityPending = (pending.join_requests || 0) + (pending.invitations || 0);
            },

            async copyInviteLink() {
                const url = this.activeCommunity?.invite_url;
                if (!url) return;
                try {
                    await navigator.clipboard.writeText(url);
                    this.copied = true;
                    setTimeout(() => { this.copied = false; }, 2500);
                } catch (e) {
                    // Clipboard is permission-gated; show the raw URL to copy by hand.
                    this.error = url;
                }
            },
        };
    }
</script>
@endpush
