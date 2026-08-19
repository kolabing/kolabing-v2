@extends('webapp.layout')
@section('title', __('webapp.community.requests.title'))

@section('body')
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), communityRequestsPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'community'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[880px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        @include('webapp.partials.community-nav', ['communityActive' => 'requests'])

        <template x-if="canManageCommunity">
        <div>
            <div class="mt-6 flex p-1 bg-white border border-ink/[.12] rounded-pill shadow-card">
                <button type="button" @click="tab = 'requests'"
                        class="flex-1 h-[34px] rounded-pill text-[12.5px] font-bold tracking-[.4px] transition"
                        :class="tab === 'requests' ? 'bg-ink text-white' : 'text-muted'">
                    {{ __('webapp.community.requests.tab_requests') }}
                </button>
                <button type="button" @click="tab = 'invitations'"
                        class="flex-1 h-[34px] rounded-pill text-[12.5px] font-bold tracking-[.4px] transition"
                        :class="tab === 'invitations' ? 'bg-ink text-white' : 'text-muted'">
                    {{ __('webapp.community.requests.tab_invitations') }}
                </button>
            </div>

            <template x-if="error">
                <div class="mt-5 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3" x-text="error"></div>
            </template>

            <template x-if="loading">
                <p class="mt-8 text-muted">{{ __('webapp.common.loading') }}</p>
            </template>

            {{-- ── Join requests ───────────────────────────────────────── --}}
            <template x-if="!loading && tab === 'requests'">
                <div class="mt-5 flex flex-col gap-2.5">
                    <template x-if="requests.length === 0">
                        <div class="bg-white border border-ink/[.08] rounded-2xl p-8 text-center shadow-card">
                            <p class="text-sm text-muted">{{ __('webapp.community.requests.requests_empty') }}</p>
                        </div>
                    </template>

                    <template x-for="rq in requests" :key="rq.id">
                        <div class="bg-white border border-ink/[.08] rounded-2xl p-4 shadow-card flex items-center gap-3.5">
                            <template x-if="rq.profile?.avatar_url">
                                <img :src="rq.profile.avatar_url" alt="" class="w-10 h-10 rounded-full object-cover shrink-0">
                            </template>
                            <template x-if="!rq.profile?.avatar_url">
                                <div class="w-10 h-10 rounded-full bg-primary/50 flex items-center justify-center text-sm font-bold shrink-0" x-text="window.kbInitial(rq.profile?.name)"></div>
                            </template>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-ink truncate" x-text="rq.profile?.name || rq.profile?.email"></p>
                                <p class="text-[11px] text-muted" x-text="window.kbDateShort(rq.created_at)"></p>
                            </div>
                            <button type="button" @click="decide(rq, 'decline')" :disabled="busy"
                                    class="h-9 px-4 rounded-pill bg-white border border-ink/[.12] text-[12.5px] font-bold disabled:opacity-50">
                                {{ __('webapp.community.requests.decline') }}
                            </button>
                            <button type="button" @click="decide(rq, 'approve')" :disabled="busy"
                                    class="h-9 px-4 rounded-pill bg-primary text-ink text-[12.5px] font-bold shadow-btn disabled:opacity-50">
                                {{ __('webapp.community.requests.approve') }}
                            </button>
                        </div>
                    </template>
                </div>
            </template>

            {{-- ── Invitations ─────────────────────────────────────────── --}}
            <template x-if="!loading && tab === 'invitations'">
                <div class="mt-5">
                    <button type="button" @click="toggleInvitationScope()" class="text-[12.5px] font-bold text-body hover:text-ink"
                            x-text="invitationScope === 'all' ? t('community.requests.show_pending') : t('community.requests.show_all')"></button>

                    <div class="mt-3 flex flex-col gap-2.5">
                        <template x-if="invitations.length === 0">
                            <div class="bg-white border border-ink/[.08] rounded-2xl p-8 text-center shadow-card">
                                <p class="text-sm text-muted">{{ __('webapp.community.requests.invitations_empty') }}</p>
                            </div>
                        </template>

                        <template x-for="inv in invitations" :key="inv.id">
                            <div class="bg-white border border-ink/[.08] rounded-2xl p-4 shadow-card flex items-center gap-3.5">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-ink truncate" x-text="inv.email"></p>
                                    <p class="text-[11px] text-muted">
                                        <span x-text="inv.tier?.name || t('community.members.tier_default')"></span>
                                        <span aria-hidden="true">·</span>
                                        <span x-text="t('community.requests.expires', { date: window.kbDateShort(inv.expires_at) })"></span>
                                    </p>
                                </div>
                                <span class="shrink-0 px-2.5 py-1 rounded-pill text-[11px] font-bold"
                                      :class="inv.is_claimable ? 'bg-cream-low text-ink' : 'bg-ink/10 text-muted'"
                                      x-text="window.kbHumanize(inv.status)"></span>
                                <button type="button" @click="resend(inv)" :disabled="busy"
                                        class="h-9 px-3 rounded-pill bg-white border border-ink/[.12] text-[12.5px] font-bold disabled:opacity-50">
                                    {{ __('webapp.community.requests.resend') }}
                                </button>
                                <template x-if="inv.status === 'pending'">
                                    <button type="button" @click="revoke(inv)" :disabled="busy"
                                            class="h-9 px-3 rounded-pill text-[12.5px] font-bold text-bad-ink disabled:opacity-50">
                                        {{ __('webapp.community.requests.revoke') }}
                                    </button>
                                </template>
                            </div>
                        </template>
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
    function communityRequestsPage() {
        return {
            t: (key, params) => window.t(key, params),
            tab: 'requests',
            loading: true,
            busy: false,
            error: '',
            requests: [],
            invitations: [],
            invitationScope: 'pending',

            get communityId() { return this.activeCommunity?.id || null; },

            async init() {
                await this.loadShell();
                await this.loadManagedCommunities();
                if (!this.communityId) { this.loading = false; return; }
                await this.load();
            },

            async load() {
                this.loading = true;
                const [rq, inv] = await Promise.all([
                    window.kb.api('/communities/' + this.communityId + '/join-requests'),
                    window.kb.api('/communities/' + this.communityId + '/invitations'
                        + (this.invitationScope === 'all' ? '?status=all' : '')),
                ]);
                this.loading = false;
                this.requests = rq.ok ? window.kb.rows(rq) : [];
                this.invitations = inv.ok ? window.kb.rows(inv) : [];
                this.communityPending = this.requests.length
                    + this.invitations.filter(i => i.is_claimable).length;
            },

            toggleInvitationScope() {
                this.invitationScope = this.invitationScope === 'all' ? 'pending' : 'all';
                this.load();
            },

            async decide(request, action) {
                this.busy = true;
                this.error = '';
                const res = await window.kb.api('/join-requests/' + request.id + '/' + action, { method: 'POST' });
                this.busy = false;
                if (res.ok) await this.load();
                else this.error = window.kb.errorText(res, window.t('community.requests.action_error'));
            },

            async resend(invitation) {
                this.busy = true;
                this.error = '';
                const res = await window.kb.api('/invitations/' + invitation.id + '/resend', { method: 'POST' });
                this.busy = false;
                if (res.ok) await this.load();
                else this.error = window.kb.errorText(res, window.t('community.requests.action_error'));
            },

            async revoke(invitation) {
                this.busy = true;
                this.error = '';
                const res = await window.kb.api('/invitations/' + invitation.id, { method: 'DELETE' });
                this.busy = false;
                if (res.ok) await this.load();
                else this.error = window.kb.errorText(res, window.t('community.requests.action_error'));
            },
        };
    }
</script>
@endpush
