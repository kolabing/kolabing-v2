@extends('webapp.layout')
@section('title', __('webapp.community.settings.title'))

@section('body')
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), communitySettingsPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'community'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[720px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        @include('webapp.partials.community-nav', ['communityActive' => 'settings'])

        <template x-if="canManageCommunity">
        <div>
            <template x-if="error">
                <div class="mt-5 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
            </template>
            <template x-if="saved">
                <div class="mt-5 rounded-2xl bg-good-surface text-good-ink text-sm px-4 py-3">{{ __('webapp.community.settings.saved') }}</div>
            </template>

            <template x-if="loading">
                <p class="mt-8 text-muted">{{ __('webapp.common.loading') }}</p>
            </template>

            <template x-if="!loading && form">
            <div>
                {{-- ── Identity ────────────────────────────────────────── --}}
                <div class="mt-6 bg-white border border-ink/[.08] rounded-2xl p-5 shadow-card">
                    <div class="flex items-center gap-4">
                        <template x-if="form.avatar_url">
                            <img :src="form.avatar_url" alt="" class="w-16 h-16 rounded-full object-cover shrink-0">
                        </template>
                        <template x-if="!form.avatar_url">
                            <div class="w-16 h-16 rounded-full bg-primary/50 flex items-center justify-center text-xl font-bold shrink-0" x-text="window.kbInitial(form.name)"></div>
                        </template>
                        <div>
                            <label class="block text-[12px] font-bold text-body">{{ __('webapp.community.settings.avatar') }}</label>
                            <input type="file" accept="image/*" @change="uploadAvatar($event)" class="mt-1.5 text-sm">
                            <p x-show="uploading" x-cloak class="mt-1 text-[11px] text-muted">{{ __('webapp.common.uploading') }}</p>
                        </div>
                    </div>

                    <label class="block mt-5 text-[12px] font-bold text-body">{{ __('webapp.community.settings.name') }}</label>
                    <input type="text" x-model="form.name" maxlength="100" class="mt-1.5 w-full h-11 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">

                    <label class="block mt-4 text-[12px] font-bold text-body">{{ __('webapp.community.settings.type') }}</label>
                    <select x-model="form.type" class="mt-1.5 w-full h-11 px-3 rounded-2xl bg-white border border-ink/[.12] text-sm font-semibold">
                        <template x-for="opt in communityTypes" :key="opt.slug">
                            <option :value="opt.slug" x-text="opt.name"></option>
                        </template>
                    </select>

                    <label class="block mt-4 text-[12px] font-bold text-body">{{ __('webapp.community.settings.description') }}</label>
                    <textarea x-model="form.description" rows="4" maxlength="2000" class="mt-1.5 w-full px-4 py-3 rounded-2xl bg-white border border-ink/[.12] text-sm"></textarea>

                    <label class="block mt-4 text-[12px] font-bold text-body">{{ __('webapp.community.settings.join_policy') }}</label>
                    <select x-model="form.join_policy" class="mt-1.5 w-full h-11 px-3 rounded-2xl bg-white border border-ink/[.12] text-sm font-semibold">
                        <option value="open">{{ __('webapp.community.settings.policy_open') }}</option>
                        <option value="invite_only">{{ __('webapp.community.settings.policy_invite') }}</option>
                    </select>
                    <p class="mt-1.5 text-[11px] text-muted">{{ __('webapp.community.settings.policy_help') }}</p>

                    <button type="button" @click="save()" :disabled="busy || !form.name.trim()"
                            class="mt-6 h-11 px-6 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn disabled:opacity-50"
                            x-text="busy ? t('common.saving') : t('common.save')"></button>
                </div>

                {{-- ── Invite links ────────────────────────────────────── --}}
                <div class="mt-3 bg-white border border-ink/[.08] rounded-2xl p-5 shadow-card">
                    <p class="text-[11px] font-semibold uppercase tracking-[.14em] text-muted">{{ __('webapp.community.settings.invite_link') }}</p>
                    <div class="mt-2 flex gap-2">
                        <input type="text" readonly :value="activeCommunity?.invite_url || ''"
                               class="flex-1 h-11 px-4 rounded-2xl bg-cream-low border border-ink/[.12] text-sm">
                        <button type="button" @click="copy(activeCommunity?.invite_url, 'link')"
                                class="h-11 px-4 rounded-pill bg-white border border-ink/[.12] text-sm font-bold"
                                x-text="copied === 'link' ? t('community.settings.copied') : t('community.settings.copy')"></button>
                    </div>

                    <template x-if="form.join_policy === 'invite_only'">
                        <div class="mt-5">
                            <p class="text-[11px] font-semibold uppercase tracking-[.14em] text-muted">{{ __('webapp.community.settings.token_link') }}</p>
                            <div class="mt-2 flex gap-2">
                                <input type="text" readonly :value="tokenUrl || ''"
                                       class="flex-1 h-11 px-4 rounded-2xl bg-cream-low border border-ink/[.12] text-sm">
                                <button type="button" @click="copy(tokenUrl, 'token')"
                                        class="h-11 px-4 rounded-pill bg-white border border-ink/[.12] text-sm font-bold"
                                        x-text="copied === 'token' ? t('community.settings.copied') : t('community.settings.copy')"></button>
                            </div>
                            <p class="mt-1.5 text-[11px] text-muted">{{ __('webapp.community.settings.token_help') }}</p>
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
    function communitySettingsPage() {
        return {
            t: (key, params) => window.t(key, params),
            loading: true,
            busy: false,
            uploading: false,
            saved: false,
            error: '',
            form: null,
            communityTypes: [],
            tokenUrl: '',
            copied: '',

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
                const [community, types] = await Promise.all([
                    window.kb.api('/communities/' + this.communityId),
                    // The 17-slug community-type vocabulary, straight from the API —
                    // never a hardcoded list.
                    window.kb.api('/lookup/community-types'),
                ]);
                this.loading = false;

                const data = community.ok ? (community.json?.data || {}) : {};
                this.form = {
                    name: data.name || '',
                    type: data.type || '',
                    description: data.description || '',
                    avatar_url: data.avatar_url || '',
                    join_policy: data.join_policy || 'open',
                };

                this.communityTypes = types.ok
                    ? window.kb.rows(types).map(r => ({ slug: r.slug ?? r.value ?? r.id, name: r.name ?? r.label ?? r.slug }))
                    : [];

                if (this.form.join_policy === 'invite_only') await this.loadTokenUrl();
            },

            async loadTokenUrl() {
                const res = await window.kb.api('/communities/' + this.communityId + '/invite');
                if (res.ok) this.tokenUrl = res.json?.data?.invite_url_with_token || res.json?.data?.token_url || '';
            },

            async uploadAvatar(event) {
                const file = event.target.files?.[0];
                if (!file) return;
                this.uploading = true;
                const res = await window.kb.uploadFile(file, 'communities');
                this.uploading = false;
                if (res.ok) this.form.avatar_url = res.json?.data?.url || this.form.avatar_url;
                else this.error = window.kb.errorText(res, window.t('community.settings.save_error'));
            },

            async save() {
                this.busy = true;
                this.error = '';
                this.saved = false;

                const res = await window.kb.api('/communities/' + this.communityId, {
                    method: 'PATCH',
                    body: {
                        name: this.form.name.trim(),
                        type: this.form.type || undefined,
                        description: this.form.description,
                        avatar_url: this.form.avatar_url || null,
                        join_policy: this.form.join_policy,
                    },
                });

                this.busy = false;

                if (!res.ok) {
                    this.error = window.kb.errorText(res, window.t('community.settings.save_error'));
                    return;
                }

                this.saved = true;
                setTimeout(() => { this.saved = false; }, 2500);
                await this.loadManagedCommunities();
                if (this.form.join_policy === 'invite_only') await this.loadTokenUrl();
            },

            async copy(value, which) {
                if (!value) return;
                try {
                    await navigator.clipboard.writeText(value);
                    this.copied = which;
                    setTimeout(() => { this.copied = ''; }, 2500);
                } catch (e) {
                    // Clipboard is permission-gated; the field is selectable as a fallback.
                }
            },
        };
    }
</script>
@endpush
