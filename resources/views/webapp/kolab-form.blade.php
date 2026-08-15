@extends('webapp.layout')
@section('title', 'Create Kolab')

@section('body')
<div x-data="kolabForm()" x-init="init()">
    @include('webapp.partials.nav', ['active' => 'kolabs'])

    <main class="max-w-xl mx-auto px-5 py-8">
        <h1 class="font-montserrat font-black text-2xl tracking-tight" x-text="isEdit ? 'Edit Kolab' : 'Create a Kolab'"></h1>

        <template x-if="loading"><p class="mt-6 text-off-black/50">Loading…</p></template>
        <template x-if="error"><div class="mt-4 rounded-xl bg-red-50 text-red-700 text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div></template>

        {{-- Business can also run venue promotions, but those need photos + a venue profile → app only. --}}
        <template x-if="!loading && canPickVenue">
            <div class="mt-4 rounded-xl bg-off-black/5 text-off-black/70 text-sm px-4 py-3">
                Promoting a <b>venue</b> with photos? Create those in the Kolabing app — this web form covers product promotions.
            </div>
        </template>

        <form x-show="!loading" @submit.prevent="submit()" class="mt-5 space-y-4">
            <div>
                <label class="text-sm font-semibold">Title</label>
                <input x-model="form.title" required maxlength="255" class="mt-1 w-full rounded-xl border-off-black/15 px-4 py-3 focus:border-off-black focus:ring-0">
            </div>
            <div>
                <label class="text-sm font-semibold">Short headline <span class="text-off-black/40 font-normal">(optional, max 50)</span></label>
                <input x-model="form.offer_headline" maxlength="50" class="mt-1 w-full rounded-xl border-off-black/15 px-4 py-3 focus:border-off-black focus:ring-0">
            </div>
            <div>
                <label class="text-sm font-semibold">Description</label>
                <textarea x-model="form.description" required maxlength="5000" rows="4" class="mt-1 w-full rounded-xl border-off-black/15 px-4 py-3 focus:border-off-black focus:ring-0"></textarea>
            </div>
            <div>
                <label class="text-sm font-semibold">City</label>
                <input x-model="form.preferred_city" required maxlength="100" class="mt-1 w-full rounded-xl border-off-black/15 px-4 py-3 focus:border-off-black focus:ring-0">
            </div>

            <div>
                <label class="text-sm font-semibold">Cover photo <span class="text-off-black/40 font-normal">(optional)</span></label>
                <template x-if="form.cover">
                    <div class="mt-2 relative">
                        <img :src="form.cover" alt="" class="w-full h-40 object-cover rounded-xl">
                        <button type="button" @click="form.cover = ''" class="absolute top-2 right-2 rounded-full bg-off-black/70 text-off-white text-xs font-semibold px-2 py-1">Remove</button>
                    </div>
                </template>
                <template x-if="!form.cover">
                    <input type="file" accept="image/*" @change="onCover($event)" class="mt-1 block w-full text-sm text-off-black/70">
                </template>
                <p class="text-xs text-off-black/50 mt-1" x-show="uploading">Uploading…</p>
            </div>

            {{-- Community seeking --}}
            <template x-if="form.intent_type === 'community_seeking'">
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-semibold">What you need</label>
                        <div class="mt-2 flex flex-wrap gap-2" x-html="chips('needs', lookups.needs)"></div>
                    </div>
                    <div>
                        <label class="text-sm font-semibold">Typical attendance</label>
                        <input x-model.number="form.typical_attendance" type="number" min="1" class="mt-1 w-full rounded-xl border-off-black/15 px-4 py-3 focus:border-off-black focus:ring-0">
                    </div>
                    <div>
                        <label class="text-sm font-semibold">What you offer in return</label>
                        <div class="mt-2 flex flex-wrap gap-2" x-html="chips('offers_in_return', lookups.deliverables)"></div>
                    </div>
                </div>
            </template>

            {{-- Product promotion --}}
            <template x-if="form.intent_type === 'product_promotion'">
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-semibold">Product name</label>
                        <input x-model="form.product_name" maxlength="255" class="mt-1 w-full rounded-xl border-off-black/15 px-4 py-3 focus:border-off-black focus:ring-0">
                    </div>
                    <div>
                        <label class="text-sm font-semibold">Product type</label>
                        <select x-model="form.product_type" class="mt-1 w-full rounded-xl border-off-black/15 px-4 py-3 focus:border-off-black focus:ring-0">
                            <option value="">Select…</option>
                            <template x-for="o in lookups.product_types" :key="o.value">
                                <option :value="o.value" x-text="o.label"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-semibold">What you're offering</label>
                        <div class="mt-2 flex flex-wrap gap-2" x-html="chips('offering', lookups.offerings)"></div>
                    </div>
                    <div>
                        <label class="text-sm font-semibold">What you expect back <span class="text-off-black/40 font-normal">(optional)</span></label>
                        <div class="mt-2 flex flex-wrap gap-2" x-html="chips('expects', lookups.deliverables)"></div>
                    </div>
                </div>
            </template>

            <div class="pt-2 flex gap-2">
                <button type="submit" :disabled="busy" class="rounded-xl bg-off-black text-off-white font-semibold px-5 py-3 disabled:opacity-50">
                    <span x-text="busy ? 'Saving…' : (isEdit ? 'Save changes' : 'Save draft')"></span>
                </button>
                <a href="/kolabs" class="rounded-xl bg-off-black/5 font-semibold px-5 py-3">Cancel</a>
            </div>
            <p class="text-xs text-off-black/50" x-show="!isEdit">You'll publish it (and go live) from the Kolab page after saving.</p>
        </form>
    </main>
</div>

@push('scripts')
<script>
    function kolabForm() {
        const parts = location.pathname.split('/');
        const editId = (parts[3] === 'edit') ? parts[2] : null;
        return {
            loading: true, busy: false, uploading: false, error: '', isEdit: !!editId, editId,
            canPickVenue: false,
            lookups: { needs: [], deliverables: [], offerings: [], product_types: [] },
            form: {
                intent_type: 'community_seeking', title: '', offer_headline: '', description: '', preferred_city: '',
                cover: '',
                needs: [], typical_attendance: null, offers_in_return: [],
                product_name: '', product_type: '', offering: [], expects: [],
            },
            async onCover(e) {
                const file = e.target.files?.[0];
                if (!file) return;
                this.error = ''; this.uploading = true;
                const r = await window.kb.uploadFile(file, 'kolabs');
                this.uploading = false;
                if (r.ok && r.json?.data?.url) this.form.cover = r.json.data.url;
                else this.error = r.json?.message || 'Could not upload the image. Try a smaller JPG or PNG.';
            },
            async init() {
                if (!window.kb.requireAuth()) return;
                const me = await window.kb.api('/auth/me');
                if (!me.ok) { window.kb.logout(); return; }
                const isBusiness = me.json?.data?.user_type === 'business';
                this.form.intent_type = isBusiness ? 'product_promotion' : 'community_seeking';
                this.canPickVenue = isBusiness;
                await this.loadLookups();
                if (this.isEdit) await this.loadExisting();
                this.loading = false;
            },
            async loadLookups() {
                const names = { needs: '/lookup/needs', deliverables: '/lookup/deliverables', offerings: '/lookup/offerings', product_types: '/lookup/product-types' };
                await Promise.all(Object.entries(names).map(async ([k, path]) => {
                    const res = await window.kb.api(path, { auth: false });
                    if (res.ok) this.lookups[k] = res.json?.data || [];
                }));
            },
            async loadExisting() {
                const res = await window.kb.api('/kolabs/' + this.editId);
                if (!res.ok) { this.error = 'Could not load this Kolab.'; return; }
                const k = res.json?.data || {};
                this.form.intent_type = k.intent_type || this.form.intent_type;
                for (const f of ['title', 'offer_headline', 'description', 'preferred_city', 'product_name', 'product_type', 'typical_attendance']) {
                    if (k[f] != null) this.form[f] = k[f];
                }
                for (const f of ['needs', 'offers_in_return', 'offering', 'expects']) {
                    if (Array.isArray(k[f])) this.form[f] = k[f];
                }
                this.form.cover = (Array.isArray(k.media) && k.media[0]?.url) || k.offer_photo || '';
            },
            // Renders selectable chips as HTML (Alpine @click via onclick delegation).
            chips(field, options) {
                return (options || []).map(o => {
                    const on = this.form[field].includes(o.value);
                    const cls = on ? 'bg-off-black text-off-white' : 'bg-off-black/5 text-off-black';
                    return `<button type="button" class="rounded-full px-3 py-1.5 text-sm font-medium ${cls}" onclick="Alpine.$data(this).toggle('${field}','${o.value}')">${o.label}</button>`;
                }).join('');
            },
            toggle(field, value) {
                const arr = this.form[field];
                const i = arr.indexOf(value);
                if (i === -1) arr.push(value); else arr.splice(i, 1);
            },
            payload() {
                const f = this.form;
                const base = { intent_type: f.intent_type, title: f.title, description: f.description, preferred_city: f.preferred_city };
                if (f.offer_headline) base.offer_headline = f.offer_headline;
                if (f.cover) base.media = [{ url: f.cover, type: 'image', sort_order: 0 }];
                if (f.intent_type === 'community_seeking') {
                    return { ...base, needs: f.needs, typical_attendance: f.typical_attendance, offers_in_return: f.offers_in_return };
                }
                // product_promotion
                const p = { ...base, product_name: f.product_name, product_type: f.product_type, offering: f.offering };
                if (f.expects.length) p.expects = f.expects;
                return p;
            },
            async submit() {
                this.error = ''; this.busy = true;
                const res = this.isEdit
                    ? await window.kb.api('/kolabs/' + this.editId, { method: 'PUT', body: this.payload() })
                    : await window.kb.api('/kolabs', { method: 'POST', body: this.payload() });
                this.busy = false;
                if (res.ok) {
                    const id = res.json?.data?.id || this.editId;
                    location.href = '/kolabs/' + id;
                    return;
                }
                if (res.status === 422 && res.json?.errors) {
                    this.error = Object.values(res.json.errors).flat().join('\n');
                } else {
                    this.error = res.json?.message || 'Could not save. Please check the form.';
                }
            },
        };
    }
</script>
@endpush
@endsection
