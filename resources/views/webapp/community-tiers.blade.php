@extends('webapp.layout')
@section('title', __('webapp.community.tiers.title'))

@section('body')
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), communityTiersPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'community'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[880px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        @include('webapp.partials.community-nav', ['communityActive' => 'tiers'])

        <template x-if="canManageCommunity">
        <div>
            {{-- ROLES §8.3 D1: a tier is a status ladder, not admin power. --}}
            <p class="mt-5 text-sm text-muted">{{ __('webapp.community.tiers.help') }}</p>

            <template x-if="error">
                <div class="mt-5 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
            </template>

            <div class="mt-5 flex justify-end">
                <button type="button" @click="openForm(null)" class="h-11 px-5 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark transition">
                    {{ __('webapp.community.tiers.new') }}
                </button>
            </div>

            <template x-if="loading">
                <p class="mt-8 text-muted">{{ __('webapp.common.loading') }}</p>
            </template>

            <template x-if="!loading && tiers.length === 0 && !form">
                <div class="mt-5 bg-white border border-ink/[.08] rounded-2xl p-10 text-center shadow-card">
                    <p class="text-sm text-muted">{{ __('webapp.community.tiers.empty') }}</p>
                </div>
            </template>

            {{-- ── Editor ──────────────────────────────────────────────── --}}
            <template x-if="form">
                <div class="mt-5 bg-white border border-ink/[.12] rounded-2xl p-5 shadow-card">
                    <p class="font-anton text-[19px] tracking-[.5px]" x-text="form.id ? t('community.tiers.edit') : t('community.tiers.new')"></p>

                    <div class="mt-4 grid sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-[12px] font-bold text-body">{{ __('webapp.community.tiers.name') }}</label>
                            <input type="text" x-model="form.name" maxlength="60" class="mt-1.5 w-full h-11 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">
                        </div>
                        <div>
                            <label class="block text-[12px] font-bold text-body">{{ __('webapp.community.tiers.rank') }}</label>
                            <input type="number" min="1" x-model.number="form.rank" class="mt-1.5 w-full h-11 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">
                        </div>
                        <div>
                            <label class="block text-[12px] font-bold text-body">{{ __('webapp.community.tiers.color') }}</label>
                            <input type="color" x-model="form.color" class="mt-1.5 w-full h-11 px-2 rounded-2xl bg-white border border-ink/[.12]">
                        </div>
                        <div>
                            <label class="block text-[12px] font-bold text-body">{{ __('webapp.community.tiers.rule') }}</label>
                            <select x-model="form.assignment_rule" class="mt-1.5 w-full h-11 px-3 rounded-2xl bg-white border border-ink/[.12] text-sm font-semibold">
                                <option value="manual">{{ __('webapp.community.tiers.rule_manual') }}</option>
                                <option value="xp_threshold">{{ __('webapp.community.tiers.rule_xp') }}</option>
                                <option value="tenure">{{ __('webapp.community.tiers.rule_tenure') }}</option>
                                <option value="events_attended">{{ __('webapp.community.tiers.rule_events') }}</option>
                            </select>
                        </div>
                        <div x-show="form.assignment_rule !== 'manual'" x-cloak>
                            <label class="block text-[12px] font-bold text-body">{{ __('webapp.community.tiers.threshold') }}</label>
                            <input type="number" min="0" x-model.number="form.threshold" class="mt-1.5 w-full h-11 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">
                            <p class="mt-1.5 text-[11px] text-muted">{{ __('webapp.community.tiers.threshold_help') }}</p>
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2.5 text-sm font-semibold">
                                <input type="checkbox" x-model="form.is_default">
                                {{ __('webapp.community.tiers.is_default') }}
                            </label>
                        </div>
                    </div>

                    <p class="mt-5 text-[11px] font-semibold tracking-[.16em] uppercase text-muted">{{ __('webapp.community.tiers.permissions') }}</p>
                    <p class="mt-1 text-[11px] text-muted">{{ __('webapp.community.tiers.perm_help') }}</p>

                    <div class="mt-3 grid sm:grid-cols-2 gap-3.5">
                        <template x-for="field in permissionFields" :key="field.key">
                            <div>
                                <label class="block text-[12px] font-bold text-body" x-text="field.label"></label>
                                <input type="text" :value="form.permissions[field.key].join(', ')"
                                       @input="form.permissions[field.key] = $event.target.value.split(',').map(v => v.trim()).filter(Boolean)"
                                       class="mt-1.5 w-full h-11 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">
                            </div>
                        </template>
                    </div>

                    <div class="mt-6 flex gap-2.5">
                        <button type="button" @click="form = null" class="h-11 px-5 rounded-pill bg-white border border-ink/[.12] text-sm font-bold">{{ __('webapp.common.cancel') }}</button>
                        <button type="button" @click="save()" :disabled="busy || !form.name.trim()"
                                class="h-11 px-5 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn disabled:opacity-50"
                                x-text="busy ? t('common.saving') : t('common.save')"></button>
                    </div>
                </div>
            </template>

            {{-- ── List ────────────────────────────────────────────────── --}}
            <div x-show="!loading && tiers.length" x-cloak class="mt-5 flex flex-col gap-2.5">
                <template x-for="tier in tiers" :key="tier.id">
                    <div class="bg-white border border-ink/[.08] rounded-2xl p-4 shadow-card flex items-center gap-3.5">
                        <span class="w-4 h-4 rounded-full shrink-0" :style="'background:' + (tier.color || '#FFE28C')"></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-ink truncate">
                                <span x-text="tier.name"></span>
                                <span x-show="tier.is_default" x-cloak class="ml-2 px-2 py-0.5 rounded-pill bg-cream-low text-[10px] font-bold uppercase tracking-wide">{{ __('webapp.common.select') }}</span>
                            </p>
                            <p class="text-[11px] text-muted">
                                <span x-text="ruleLabel(tier)"></span>
                                <span aria-hidden="true">·</span>
                                <span x-text="t('community.tiers.members_count', { count: memberCount(tier.id) })"></span>
                            </p>
                        </div>
                        <button type="button" @click="openForm(tier)" class="h-9 px-3 rounded-pill bg-white border border-ink/[.12] text-[12.5px] font-bold">{{ __('webapp.common.edit') }}</button>
                        <template x-if="confirmDelete !== tier.id">
                            <button type="button" @click="confirmDelete = tier.id" class="h-9 px-3 text-[12.5px] font-bold text-bad-ink">{{ __('webapp.common.delete') }}</button>
                        </template>
                        <template x-if="confirmDelete === tier.id">
                            <span class="inline-flex items-center gap-2">
                                <button type="button" @click="destroy(tier)" class="h-9 px-3 text-[12.5px] font-bold text-bad-ink">{{ __('webapp.common.confirm') }}</button>
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
    function communityTiersPage() {
        return {
            t: (key, params) => window.t(key, params),
            loading: true,
            busy: false,
            error: '',
            tiers: [],
            counts: {},
            form: null,
            confirmDelete: null,

            get communityId() { return this.activeCommunity?.id || null; },

            get permissionFields() {
                return [
                    { key: 'view', label: window.t('community.tiers.perm_view') },
                    { key: 'chat_channels', label: window.t('community.tiers.perm_chat') },
                    { key: 'perks', label: window.t('community.tiers.perm_perks') },
                    { key: 'capabilities', label: window.t('community.tiers.perm_capabilities') },
                ];
            },

            async init() {
                await this.loadShell();
                await this.loadManagedCommunities();
                if (!this.communityId) { this.loading = false; return; }
                await this.load();
            },

            async load() {
                this.loading = true;
                const [tiers, stats] = await Promise.all([
                    window.kb.api('/communities/' + this.communityId + '/tiers'),
                    window.kb.api('/communities/' + this.communityId + '/stats'),
                ]);
                this.loading = false;
                this.tiers = tiers.ok ? window.kb.rows(tiers) : [];
                this.counts = {};
                if (stats.ok) {
                    (stats.json?.data?.tiers || []).forEach(row => { this.counts[row.tier_id] = row.member_count; });
                }
                this.confirmDelete = null;
            },

            memberCount(tierId) { return this.counts[tierId] || 0; },

            ruleLabel(tier) {
                const map = {
                    manual: window.t('community.tiers.rule_manual'),
                    xp_threshold: window.t('community.tiers.rule_xp'),
                    tenure: window.t('community.tiers.rule_tenure'),
                    events_attended: window.t('community.tiers.rule_events'),
                };
                const label = map[tier.assignment_rule] || tier.assignment_rule;
                return tier.assignment_rule === 'manual' ? label : label + ' · ' + (tier.threshold ?? 0);
            },

            openForm(tier) {
                const perms = tier?.permissions || {};
                this.form = {
                    id: tier?.id || null,
                    name: tier?.name || '',
                    rank: tier?.rank ?? (this.tiers.length + 1),
                    color: tier?.color || '#FFE28C',
                    assignment_rule: tier?.assignment_rule || 'manual',
                    threshold: tier?.threshold ?? null,
                    is_default: !!tier?.is_default,
                    permissions: {
                        view: perms.view || [],
                        chat_channels: perms.chat_channels || [],
                        perks: perms.perks || [],
                        capabilities: perms.capabilities || [],
                    },
                };
                this.error = '';
            },

            async save() {
                this.busy = true;
                this.error = '';

                const body = {
                    name: this.form.name.trim(),
                    rank: this.form.rank,
                    color: this.form.color,
                    assignment_rule: this.form.assignment_rule,
                    // The API requires a threshold for every non-manual rule.
                    threshold: this.form.assignment_rule === 'manual' ? null : (this.form.threshold ?? 0),
                    is_default: this.form.is_default,
                    permissions: this.form.permissions,
                };

                const res = this.form.id
                    ? await window.kb.api('/tiers/' + this.form.id, { method: 'PATCH', body })
                    : await window.kb.api('/communities/' + this.communityId + '/tiers', { method: 'POST', body });

                this.busy = false;

                if (res.ok) { this.form = null; await this.load(); return; }
                this.error = window.kb.errorText(res, window.t('community.tiers.save_error'));
            },

            async destroy(tier) {
                this.confirmDelete = null;
                this.error = '';
                const res = await window.kb.api('/tiers/' + tier.id, { method: 'DELETE' });
                if (res.ok) await this.load();
                else this.error = window.kb.errorText(res, window.t('community.tiers.delete_error'));
            },
        };
    }
</script>
@endpush
