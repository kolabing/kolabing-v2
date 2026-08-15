@extends('webapp.layout')
@section('title', 'Account')

@section('body')
<div x-data="accountPage()" x-init="init()">
    @include('webapp.partials.nav', ['active' => 'account'])

    <main class="max-w-xl mx-auto px-5 py-8">
        <h1 class="font-montserrat font-black text-2xl tracking-tight">Your profile</h1>

        <template x-if="loading"><p class="mt-6 text-off-black/50">Loading…</p></template>
        <template x-if="error"><div class="mt-4 rounded-xl bg-red-50 text-red-700 text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div></template>
        <template x-if="saved"><div class="mt-4 rounded-xl bg-green-50 text-green-700 text-sm px-4 py-3">Saved.</div></template>

        <form x-show="!loading" @submit.prevent="save()" class="mt-5 space-y-4">
            <div class="flex items-center gap-4">
                <img :src="avatarUrl || fallbackAvatar" alt="" class="w-16 h-16 rounded-full object-cover bg-off-black/10">
                <div>
                    <label class="text-sm font-semibold block">Logo / photo</label>
                    <input type="file" accept="image/*" @change="uploadPhoto($event)" class="mt-1 block text-sm text-off-black/70">
                    <p class="text-xs text-off-black/50" x-show="uploadingPhoto">Uploading…</p>
                </div>
            </div>
            <div>
                <label class="text-sm font-semibold" x-text="isBusiness ? 'Business name' : 'Community name'"></label>
                <input x-model="form.name" maxlength="255" class="mt-1 w-full rounded-xl border-off-black/15 px-4 py-3 focus:border-off-black focus:ring-0">
            </div>
            <div>
                <label class="text-sm font-semibold">About</label>
                <textarea x-model="form.about" maxlength="2000" rows="3" class="mt-1 w-full rounded-xl border-off-black/15 px-4 py-3 focus:border-off-black focus:ring-0"></textarea>
            </div>

            <template x-if="isBusiness">
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-semibold">Business type</label>
                        <select x-model="form.business_type" class="mt-1 w-full rounded-xl border-off-black/15 px-4 py-3 focus:border-off-black focus:ring-0">
                            <option value="">Select…</option>
                            <template x-for="o in businessTypes" :key="o.value"><option :value="o.value" x-text="o.label"></option></template>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-semibold">Categories <span class="text-off-black/40 font-normal">(1–3)</span></label>
                        <div class="mt-2 flex flex-wrap gap-2" x-html="chips('categories', businessTypes)"></div>
                    </div>
                </div>
            </template>

            <template x-if="!isBusiness">
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-semibold">Community type</label>
                        <select x-model="form.community_type" class="mt-1 w-full rounded-xl border-off-black/15 px-4 py-3 focus:border-off-black focus:ring-0">
                            <option value="">Select…</option>
                            <template x-for="o in communityTypes" :key="o.value"><option :value="o.value" x-text="o.label"></option></template>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-semibold">Community size</label>
                        <input x-model.number="form.community_size" type="number" min="1" class="mt-1 w-full rounded-xl border-off-black/15 px-4 py-3 focus:border-off-black focus:ring-0">
                    </div>
                </div>
            </template>

            <div>
                <label class="text-sm font-semibold">City</label>
                <select x-model="form.city_id" class="mt-1 w-full rounded-xl border-off-black/15 px-4 py-3 focus:border-off-black focus:ring-0">
                    <option value="">Select…</option>
                    <template x-for="c in cities" :key="c.id"><option :value="c.id" x-text="c.name"></option></template>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-sm font-semibold">Instagram</label>
                    <input x-model="form.instagram" maxlength="255" placeholder="@handle" class="mt-1 w-full rounded-xl border-off-black/15 px-4 py-3 focus:border-off-black focus:ring-0">
                </div>
                <div>
                    <label class="text-sm font-semibold">Website</label>
                    <input x-model="form.website" type="url" placeholder="https://" class="mt-1 w-full rounded-xl border-off-black/15 px-4 py-3 focus:border-off-black focus:ring-0">
                </div>
            </div>
            <template x-if="!isBusiness">
                <div>
                    <label class="text-sm font-semibold">TikTok</label>
                    <input x-model="form.tiktok" maxlength="255" placeholder="@handle" class="mt-1 w-full rounded-xl border-off-black/15 px-4 py-3 focus:border-off-black focus:ring-0">
                </div>
            </template>

            <div class="pt-2">
                <button type="submit" :disabled="busy" class="rounded-xl bg-off-black text-off-white font-semibold px-5 py-3 disabled:opacity-50">
                    <span x-text="busy ? 'Saving…' : 'Save profile'"></span>
                </button>
            </div>
            <p class="text-xs text-off-black/50">To change your logo/photo, use the Kolabing app.</p>
        </form>
    </main>
</div>

@push('scripts')
<script>
    function accountPage() {
        return {
            loading: true, busy: false, uploadingPhoto: false, error: '', saved: false, isBusiness: false,
            avatarUrl: '',
            fallbackAvatar: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><rect width="64" height="64" fill="%23e5e2da"/></svg>',
            businessTypes: [], communityTypes: [], cities: [],
            form: { name: '', about: '', business_type: '', categories: [], community_type: '', community_size: null, city_id: '', instagram: '', tiktok: '', website: '' },
            async init() {
                if (!window.kb.requireAuth()) return;
                const me = await window.kb.api('/auth/me');
                if (!me.ok) { window.kb.logout(); return; }
                const u = me.json?.data || {};
                this.isBusiness = u.user_type === 'business';
                await this.loadLookups();
                this.prefill(u);
                this.loading = false;
            },
            async loadLookups() {
                const [bt, ct, ci] = await Promise.all([
                    window.kb.api('/lookup/business-types', { auth: false }),
                    window.kb.api('/lookup/community-types', { auth: false }),
                    window.kb.api('/cities', { auth: false }),
                ]);
                if (bt.ok) this.businessTypes = bt.json?.data || [];
                if (ct.ok) this.communityTypes = ct.json?.data || [];
                if (ci.ok) this.cities = (ci.json?.data || []).filter(c => c.id !== 'other');
            },
            prefill(u) {
                const p = this.isBusiness ? (u.business_profile || {}) : (u.community_profile || {});
                this.avatarUrl = p.logo_url || p.profile_photo || u.avatar_url || '';
                this.form.name = p.name || '';
                this.form.about = p.about || '';
                this.form.city_id = p.city?.id || '';
                this.form.instagram = p.instagram || '';
                this.form.website = p.website || '';
                if (this.isBusiness) {
                    this.form.business_type = p.business_type || '';
                    this.form.categories = Array.isArray(p.categories) ? [...p.categories] : [];
                } else {
                    this.form.community_type = p.community_type || '';
                    this.form.community_size = p.community_size ?? null;
                    this.form.tiktok = p.tiktok || '';
                }
            },
            chips(field, options) {
                return (options || []).map(o => {
                    const on = this.form[field].includes(o.value);
                    const cls = on ? 'bg-off-black text-off-white' : 'bg-off-black/5 text-off-black';
                    return `<button type="button" class="rounded-full px-3 py-1.5 text-sm font-medium ${cls}" onclick="Alpine.$data(this).toggleCat('${o.value}')">${o.label}</button>`;
                }).join('');
            },
            toggleCat(value) {
                const arr = this.form.categories;
                const i = arr.indexOf(value);
                if (i !== -1) arr.splice(i, 1);
                else if (arr.length < 3) arr.push(value);
            },
            payload() {
                const f = this.form;
                const base = { name: f.name, about: f.about, instagram: f.instagram, website: f.website };
                if (f.city_id) base.city_id = f.city_id;
                if (this.isBusiness) {
                    return { ...base, business_type: f.business_type, categories: f.categories };
                }
                return { ...base, community_type: f.community_type, community_size: f.community_size, tiktok: f.tiktok };
            },
            async uploadPhoto(e) {
                const file = e.target.files?.[0];
                if (!file) return;
                this.error = ''; this.saved = false; this.uploadingPhoto = true;
                // PUT /me/profile takes profile_photo as a file → multipart POST with method spoofing.
                const fd = new FormData();
                fd.append('_method', 'PUT');
                fd.append('profile_photo', file);
                const headers = {};
                if (window.kb.token) headers['Authorization'] = 'Bearer ' + window.kb.token;
                let res;
                try { res = await fetch(window.KB_CONFIG.apiBase + '/me/profile', { method: 'POST', headers, body: fd }); }
                catch (err) { this.uploadingPhoto = false; this.error = 'Could not upload photo.'; return; }
                this.uploadingPhoto = false;
                let j = null; try { j = await res.json(); } catch (err) { /* empty */ }
                if (res.ok) {
                    const p = this.isBusiness ? j?.data?.business_profile : j?.data?.community_profile;
                    this.avatarUrl = p?.logo_url || p?.profile_photo || j?.data?.avatar_url || this.avatarUrl;
                    this.saved = true;
                } else {
                    this.error = (j?.errors ? Object.values(j.errors).flat().join('\n') : j?.message) || 'Could not upload photo. Use a JPG/PNG under 5MB.';
                }
            },
            async save() {
                this.error = ''; this.saved = false; this.busy = true;
                const res = await window.kb.api('/me/profile', { method: 'PUT', body: this.payload() });
                this.busy = false;
                if (res.ok) { this.saved = true; window.scrollTo({ top: 0, behavior: 'smooth' }); }
                else if (res.status === 422 && res.json?.errors) this.error = Object.values(res.json.errors).flat().join('\n');
                else this.error = res.json?.message || 'Could not save your profile.';
            },
        };
    }
</script>
@endpush
@endsection
