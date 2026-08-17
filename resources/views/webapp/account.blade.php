@extends('webapp.layout')
@section('title', __('webapp.account.title'))

@section('body')
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), accountPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'account'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[640px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        <h1 class="font-anton text-[28px] tracking-[1px] text-ink">{{ __('webapp.account.title') }}</h1>

        <template x-if="error">
            <div class="mt-5 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
        </template>
        <template x-if="saved">
            <div class="mt-5 rounded-2xl bg-ok-surface text-ok-ink text-sm px-4 py-3">{{ __('webapp.common.saved') }}</div>
        </template>
        <template x-if="loading">
            <p class="mt-8 text-muted">{{ __('webapp.common.loading') }}</p>
        </template>

        {{-- ── Identity card ───────────────────────────────────────────── --}}
        <template x-if="!loading">
            <div class="flex items-center gap-[18px] bg-white border border-ink/[.08] rounded-[20px] p-6 mt-5 shadow-card">
                <div class="w-[72px] h-[72px] rounded-full bg-primary/50 flex items-center justify-center overflow-hidden shrink-0 text-[28px] font-semibold text-ink">
                    <template x-if="avatarUrl"><img :src="avatarUrl" alt="" class="w-full h-full object-cover"></template>
                    <template x-if="!avatarUrl"><span x-text="initial"></span></template>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xl font-bold text-ink truncate" x-text="displayName"></p>
                    <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                        <span class="px-2.5 py-1 rounded-pill bg-cream-input text-[11px] font-semibold text-body" x-text="roleLabel"></span>
                        <span x-show="typeLabel" x-cloak class="px-2.5 py-1 rounded-pill bg-peach text-peach-ink text-[11px] font-semibold" x-text="typeLabel"></span>
                        <span x-show="cityName" x-cloak class="text-[12.5px] text-muted" x-text="cityName"></span>
                    </div>
                </div>
                <button type="button" @click="toggleEdit()"
                        class="h-[38px] px-[18px] rounded-pill bg-white border border-line text-ink text-[13px] font-bold hover:border-ink transition shrink-0"
                        x-text="editing ? t('common.cancel') : t('account.edit_profile')"></button>
            </div>
        </template>

        {{-- ── About card (read mode) ──────────────────────────────────── --}}
        <template x-if="!loading && !editing">
            <div class="bg-white border border-ink/[.08] rounded-[20px] px-6 py-5 mt-3.5">
                <p class="text-[11px] font-semibold tracking-[1px] uppercase text-muted">{{ __('webapp.account.about') }}</p>
                <p class="text-sm text-body leading-relaxed mt-2 whitespace-pre-line" x-text="form.about || t('account.no_about')"></p>
                <div class="flex gap-5 mt-3.5 text-[13px] text-body flex-wrap">
                    <template x-for="s in profileStats" :key="s">
                        <span class="font-semibold" x-text="s"></span>
                    </template>
                </div>
            </div>
        </template>

        {{-- ── Edit form ───────────────────────────────────────────────── --}}
        <template x-if="!loading && editing">
            <form @submit.prevent="save()" class="bg-white border border-ink/[.08] rounded-[20px] p-6 mt-3.5 flex flex-col gap-[13px]">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-full bg-primary/50 flex items-center justify-center overflow-hidden shrink-0 text-2xl font-semibold text-ink">
                        <template x-if="avatarUrl"><img :src="avatarUrl" alt="" class="w-full h-full object-cover"></template>
                        <template x-if="!avatarUrl"><span x-text="initial"></span></template>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-body block">{{ __('webapp.account.photo') }}</label>
                        <input type="file" accept="image/*" @change="uploadPhoto($event)" class="mt-1 block text-[13px] text-body">
                        <p class="text-[11px] text-muted mt-0.5" x-show="uploadingPhoto" x-cloak>{{ __('webapp.common.uploading') }}</p>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-semibold text-body" x-text="isBusiness ? t('account.business_name') : t('account.community_name')"></label>
                    <input x-model="form.name" maxlength="255" class="h-[50px] rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-semibold text-body">{{ __('webapp.account.about') }}</label>
                    <textarea x-model="form.about" maxlength="2000" rows="4" class="rounded-2xl border border-transparent bg-cream-input px-4 py-3.5 text-sm text-ink resize-y"></textarea>
                </div>

                <template x-if="isBusiness">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-body">{{ __('webapp.account.categories') }} <span class="font-normal text-muted">{{ __('webapp.account.categories_hint') }}</span></label>
                        <div class="flex flex-wrap gap-2 mt-1">
                            <template x-for="o in businessTypes" :key="o.value">
                                <button type="button" @click="toggleCategory(o.value)"
                                        class="px-4 py-2 rounded-pill text-[13px] font-semibold border transition"
                                        :class="form.categories.includes(o.value) ? 'bg-primary-tint border-primary text-ink' : 'bg-white border-ink/[.12] text-ink'"
                                        x-text="o.label"></button>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-if="!isBusiness">
                    <div class="flex gap-3">
                        <div class="flex-1 flex flex-col gap-1.5">
                            <label class="text-xs font-semibold text-body">{{ __('webapp.account.community_type') }}</label>
                            <select x-model="form.community_type" class="h-[50px] w-full rounded-2xl border border-transparent bg-cream-input px-3 text-sm text-ink">
                                <option value="">{{ __('webapp.common.select') }}</option>
                                <template x-for="o in communityTypes" :key="o.value"><option :value="o.value" x-text="o.label"></option></template>
                            </select>
                        </div>
                        <div class="w-[140px] flex flex-col gap-1.5">
                            <label class="text-xs font-semibold text-body">{{ __('webapp.account.community_size') }}</label>
                            <input x-model.number="form.community_size" type="number" min="1" class="h-[50px] w-full rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
                        </div>
                    </div>
                </template>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-semibold text-body">{{ __('webapp.account.city') }}</label>
                    <select x-model="form.city_id" class="h-[50px] rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
                        <option value="">{{ __('webapp.common.select') }}</option>
                        <template x-for="c in cities" :key="c.id"><option :value="c.id" x-text="c.name"></option></template>
                    </select>
                </div>

                <div class="flex gap-3">
                    <div class="flex-1 flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-body">{{ __('webapp.account.instagram') }}</label>
                        <input x-model="form.instagram" maxlength="255" placeholder="@handle" class="h-[50px] w-full rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
                    </div>
                    <div class="flex-1 flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-body">{{ __('webapp.account.website') }}</label>
                        <input x-model="form.website" type="url" placeholder="https://" class="h-[50px] w-full rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
                    </div>
                </div>
                <template x-if="!isBusiness">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-body">{{ __('webapp.account.tiktok') }}</label>
                        <input x-model="form.tiktok" maxlength="255" placeholder="@handle" class="h-[50px] rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
                    </div>
                </template>

                <button type="submit" :disabled="busy"
                        class="mt-2 h-[52px] rounded-pill bg-primary text-ink text-[15px] font-bold shadow-btn hover:bg-primary-dark hover:-translate-y-px transition disabled:opacity-50">
                    <span x-text="busy ? t('form.saving') : t('account.submit')">{{ __('webapp.account.submit') }}</span>
                </button>
            </form>
        </template>

        {{-- ── Settings rows ───────────────────────────────────────────── --}}
        <template x-if="!loading">
            <div class="bg-white border border-ink/[.08] rounded-[20px] mt-3.5 overflow-hidden">
                <button type="button" @click="toggleEdit()"
                        class="w-full flex items-center justify-between px-6 py-[15px] border-b border-ink/[.06] hover:bg-cream-low transition text-sm font-medium text-ink text-left">
                    {{ __('webapp.account.edit_profile') }}
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </button>

                <button type="button" @click="toggleNotifPrefs()"
                        class="w-full flex items-center justify-between px-6 py-[15px] border-b border-ink/[.06] hover:bg-cream-low transition text-sm font-medium text-ink text-left">
                    {{ __('webapp.account.notification_settings') }}
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :class="showNotifPrefs ? 'rotate-90' : ''" class="transition"><path d="m9 18 6-6-6-6"/></svg>
                </button>
                <div x-show="showNotifPrefs" x-cloak class="px-6 py-4 border-b border-ink/[.06] bg-cream-low/60 flex flex-col gap-3">
                    <template x-for="p in notifRows" :key="p.key">
                        <label class="flex items-center justify-between gap-3 cursor-pointer">
                            <span class="text-[13px] text-body" x-text="p.label"></span>
                            <input type="checkbox" :checked="prefs[p.key]" @change="setPref(p.key, $event.target.checked)"
                                   class="w-5 h-5 rounded-md border-ink/20 text-ink focus:ring-0">
                        </label>
                    </template>
                    <p class="text-[11px] text-muted" x-show="prefsSaving" x-cloak>{{ __('webapp.common.saving') }}</p>
                </div>

                <button type="button" @click="showLanguages = !showLanguages"
                        class="w-full flex items-center justify-between px-6 py-[15px] border-b border-ink/[.06] hover:bg-cream-low transition text-sm font-medium text-ink text-left">
                    {{ __('webapp.account.language') }}
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :class="showLanguages ? 'rotate-90' : ''" class="transition"><path d="m9 18 6-6-6-6"/></svg>
                </button>
                <div x-show="showLanguages" x-cloak class="px-6 py-4 border-b border-ink/[.06] bg-cream-low/60 flex gap-2">
                    @foreach ($localePaths as $l => $href)
                        <a href="{{ $href }}"
                           class="px-4 py-2 rounded-pill text-[13px] font-bold border transition {{ $l === $loc ? 'bg-primary border-primary text-ink' : 'bg-white border-ink/[.12] text-body' }}">{{ strtoupper($l) }}</a>
                    @endforeach
                </div>

                <a href="https://kolabing.com/contact" target="_blank" rel="noopener"
                   class="flex items-center justify-between px-6 py-[15px] border-b border-ink/[.06] hover:bg-cream-low transition text-sm font-medium text-ink">
                    {{ __('webapp.account.support') }}
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </a>

                <button type="button" @click="window.kb.logout()"
                        class="w-full text-left px-6 py-[15px] text-sm font-semibold text-danger hover:bg-bad-surface transition">{{ __('webapp.nav.logout') }}</button>
            </div>
        </template>
    </div>
    </main>
</div>

@push('scripts')
<script>
    function accountPage() {
        return {
            loading: true, busy: false, uploadingPhoto: false, error: '', saved: false,
            editing: false, showNotifPrefs: false, showLanguages: false,
            businessTypes: [], communityTypes: [], cities: [],
            prefs: {}, prefsSaving: false,
            form: { name: '', about: '', categories: [], community_type: '', community_size: null, city_id: '', instagram: '', tiktok: '', website: '' },

            get typeLabel() {
                const p = this.profile;
                return p.type_label || window.kbHumanize(p.business_type || p.community_type);
            },
            get cityName() { return this.profile.city?.name || ''; },
            get profileStats() {
                const p = this.profile, out = [];
                if (!this.isBusiness && p.community_size) out.push(t('account.stat_members', { count: p.community_size }));
                if (p.instagram) out.push('@' + String(p.instagram).replace(/^@/, ''));
                if (p.website) out.push(String(p.website).replace(/^https?:\/\//, ''));
                return out;
            },
            get notifRows() {
                return [
                    { key: 'new_application_alerts', label: t('account.pref_applications') },
                    { key: 'collaboration_updates', label: t('account.pref_collaborations') },
                    { key: 'message_notifications', label: t('account.pref_messages') },
                    { key: 'email_notifications', label: t('account.pref_email') },
                    { key: 'marketing_tips', label: t('account.pref_marketing') },
                ];
            },

            async init() {
                if (!window.kb.requireAuth()) return;
                if (!await this.loadShell()) return;
                await this.loadLookups();
                this.prefill();
                this.loading = false;
                if (new URLSearchParams(location.search).get('edit') === '1') this.editing = true;
            },
            async loadLookups() {
                const [bt, ct, ci] = await Promise.all([
                    window.kb.api('/lookup/business-types', { auth: false }),
                    window.kb.api('/lookup/community-types', { auth: false }),
                    window.kb.api('/cities', { auth: false }),
                ]);
                if (bt.ok) this.businessTypes = window.kb.rows(bt);
                if (ct.ok) this.communityTypes = window.kb.rows(ct);
                if (ci.ok) this.cities = window.kb.rows(ci).filter(c => c.id && c.id !== 'other');
            },
            prefill() {
                const p = this.profile;
                this.form.name = p.name || '';
                this.form.about = p.about || '';
                this.form.city_id = p.city?.id || '';
                this.form.instagram = p.instagram || '';
                this.form.website = p.website || '';
                if (this.isBusiness) {
                    this.form.categories = Array.isArray(p.categories) ? [...p.categories] : [];
                } else {
                    this.form.community_type = p.community_type || '';
                    this.form.community_size = p.community_size ?? null;
                    this.form.tiktok = p.tiktok || '';
                }
            },
            toggleEdit() {
                this.editing = !this.editing;
                this.error = ''; this.saved = false;
                if (this.editing) { this.prefill(); window.scrollTo({ top: 0, behavior: 'smooth' }); }
            },
            toggleCategory(value) {
                const arr = this.form.categories;
                const i = arr.indexOf(value);
                if (i !== -1) arr.splice(i, 1);
                else if (arr.length < 3) arr.push(value);
            },
            payload() {
                const f = this.form;
                const base = { name: f.name, about: f.about, instagram: f.instagram, website: f.website };
                if (f.city_id) base.city_id = f.city_id;
                if (this.isBusiness) return { ...base, categories: f.categories };
                return { ...base, community_type: f.community_type, community_size: f.community_size, tiktok: f.tiktok };
            },
            async save() {
                this.error = ''; this.saved = false; this.busy = true;
                const res = await window.kb.api('/me/profile', { method: 'PUT', body: this.payload() });
                this.busy = false;
                if (res.ok) {
                    this.me = res.json?.data || this.me;
                    this.saved = true; this.editing = false;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return;
                }
                this.error = window.kb.errorText(res, t('account.save_error'));
            },
            async uploadPhoto(e) {
                const file = e.target.files?.[0];
                if (!file) return;
                this.error = ''; this.saved = false; this.uploadingPhoto = true;
                // Multipart with a method-spoof — the profile endpoint is a PUT.
                // kb.upload() carries the same 401 refresh-and-retry as kb.api(), so an
                // expired token does not silently cost the user their upload.
                const fd = new FormData();
                fd.append('_method', 'PUT');
                fd.append('profile_photo', file);
                const res = await window.kb.upload('/me/profile', fd);
                this.uploadingPhoto = false;
                if (res.ok) { this.me = res.json?.data || this.me; this.saved = true; return; }
                this.error = window.kb.errorText(res, t('account.photo_error'));
            },
            async toggleNotifPrefs() {
                this.showNotifPrefs = !this.showNotifPrefs;
                if (!this.showNotifPrefs || Object.keys(this.prefs).length) return;
                const res = await window.kb.api('/me/notification-preferences');
                if (res.ok) this.prefs = res.json?.data || {};
            },
            async setPref(key, value) {
                this.prefs = { ...this.prefs, [key]: value };
                this.prefsSaving = true;
                const body = {};
                this.notifRows.forEach(r => { body[r.key] = !!this.prefs[r.key]; });
                await window.kb.api('/me/notification-preferences', { method: 'PUT', body });
                this.prefsSaving = false;
            },
        };
    }
</script>
@endpush
@endsection
