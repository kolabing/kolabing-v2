@extends('webapp.layout')
@section('title', __('webapp.kolabs.title'))

@section('body')
<div x-data="myKolabsPage()" x-init="init()">
    @include('webapp.partials.nav', ['active' => 'kolabs'])

    <main class="max-w-3xl mx-auto px-5 py-8">
        <div class="flex items-center justify-between gap-4">
            <h1 class="font-montserrat font-black text-2xl tracking-tight">{{ __('webapp.kolabs.title') }}</h1>
            <a href="{{ $base }}/kolabs/create" class="rounded-xl bg-off-black text-off-white text-sm font-semibold px-4 py-2">{{ __('webapp.kolabs.create') }}</a>
        </div>

        <template x-if="error"><div class="mt-4 rounded-xl bg-red-50 text-red-700 text-sm px-4 py-3" x-text="error"></div></template>

        <div class="mt-4 flex gap-2 text-sm">
            <template x-for="tab in tabs" :key="tab.value">
                <button @click="status = tab.value; load()"
                        :class="status === tab.value ? 'bg-off-black text-off-white' : 'bg-off-black/5'"
                        class="rounded-lg px-3 py-1.5 font-medium" x-text="tab.label"></button>
            </template>
        </div>

        <template x-if="loading"><p class="mt-8 text-off-black/50">{{ __('webapp.common.loading') }}</p></template>
        <template x-if="!loading && items.length === 0">
            <div class="mt-8 text-center text-off-black/50">
                <p>{{ __('webapp.kolabs.empty') }}</p>
                <a href="{{ $base }}/kolabs/create" class="inline-block mt-3 rounded-xl bg-off-black text-off-white text-sm font-semibold px-4 py-2">{{ __('webapp.kolabs.create_first') }}</a>
            </div>
        </template>

        <div class="mt-5 space-y-3">
            <template x-for="k in items" :key="k.id">
                <div class="rounded-2xl border border-off-black/10 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] uppercase tracking-wide font-bold px-2 py-0.5 rounded-full"
                                      :class="statusClass(k.status)" x-text="statusLabel(k.status)"></span>
                                <span class="text-xs text-off-black/50" x-text="intentLabel(k.intent_type)"></span>
                            </div>
                            <a :href="(window.KB_BASE || '') + '/kolabs/' + k.id" class="font-semibold mt-1 block leading-snug" x-text="k.title"></a>
                            <p class="text-sm text-off-black/60 mt-0.5" x-text="applicationsLabel(k.applications_count || 0)"></p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-3 text-sm">
                        <template x-if="k.status === 'draft'">
                            <button @click="publish(k)" :disabled="busy" class="rounded-lg bg-off-black text-off-white font-semibold px-3 py-1.5 disabled:opacity-50">{{ __('webapp.kolabs.publish') }}</button>
                        </template>
                        <template x-if="k.status === 'published'">
                            <button @click="close(k)" :disabled="busy" class="rounded-lg bg-off-black/5 font-semibold px-3 py-1.5">{{ __('webapp.kolabs.close') }}</button>
                        </template>
                        <a :href="(window.KB_BASE || '') + '/kolabs/' + k.id + '/edit'" class="rounded-lg bg-off-black/5 font-semibold px-3 py-1.5">{{ __('webapp.common.edit') }}</a>
                        <button @click="destroy(k)" :disabled="busy" class="rounded-lg text-red-600 font-semibold px-3 py-1.5">{{ __('webapp.common.delete') }}</button>
                    </div>
                </div>
            </template>
        </div>
    </main>
</div>

@push('scripts')
<script>
    function myKolabsPage() {
        return {
            items: [], loading: true, busy: false, error: '', status: '',
            tabs: [
                { value: '', label: t('kolabs.tab_all') },
                { value: 'draft', label: t('kolabs.tab_drafts') },
                { value: 'published', label: t('kolabs.tab_published') },
                { value: 'closed', label: t('kolabs.tab_closed') },
            ],
            intentLabel(type) {
                const map = { community_seeking: 'intent.community_seeking', venue_promotion: 'intent.venue_promotion', product_promotion: 'intent.product_promotion' };
                return window.t(map[type] || 'intent.kolab');
            },
            statusLabel(s) { return window.t('status.' + s); },
            statusClass(s) { return { draft: 'bg-amber-100 text-amber-700', published: 'bg-green-100 text-green-700', closed: 'bg-off-black/10 text-off-black/50' }[s] || 'bg-off-black/10'; },
            applicationsLabel(n) { return window.t(n === 1 ? 'kolabs.application_count' : 'kolabs.applications_count', { count: n }); },
            async init() { if (!window.kb.requireAuth()) return; await this.load(); },
            async load() {
                this.loading = true; this.error = '';
                const p = new URLSearchParams({ per_page: 50 });
                if (this.status) p.set('status', this.status);
                const res = await window.kb.api('/kolabs/me?' + p.toString());
                if (res.ok) this.items = res.json?.data || [];
                else this.error = res.json?.message || t('kolabs.load_error');
                this.loading = false;
            },
            async publish(k) {
                this.error = ''; this.busy = true;
                const res = await window.kb.api('/kolabs/' + k.id + '/publish', { method: 'POST' });
                this.busy = false;
                if (res.status === 402) { window.nav('/subscription'); return; }
                if (res.ok) k.status = 'published';
                else this.error = res.json?.message || t('kolabs.publish_error');
            },
            async close(k) {
                this.busy = true;
                const res = await window.kb.api('/kolabs/' + k.id + '/close', { method: 'POST' });
                this.busy = false;
                if (res.ok) k.status = 'closed'; else this.error = res.json?.message || t('kolabs.close_error');
            },
            async destroy(k) {
                if (!confirm(t('kolabs.delete_confirm'))) return;
                this.busy = true;
                const res = await window.kb.api('/kolabs/' + k.id, { method: 'DELETE' });
                this.busy = false;
                if (res.ok) this.items = this.items.filter(i => i.id !== k.id);
                else this.error = res.json?.message || t('kolabs.delete_error');
            },
        };
    }
</script>
@endpush
@endsection
