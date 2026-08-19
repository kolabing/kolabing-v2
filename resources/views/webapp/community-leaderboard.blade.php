@extends('webapp.layout')
@section('title', __('webapp.community.leaderboard.title'))

@section('body')
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), communityLeaderboardPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'community'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[880px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        @include('webapp.partials.community-nav', ['communityActive' => 'leaderboard'])

        <template x-if="canManageCommunity">
        <div>
            <template x-if="error">
                <div class="mt-5 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3" x-text="error"></div>
            </template>

            <template x-if="loading">
                <p class="mt-8 text-muted">{{ __('webapp.common.loading') }}</p>
            </template>

            <template x-if="!loading && rows.length === 0">
                <div class="mt-6 bg-white border border-ink/[.08] rounded-2xl p-10 text-center shadow-card">
                    <p class="text-sm text-muted">{{ __('webapp.community.leaderboard.empty') }}</p>
                </div>
            </template>

            <div x-show="!loading && rows.length" x-cloak class="mt-6 bg-white border border-ink/[.08] rounded-2xl shadow-card overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ink/[.08] text-left text-muted">
                            <th class="py-3 pl-4 w-14">{{ __('webapp.community.leaderboard.col_rank') }}</th>
                            <th class="py-3">{{ __('webapp.community.leaderboard.col_member') }}</th>
                            <th class="py-3 hidden sm:table-cell">{{ __('webapp.community.leaderboard.col_tier') }}</th>
                            <th class="py-3 text-right hidden sm:table-cell">{{ __('webapp.community.leaderboard.col_badges') }}</th>
                            <th class="py-3 pr-4 text-right">{{ __('webapp.community.leaderboard.col_points') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in rows" :key="row.profile_id">
                            <tr class="border-b border-ink/[.05] last:border-0" :class="row.rank <= 3 ? 'bg-primary-tint/40' : ''">
                                <td class="py-3 pl-4 font-anton text-[17px] tabular-nums" x-text="row.rank"></td>
                                <td class="py-3">
                                    <div class="flex items-center gap-2.5">
                                        <template x-if="row.profile_photo">
                                            <img :src="row.profile_photo" alt="" class="w-8 h-8 rounded-full object-cover shrink-0">
                                        </template>
                                        <template x-if="!row.profile_photo">
                                            <div class="w-8 h-8 rounded-full bg-primary/50 flex items-center justify-center text-xs font-bold shrink-0" x-text="window.kbInitial(row.display_name)"></div>
                                        </template>
                                        <span class="font-semibold text-ink truncate" x-text="row.display_name"></span>
                                    </div>
                                </td>
                                <td class="py-3 hidden sm:table-cell">
                                    <span x-show="row.tier" x-cloak class="inline-flex items-center gap-1.5 text-[12.5px] font-semibold">
                                        <span class="w-2 h-2 rounded-full" :style="'background:' + (row.tier?.color || '#FFE28C')"></span>
                                        <span x-text="row.tier?.name"></span>
                                    </span>
                                </td>
                                <td class="py-3 text-right tabular-nums hidden sm:table-cell" x-text="row.badge_count"></td>
                                <td class="py-3 pr-4 text-right font-bold tabular-nums" x-text="row.points"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
        </template>
    </div>
    </main>
</div>
@endsection

@push('scripts')
<script>
    function communityLeaderboardPage() {
        return {
            t: (key, params) => window.t(key, params),
            loading: true,
            error: '',
            rows: [],

            get communityId() { return this.activeCommunity?.id || null; },

            async init() {
                await this.loadShell();
                await this.loadManagedCommunities();
                if (!this.communityId) { this.loading = false; return; }
                await this.load();
                this.loadCommunityPending();
            },

            async load() {
                this.loading = true;
                const res = await window.kb.api('/communities/' + this.communityId + '/leaderboard');
                this.loading = false;
                if (!res.ok) { this.error = window.kb.errorText(res, window.t('community.leaderboard.load_error')); return; }
                this.rows = window.kb.rows(res, 'leaderboard');
            },
        };
    }
</script>
@endpush
