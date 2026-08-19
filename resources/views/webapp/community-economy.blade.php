@extends('webapp.layout')
@section('title', __('webapp.community.economy.title'))

@section('body')
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), communityEconomyPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'community'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[880px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        @include('webapp.partials.community-nav', ['communityActive' => 'economy'])

        <template x-if="canManageCommunity">
        <div>
            <div class="mt-6 flex p-1 bg-white border border-ink/[.12] rounded-pill shadow-card">
                <template x-for="tb in tabs" :key="tb.value">
                    <button type="button" @click="setTab(tb.value)"
                            class="flex-1 h-[34px] rounded-pill text-[12.5px] font-bold tracking-[.4px] transition"
                            :class="tab === tb.value ? 'bg-ink text-white' : 'text-muted'"
                            x-text="tb.label"></button>
                </template>
            </div>

            <template x-if="error">
                <div class="mt-5 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
            </template>

            <div class="mt-5 flex justify-end">
                <button type="button" @click="openForm(null)" class="h-11 px-5 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark transition"
                        x-text="t('common.select') + ' +'"></button>
            </div>

            {{-- ── Editor ──────────────────────────────────────────────── --}}
            <template x-if="form">
                <div class="mt-3 bg-white border border-ink/[.12] rounded-2xl p-5 shadow-card">
                    <div class="grid sm:grid-cols-2 gap-3.5">
                        <div class="sm:col-span-2">
                            <label class="block text-[12px] font-bold text-body" x-text="labels.title"></label>
                            <input type="text" x-model="form.title" maxlength="120" class="mt-1.5 w-full h-11 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">
                        </div>

                        {{-- Goals --}}
                        <template x-if="tab === 'goals'">
                            <div>
                                <label class="block text-[12px] font-bold text-body">{{ __('webapp.community.economy.goal_earn_type') }}</label>
                                <select x-model="form.earn_type" class="mt-1.5 w-full h-11 px-3 rounded-2xl bg-white border border-ink/[.12] text-sm font-semibold">
                                    <option value="event_check_ins">{{ __('webapp.community.tiers.rule_events') }}</option>
                                    <option value="challenge">{{ __('webapp.community.economy.tab_goals') }}</option>
                                    <option value="days_in_community">{{ __('webapp.community.tiers.rule_tenure') }}</option>
                                </select>
                            </div>
                        </template>
                        <template x-if="tab === 'goals'">
                            <div>
                                <label class="block text-[12px] font-bold text-body">{{ __('webapp.community.economy.goal_target') }}</label>
                                <input type="number" min="1" x-model.number="form.target" class="mt-1.5 w-full h-11 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">
                            </div>
                        </template>
                        <template x-if="tab === 'goals'">
                            <div>
                                <label class="block text-[12px] font-bold text-body">{{ __('webapp.community.economy.goal_points') }}</label>
                                <input type="number" min="0" x-model.number="form.reward_points" class="mt-1.5 w-full h-11 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">
                            </div>
                        </template>

                        {{-- Rewards --}}
                        <template x-if="tab === 'rewards'">
                            <div class="sm:col-span-2">
                                <label class="block text-[12px] font-bold text-body">{{ __('webapp.community.economy.reward_description') }}</label>
                                <textarea x-model="form.description" rows="3" class="mt-1.5 w-full px-4 py-3 rounded-2xl bg-white border border-ink/[.12] text-sm"></textarea>
                            </div>
                        </template>
                        <template x-if="tab === 'rewards'">
                            <div>
                                <label class="block text-[12px] font-bold text-body">{{ __('webapp.community.economy.reward_cost') }}</label>
                                <input type="number" min="0" x-model.number="form.cost_points" class="mt-1.5 w-full h-11 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">
                            </div>
                        </template>
                        <template x-if="tab === 'rewards'">
                            <div>
                                <label class="block text-[12px] font-bold text-body">{{ __('webapp.community.economy.reward_stock') }}</label>
                                <input type="number" min="0" x-model.number="form.stock" class="mt-1.5 w-full h-11 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">
                            </div>
                        </template>

                        {{-- Badges --}}
                        <template x-if="tab === 'badges'">
                            <div>
                                <label class="block text-[12px] font-bold text-body">{{ __('webapp.community.economy.badge_icon') }}</label>
                                <input type="text" x-model="form.icon" maxlength="60" class="mt-1.5 w-full h-11 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">
                            </div>
                        </template>
                        <template x-if="tab === 'badges'">
                            <div>
                                <label class="block text-[12px] font-bold text-body">{{ __('webapp.community.economy.badge_criteria') }}</label>
                                <select x-model="form.criteria_type" class="mt-1.5 w-full h-11 px-3 rounded-2xl bg-white border border-ink/[.12] text-sm font-semibold">
                                    <option value="points_threshold">{{ __('webapp.community.tiers.rule_xp') }}</option>
                                    <option value="event_check_ins">{{ __('webapp.community.tiers.rule_events') }}</option>
                                    <option value="days_in_community">{{ __('webapp.community.tiers.rule_tenure') }}</option>
                                    <option value="challenges_completed">{{ __('webapp.community.economy.tab_goals') }}</option>
                                </select>
                            </div>
                        </template>
                        <template x-if="tab === 'badges'">
                            <div>
                                <label class="block text-[12px] font-bold text-body">{{ __('webapp.community.economy.badge_value') }}</label>
                                <input type="number" min="0" x-model.number="form.criteria_value" class="mt-1.5 w-full h-11 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">
                            </div>
                        </template>

                        <div class="flex items-end">
                            <label class="flex items-center gap-2.5 text-sm font-semibold">
                                <input type="checkbox" x-model="form.is_active">
                                {{ __('webapp.community.economy.active') }}
                            </label>
                        </div>
                    </div>

                    <div class="mt-6 flex gap-2.5">
                        <button type="button" @click="form = null" class="h-11 px-5 rounded-pill bg-white border border-ink/[.12] text-sm font-bold">{{ __('webapp.common.cancel') }}</button>
                        <button type="button" @click="save()" :disabled="busy || !form.title.trim()"
                                class="h-11 px-5 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn disabled:opacity-50"
                                x-text="busy ? t('common.saving') : t('common.save')"></button>
                    </div>
                </div>
            </template>

            <template x-if="loading">
                <p class="mt-8 text-muted">{{ __('webapp.common.loading') }}</p>
            </template>

            <template x-if="!loading && items.length === 0 && !form">
                <div class="mt-5 bg-white border border-ink/[.08] rounded-2xl p-10 text-center shadow-card">
                    <p class="text-sm text-muted" x-text="labels.empty"></p>
                </div>
            </template>

            {{-- ── List ────────────────────────────────────────────────── --}}
            <div x-show="!loading && items.length" x-cloak class="mt-5 flex flex-col gap-2.5">
                <template x-for="item in items" :key="item.id">
                    <div class="bg-white border border-ink/[.08] rounded-2xl p-4 shadow-card flex items-center gap-3.5">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-ink truncate">
                                <span x-text="item.title"></span>
                                <span x-show="!item.is_active" x-cloak class="ml-2 px-2 py-0.5 rounded-pill bg-ink/10 text-[10px] font-bold uppercase tracking-wide text-muted">off</span>
                            </p>
                            <p class="text-[11px] text-muted" x-text="summary(item)"></p>
                        </div>
                        <button type="button" @click="openForm(item)" class="h-9 px-3 rounded-pill bg-white border border-ink/[.12] text-[12.5px] font-bold">{{ __('webapp.common.edit') }}</button>
                        <template x-if="confirmDelete !== item.id">
                            <button type="button" @click="confirmDelete = item.id" class="h-9 px-3 text-[12.5px] font-bold text-bad-ink">{{ __('webapp.common.delete') }}</button>
                        </template>
                        <template x-if="confirmDelete === item.id">
                            <span class="inline-flex items-center gap-2">
                                <button type="button" @click="destroy(item)" class="h-9 px-3 text-[12.5px] font-bold text-bad-ink">{{ __('webapp.common.confirm') }}</button>
                                <button type="button" @click="confirmDelete = null" class="h-9 px-2 text-[12.5px] font-semibold text-muted">{{ __('webapp.common.cancel') }}</button>
                            </span>
                        </template>
                    </div>
                </template>
            </div>
        </div>
        </template>
    </div>
    </main>
</div>
@endsection

@push('scripts')
<script>
    function communityEconomyPage() {
        return {
            t: (key, params) => window.t(key, params),
            tab: 'goals',
            loading: true,
            busy: false,
            error: '',
            items: [],
            form: null,
            confirmDelete: null,

            get communityId() { return this.activeCommunity?.id || null; },

            get tabs() {
                return [
                    { value: 'goals', label: window.t('community.economy.tab_goals') },
                    { value: 'rewards', label: window.t('community.economy.tab_rewards') },
                    { value: 'badges', label: window.t('community.economy.tab_badges') },
                ];
            },

            get labels() {
                const map = {
                    goals: { title: window.t('community.economy.goal_title'), empty: window.t('community.economy.goals_empty') },
                    rewards: { title: window.t('community.economy.reward_title'), empty: window.t('community.economy.rewards_empty') },
                    badges: { title: window.t('community.economy.badge_title'), empty: window.t('community.economy.badges_empty') },
                };
                return map[this.tab];
            },

            /** Collection path per tab; the singular path is what update/delete use. */
            get collectionPath() { return '/communities/' + this.communityId + '/' + this.tab; },
            singularPath(id) {
                const map = { goals: '/goals/', rewards: '/rewards/', badges: '/badges/' };
                return map[this.tab] + id;
            },

            async init() {
                await this.loadShell();
                await this.loadManagedCommunities();
                if (!this.communityId) { this.loading = false; return; }
                await this.load();
                this.loadCommunityPending();
            },

            setTab(value) {
                this.tab = value;
                this.form = null;
                this.confirmDelete = null;
                this.load();
            },

            async load() {
                this.loading = true;
                const res = await window.kb.api(this.collectionPath);
                this.loading = false;
                this.items = res.ok ? window.kb.rows(res) : [];
            },

            summary(item) {
                if (this.tab === 'goals') {
                    return window.kbHumanize(item.earn_type) + ' · ' + item.target + ' → +' + item.reward_points;
                }
                if (this.tab === 'rewards') {
                    return item.cost_points + ' pts' + (item.stock === null ? '' : ' · ' + item.stock);
                }
                return window.kbHumanize(item.criteria_type) + ' · ' + item.criteria_value;
            },

            openForm(item) {
                const base = { id: item?.id || null, title: item?.title || '', is_active: item ? !!item.is_active : true };

                if (this.tab === 'goals') {
                    this.form = {
                        ...base,
                        earn_type: item?.earn_type || 'event_check_ins',
                        target: item?.target ?? 1,
                        reward_points: item?.reward_points ?? 10,
                    };
                } else if (this.tab === 'rewards') {
                    this.form = {
                        ...base,
                        description: item?.description || '',
                        cost_points: item?.cost_points ?? 100,
                        stock: item?.stock ?? null,
                    };
                } else {
                    this.form = {
                        ...base,
                        icon: item?.icon || '',
                        criteria_type: item?.criteria_type || 'points_threshold',
                        criteria_value: item?.criteria_value ?? 100,
                    };
                }

                this.error = '';
            },

            body() {
                const { id, ...rest } = this.form;
                return { ...rest, title: rest.title.trim() };
            },

            async save() {
                this.busy = true;
                this.error = '';

                const res = this.form.id
                    // Goals/rewards/badges use PUT for updates (tiers use PATCH).
                    ? await window.kb.api(this.singularPath(this.form.id), { method: 'PUT', body: this.body() })
                    : await window.kb.api(this.collectionPath, { method: 'POST', body: this.body() });

                this.busy = false;

                if (res.ok) { this.form = null; await this.load(); return; }
                this.error = window.kb.errorText(res, window.t('community.economy.save_error'));
            },

            async destroy(item) {
                this.confirmDelete = null;
                this.error = '';
                const res = await window.kb.api(this.singularPath(item.id), { method: 'DELETE' });
                if (res.ok) await this.load();
                else this.error = window.kb.errorText(res, window.t('community.economy.delete_error'));
            },
        };
    }
</script>
@endpush
