@extends('webapp.layout')
@section('title', __('webapp.community.members.title'))

@section('body')
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), communityMembersPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'community'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[1080px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        @include('webapp.partials.community-nav', ['communityActive' => 'members'])

        <template x-if="canManageCommunity">
        <div>
            {{-- ── Toolbar ─────────────────────────────────────────────── --}}
            <div class="mt-6 flex flex-wrap items-center gap-2.5">
                <div class="relative flex-1 min-w-[220px]">
                    <input type="search" x-model="filters.search" @input="onSearch()"
                           placeholder="{{ __('webapp.community.members.search_placeholder') }}"
                           class="w-full h-11 pl-10 pr-4 rounded-pill bg-white border border-ink/[.12] text-sm">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                </div>

                <select x-model="filters.status" @change="reload()" class="h-11 px-3 rounded-pill bg-white border border-ink/[.12] text-sm font-semibold">
                    <option value="">{{ __('webapp.community.members.status_current') }}</option>
                    <option value="active">{{ __('webapp.community.members.status_active') }}</option>
                    <option value="inactive">{{ __('webapp.community.members.status_inactive') }}</option>
                    <option value="removed">{{ __('webapp.community.members.status_removed') }}</option>
                    <option value="all">{{ __('webapp.community.members.status_all') }}</option>
                </select>

                <select x-model="filters.tier_id" @change="reload()" class="h-11 px-3 rounded-pill bg-white border border-ink/[.12] text-sm font-semibold">
                    <option value="">{{ __('webapp.community.members.tier_any') }}</option>
                    <option value="none">{{ __('webapp.community.members.tier_none') }}</option>
                    <template x-for="t in tiers" :key="t.id">
                        <option :value="t.id" x-text="t.name"></option>
                    </template>
                </select>

                <button type="button" @click="toggleManagersOnly()"
                        class="h-11 px-4 rounded-pill border text-sm font-bold transition"
                        :class="filters.can_manage === '1' ? 'bg-ink text-white border-ink' : 'bg-white text-body border-ink/[.12]'">
                    {{ __('webapp.community.members.managers_only') }}
                </button>

                <div class="flex-1"></div>

                <button type="button" @click="openAdd()" class="h-11 px-4 rounded-pill bg-white border border-ink/[.12] text-sm font-bold hover:border-ink/30 transition">
                    {{ __('webapp.community.members.add') }}
                </button>
                <button type="button" @click="openInvite()" class="h-11 px-5 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark transition">
                    {{ __('webapp.community.members.invite') }}
                </button>
            </div>

            <template x-if="error">
                <div class="mt-5 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
            </template>

            {{-- ── Bulk bar ────────────────────────────────────────────── --}}
            <div x-show="selected.length" x-cloak class="mt-4 flex flex-wrap items-center gap-2.5 bg-ink text-white rounded-2xl px-4 py-3">
                <span class="text-sm font-bold" x-text="t('community.members.selected', { count: selected.length })"></span>
                <div class="flex-1"></div>
                <select @change="bulkTier($event.target.value); $event.target.value = ''"
                        class="h-9 px-3 rounded-pill bg-white/10 border border-white/25 text-sm font-semibold text-white">
                    <option value="">{{ __('webapp.community.members.bulk_set_tier') }}</option>
                    <option value="none">{{ __('webapp.community.members.tier_none') }}</option>
                    <template x-for="t in tiers" :key="t.id">
                        <option :value="t.id" x-text="t.name"></option>
                    </template>
                </select>
                <button type="button" @click="bulkRemove()" class="h-9 px-4 rounded-pill bg-white/10 border border-white/25 text-sm font-bold">
                    {{ __('webapp.community.members.bulk_remove') }}
                </button>
                <button type="button" @click="selected = []" class="h-9 px-3 text-sm font-semibold text-white/70">
                    {{ __('webapp.common.cancel') }}
                </button>
            </div>

            <template x-if="loading">
                <p class="mt-8 text-muted">{{ __('webapp.common.loading') }}</p>
            </template>

            {{-- ── Empty state ─────────────────────────────────────────── --}}
            <template x-if="!loading && members.length === 0">
                <div class="mt-8 bg-white border border-ink/[.08] rounded-2xl p-10 text-center shadow-card">
                    <p class="font-bold text-ink" x-text="hasFilters ? t('community.members.empty_filtered_title') : t('community.members.empty_title')"></p>
                    <p class="mt-2 text-sm text-muted" x-text="hasFilters ? t('community.members.empty_filtered_body') : t('community.members.empty_body')"></p>
                    <button type="button" @click="hasFilters ? clearFilters() : openInvite()"
                            class="mt-5 h-11 px-5 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn"
                            x-text="hasFilters ? t('community.members.clear_filters') : t('community.members.invite_first')"></button>
                </div>
            </template>

            {{-- ── Table (desktop) ─────────────────────────────────────── --}}
            <div x-show="!loading && members.length" x-cloak class="mt-5 hidden md:block bg-white border border-ink/[.08] rounded-2xl shadow-card overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ink/[.08] text-left text-muted">
                            <th class="w-10 py-3 pl-4">
                                <input type="checkbox" :checked="allSelected" @change="toggleAll()" aria-label="{{ __('webapp.community.members.select_all') }}">
                            </th>
                            <th class="py-3"><button type="button" @click="sortBy('name')" class="font-semibold hover:text-ink" x-html="head('name', t('community.members.col_member'))"></button></th>
                            <th class="py-3"><button type="button" @click="sortBy('tier')" class="font-semibold hover:text-ink" x-html="head('tier', t('community.members.col_tier'))"></button></th>
                            <th class="py-3 text-right"><button type="button" @click="sortBy('points')" class="font-semibold hover:text-ink" x-html="head('points', t('community.members.col_points'))"></button></th>
                            <th class="py-3 text-right"><button type="button" @click="sortBy('events_attended')" class="font-semibold hover:text-ink" x-html="head('events_attended', t('community.members.col_events'))"></button></th>
                            <th class="py-3"><button type="button" @click="sortBy('last_active_at')" class="font-semibold hover:text-ink" x-html="head('last_active_at', t('community.members.col_last_active'))"></button></th>
                            <th class="py-3"><button type="button" @click="sortBy('joined_at')" class="font-semibold hover:text-ink" x-html="head('joined_at', t('community.members.col_joined'))"></button></th>
                            <th class="py-3 pr-4"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="m in members" :key="m.id">
                            <tr class="border-b border-ink/[.05] last:border-0 hover:bg-cream-low/60 transition">
                                <td class="py-3 pl-4">
                                    <input type="checkbox" :value="m.id" x-model="selected" :aria-label="m.profile?.name">
                                </td>
                                <td class="py-3">
                                    <button type="button" @click="openDrawer(m)" class="flex items-center gap-2.5 text-left">
                                        <template x-if="m.profile?.avatar_url">
                                            <img :src="m.profile.avatar_url" alt="" class="w-8 h-8 rounded-full object-cover shrink-0">
                                        </template>
                                        <template x-if="!m.profile?.avatar_url">
                                            <div class="w-8 h-8 rounded-full bg-primary/50 flex items-center justify-center text-xs font-bold shrink-0" x-text="window.kbInitial(m.profile?.name)"></div>
                                        </template>
                                        <span class="min-w-0">
                                            <span class="block font-semibold text-ink truncate" x-text="m.profile?.name"></span>
                                            <span class="block text-[11px] text-muted truncate" x-text="m.profile?.handle ? '@' + m.profile.handle : m.profile?.email"></span>
                                        </span>
                                        <span x-show="m.can_manage" x-cloak class="ml-1 px-1.5 py-0.5 rounded-pill bg-ink text-primary text-[10px] font-bold">{{ __('webapp.community.members.manager') }}</span>
                                    </button>
                                </td>
                                <td class="py-3">
                                    <select :value="m.tier_id || ''" @change="setTier(m, $event.target.value)"
                                            class="h-8 px-2 rounded-pill border border-ink/[.12] bg-white text-[12px] font-semibold max-w-[150px]">
                                        <option value="">{{ __('webapp.community.members.tier_none') }}</option>
                                        <template x-for="t in tiers" :key="t.id">
                                            <option :value="t.id" x-text="t.name" :selected="m.tier_id === t.id"></option>
                                        </template>
                                    </select>
                                </td>
                                <td class="py-3 text-right font-semibold tabular-nums" x-text="m.points ?? 0"></td>
                                <td class="py-3 text-right font-semibold tabular-nums" x-text="m.events_attended ?? 0"></td>
                                <td class="py-3 text-muted" x-text="window.kbDateShort(m.last_active_at)"></td>
                                <td class="py-3 text-muted" x-text="window.kbDateShort(m.joined_at)"></td>
                                <td class="py-3 pr-4 text-right whitespace-nowrap">
                                    <button type="button" @click="toggleManager(m)" class="text-[12px] font-bold text-body hover:text-ink"
                                            x-text="m.can_manage ? t('community.members.revoke_manager') : t('community.members.make_manager')"></button>
                                    <template x-if="confirmRemove !== m.id">
                                        <button type="button" @click="confirmRemove = m.id" class="ml-3 text-[12px] font-bold text-bad-ink">{{ __('webapp.community.members.remove') }}</button>
                                    </template>
                                    <template x-if="confirmRemove === m.id">
                                        <span class="ml-3 inline-flex items-center gap-2">
                                            <button type="button" @click="removeMember(m)" class="text-[12px] font-bold text-bad-ink">{{ __('webapp.common.confirm') }}</button>
                                            <button type="button" @click="confirmRemove = null" class="text-[12px] font-semibold text-muted">{{ __('webapp.common.cancel') }}</button>
                                        </span>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- ── Cards (mobile) ──────────────────────────────────────── --}}
            <div x-show="!loading && members.length" x-cloak class="mt-5 md:hidden flex flex-col gap-2.5">
                <template x-for="m in members" :key="m.id">
                    <div class="bg-white border border-ink/[.08] rounded-2xl p-4 shadow-card">
                        <button type="button" @click="openDrawer(m)" class="flex items-center gap-3 w-full text-left">
                            <template x-if="m.profile?.avatar_url">
                                <img :src="m.profile.avatar_url" alt="" class="w-10 h-10 rounded-full object-cover shrink-0">
                            </template>
                            <template x-if="!m.profile?.avatar_url">
                                <div class="w-10 h-10 rounded-full bg-primary/50 flex items-center justify-center text-sm font-bold shrink-0" x-text="window.kbInitial(m.profile?.name)"></div>
                            </template>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-ink truncate" x-text="m.profile?.name"></p>
                                <p class="text-[11px] text-muted truncate" x-text="m.tier?.name || t('community.members.tier_none')"></p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="font-bold tabular-nums" x-text="m.points ?? 0"></p>
                                <p class="text-[10px] text-muted uppercase tracking-wide">{{ __('webapp.community.members.col_points') }}</p>
                            </div>
                        </button>
                    </div>
                </template>
            </div>

            {{-- ── Pagination ──────────────────────────────────────────── --}}
            <div x-show="!loading && pagination.total_pages > 1" x-cloak class="mt-5 flex items-center justify-center gap-3">
                <button type="button" @click="go(pagination.current_page - 1)" :disabled="pagination.current_page <= 1"
                        class="h-9 px-4 rounded-pill bg-white border border-ink/[.12] text-sm font-bold disabled:opacity-40">{{ __('webapp.common.previous') }}</button>
                <span class="text-sm text-muted" x-text="t('community.members.page_of', { current: pagination.current_page, total: pagination.total_pages })"></span>
                <button type="button" @click="go(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.total_pages"
                        class="h-9 px-4 rounded-pill bg-white border border-ink/[.12] text-sm font-bold disabled:opacity-40">{{ __('webapp.common.next') }}</button>
            </div>
        </div>
        </template>
    </div>
    </main>

    @include('webapp.partials.community-modals')
</div>
@endsection

@push('scripts')
<script>
    function communityMembersPage() {
        const t = window.t;

        return {
            // Alpine expressions evaluate against the component scope, so the
            // translator has to be a member — a closure alias is not visible
            // from markup.
            t: (key, params) => window.t(key, params),

            loading: true,
            error: '',
            members: [],
            tiers: [],
            pagination: { current_page: 1, total_pages: 1, total_count: 0, per_page: 25 },
            filters: { search: '', status: '', tier_id: '', can_manage: '', sort: 'joined_at', direction: 'asc' },
            selected: [],
            confirmRemove: null,
            searchTimer: null,

            // Drawer
            drawer: null,
            drawerActivity: [],
            drawerLoading: false,

            // Modals
            addOpen: false,
            addIdentifier: '',
            addTierId: '',
            addError: '',
            addOfferInvite: false,
            addBusy: false,

            inviteOpen: false,
            inviteEmails: '',
            inviteTierId: '',
            inviteError: '',
            inviteResult: null,
            inviteBusy: false,

            get communityId() { return this.activeCommunity?.id || null; },
            get allSelected() { return this.members.length > 0 && this.selected.length === this.members.length; },
            get hasFilters() {
                return !!(this.filters.search || this.filters.status || this.filters.tier_id || this.filters.can_manage);
            },

            async init() {
                await this.loadShell();
                await this.loadManagedCommunities();
                if (!this.communityId) { this.loading = false; return; }
                await Promise.all([this.loadTiers(), this.load(1)]);
                this.loadCommunityPending();
            },

            /** Column header with a sort arrow when it is the active key. */
            head(key, label) {
                if (this.filters.sort !== key) return label;
                return label + (this.filters.direction === 'asc' ? ' &uarr;' : ' &darr;');
            },

            /** Debounced so typing does not fire a request per keystroke. */
            onSearch() {
                clearTimeout(this.searchTimer);
                this.searchTimer = setTimeout(() => this.load(1), 300);
            },

            reload() { this.load(1); },
            go(page) { if (page >= 1 && page <= this.pagination.total_pages) this.load(page); },

            clearFilters() {
                this.filters = { search: '', status: '', tier_id: '', can_manage: '', sort: 'joined_at', direction: 'asc' };
                this.load(1);
            },

            toggleManagersOnly() {
                this.filters.can_manage = this.filters.can_manage === '1' ? '' : '1';
                this.load(1);
            },

            query(page) {
                const p = new URLSearchParams();
                Object.entries(this.filters).forEach(([k, v]) => { if (v !== '' && v !== null && v !== undefined) p.set(k, v); });
                p.set('page', page);
                p.set('limit', this.pagination.per_page);
                return p.toString();
            },

            async load(page = 1) {
                if (!this.communityId) return;
                this.loading = true;
                this.error = '';
                const res = await window.kb.api('/communities/' + this.communityId + '/members?' + this.query(page));
                this.loading = false;
                if (!res.ok) {
                    this.members = [];
                    this.error = window.kb.errorText(res, t('community.members.load_error'));
                    return;
                }
                // The roster nests rows under data.members, next to data.pagination.
                this.members = window.kb.rows(res, 'members');
                this.pagination = res.json?.data?.pagination || this.pagination;
                this.selected = [];
                this.confirmRemove = null;
            },

            async loadTiers() {
                const res = await window.kb.api('/communities/' + this.communityId + '/tiers');
                this.tiers = res.ok ? window.kb.rows(res) : [];
            },

            sortBy(key) {
                if (this.filters.sort === key) {
                    this.filters.direction = this.filters.direction === 'asc' ? 'desc' : 'asc';
                } else {
                    this.filters.sort = key;
                    this.filters.direction = ['points', 'events_attended', 'last_active_at', 'tier'].includes(key) ? 'desc' : 'asc';
                }
                this.load(1);
            },

            toggleAll() { this.selected = this.allSelected ? [] : this.members.map(m => m.id); },

            async patchMember(member, body) {
                this.error = '';
                const res = await window.kb.api('/communities/' + this.communityId + '/members/' + member.id, {
                    method: 'PATCH', body,
                });
                if (res.ok) { await this.load(this.pagination.current_page); return true; }
                this.error = window.kb.errorText(res, t('community.members.update_error'));
                return false;
            },

            setTier(member, tierId) { return this.patchMember(member, { tier_id: tierId || null }); },
            toggleManager(member) { return this.patchMember(member, { can_manage: !member.can_manage }); },

            async removeMember(member) {
                this.confirmRemove = null;
                this.error = '';
                const res = await window.kb.api('/communities/' + this.communityId + '/members/' + member.id, { method: 'DELETE' });
                if (res.ok) await this.load(this.pagination.current_page);
                else this.error = window.kb.errorText(res, t('community.members.update_error'));
            },

            async bulk(body) {
                if (!this.selected.length) return;
                this.error = '';
                const res = await window.kb.api('/communities/' + this.communityId + '/members', {
                    method: 'PATCH', body: { member_ids: this.selected, ...body },
                });
                if (res.ok) await this.load(this.pagination.current_page);
                else this.error = window.kb.errorText(res, t('community.members.update_error'));
            },

            bulkTier(tierId) { if (tierId) this.bulk({ tier_id: tierId === 'none' ? null : tierId }); },
            bulkRemove() { this.bulk({ status: 'removed' }); },

            /* ── Drawer ──────────────────────────────────────────────── */

            async openDrawer(member) {
                this.drawer = member;
                this.drawerActivity = [];
                this.drawerLoading = true;
                const res = await window.kb.api('/communities/' + this.communityId + '/members/' + member.id);
                this.drawerLoading = false;
                if (res.ok) {
                    this.drawer = res.json?.data?.member || member;
                    this.drawerActivity = res.json?.data?.activity || [];
                }
            },
            closeDrawer() { this.drawer = null; this.drawerActivity = []; },

            /* ── Add member ──────────────────────────────────────────── */

            openAdd() {
                this.addOpen = true;
                this.addIdentifier = '';
                this.addTierId = '';
                this.addError = '';
                this.addOfferInvite = false;
            },

            async submitAdd() {
                this.addError = '';
                this.addOfferInvite = false;
                this.addBusy = true;
                const res = await window.kb.api('/communities/' + this.communityId + '/members', {
                    method: 'POST',
                    body: { identifier: this.addIdentifier.trim(), tier_id: this.addTierId || null },
                });
                this.addBusy = false;

                if (res.ok) { this.addOpen = false; await this.load(1); return; }

                // No Kolabing account for that address — do not dead-end. Offer
                // the email invitation instead; the invite waits until they sign up.
                if (res.status === 404 && res.json?.error === 'profile_not_found') {
                    this.addError = t('community.members.no_account');
                    this.addOfferInvite = this.addIdentifier.includes('@');
                    return;
                }
                this.addError = window.kb.errorText(res, t('community.members.add_error'));
            },

            /** Hand the typed address straight to the invite modal. */
            escalateToInvite() {
                const email = this.addIdentifier.trim();
                const tier = this.addTierId;
                this.addOpen = false;
                this.openInvite();
                this.inviteEmails = email;
                this.inviteTierId = tier;
            },

            /* ── Invite by email ─────────────────────────────────────── */

            openInvite() {
                this.inviteOpen = true;
                this.inviteEmails = '';
                this.inviteTierId = '';
                this.inviteError = '';
                this.inviteResult = null;
            },

            get parsedInviteEmails() {
                return this.inviteEmails
                    .split(/[\s,;]+/)
                    .map(e => e.trim())
                    .filter(Boolean);
            },

            async submitInvite() {
                const emails = this.parsedInviteEmails;
                this.inviteError = '';
                this.inviteResult = null;

                if (!emails.length) { this.inviteError = t('community.members.invite_empty'); return; }
                if (emails.length > 50) { this.inviteError = t('community.members.invite_too_many'); return; }

                this.inviteBusy = true;
                const res = await window.kb.api('/communities/' + this.communityId + '/invitations', {
                    method: 'POST',
                    body: { emails, tier_id: this.inviteTierId || null },
                });
                this.inviteBusy = false;

                if (!res.ok) { this.inviteError = window.kb.errorText(res, t('community.members.invite_error')); return; }

                this.inviteResult = res.json?.data || null;
                this.inviteEmails = '';
                this.loadCommunityPending();
            },
        };
    }
</script>
@endpush
