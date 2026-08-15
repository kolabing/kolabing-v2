@extends('webapp.layout')
@section('title', 'Your Kolabs')

@section('body')
<div x-data="myKolabsPage()" x-init="init()">
    @include('webapp.partials.nav', ['active' => 'kolabs'])

    <main class="max-w-3xl mx-auto px-5 py-8">
        <div class="flex items-center justify-between gap-4">
            <h1 class="font-montserrat font-black text-2xl tracking-tight">Your Kolabs</h1>
            <a href="{{ $base }}/kolabs/create" class="rounded-xl bg-off-black text-off-white text-sm font-semibold px-4 py-2">Create Kolab</a>
        </div>

        <template x-if="error"><div class="mt-4 rounded-xl bg-red-50 text-red-700 text-sm px-4 py-3" x-text="error"></div></template>

        <div class="mt-4 flex gap-2 text-sm">
            <template x-for="t in tabs" :key="t.value">
                <button @click="status = t.value; load()"
                        :class="status === t.value ? 'bg-off-black text-off-white' : 'bg-off-black/5'"
                        class="rounded-lg px-3 py-1.5 font-medium" x-text="t.label"></button>
            </template>
        </div>

        <template x-if="loading"><p class="mt-8 text-off-black/50">Loading…</p></template>
        <template x-if="!loading && items.length === 0">
            <div class="mt-8 text-center text-off-black/50">
                <p>No Kolabs here yet.</p>
                <a href="{{ $base }}/kolabs/create" class="inline-block mt-3 rounded-xl bg-off-black text-off-white text-sm font-semibold px-4 py-2">Create your first Kolab</a>
            </div>
        </template>

        <div class="mt-5 space-y-3">
            <template x-for="k in items" :key="k.id">
                <div class="rounded-2xl border border-off-black/10 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] uppercase tracking-wide font-bold px-2 py-0.5 rounded-full"
                                      :class="statusClass(k.status)" x-text="k.status"></span>
                                <span class="text-xs text-off-black/50" x-text="intentLabel(k.intent_type)"></span>
                            </div>
                            <a :href="(window.KB_BASE || '') + '/kolabs/' + k.id" class="font-semibold mt-1 block leading-snug" x-text="k.title"></a>
                            <p class="text-sm text-off-black/60 mt-0.5" x-text="(k.applications_count || 0) + ' application' + ((k.applications_count === 1) ? '' : 's')"></p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-3 text-sm">
                        <template x-if="k.status === 'draft'">
                            <button @click="publish(k)" :disabled="busy" class="rounded-lg bg-off-black text-off-white font-semibold px-3 py-1.5 disabled:opacity-50">Publish</button>
                        </template>
                        <template x-if="k.status === 'published'">
                            <button @click="close(k)" :disabled="busy" class="rounded-lg bg-off-black/5 font-semibold px-3 py-1.5">Close</button>
                        </template>
                        <a :href="(window.KB_BASE || '') + '/kolabs/' + k.id + '/edit'" class="rounded-lg bg-off-black/5 font-semibold px-3 py-1.5">Edit</a>
                        <button @click="destroy(k)" :disabled="busy" class="rounded-lg text-red-600 font-semibold px-3 py-1.5">Delete</button>
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
                { value: '', label: 'All' },
                { value: 'draft', label: 'Drafts' },
                { value: 'published', label: 'Published' },
                { value: 'closed', label: 'Closed' },
            ],
            intentLabel(t) { return { community_seeking: 'Community seeking', venue_promotion: 'Venue', product_promotion: 'Product' }[t] || 'Kolab'; },
            statusClass(s) { return { draft: 'bg-amber-100 text-amber-700', published: 'bg-green-100 text-green-700', closed: 'bg-off-black/10 text-off-black/50' }[s] || 'bg-off-black/10'; },
            async init() { if (!window.kb.requireAuth()) return; await this.load(); },
            async load() {
                this.loading = true; this.error = '';
                const p = new URLSearchParams({ per_page: 50 });
                if (this.status) p.set('status', this.status);
                const res = await window.kb.api('/kolabs/me?' + p.toString());
                if (res.ok) this.items = res.json?.data || [];
                else this.error = res.json?.message || 'Could not load your Kolabs.';
                this.loading = false;
            },
            async publish(k) {
                this.error = ''; this.busy = true;
                const res = await window.kb.api('/kolabs/' + k.id + '/publish', { method: 'POST' });
                this.busy = false;
                if (res.status === 402) { window.nav('/subscription'); return; }
                if (res.ok) k.status = 'published';
                else this.error = res.json?.message || 'Could not publish.';
            },
            async close(k) {
                this.busy = true;
                const res = await window.kb.api('/kolabs/' + k.id + '/close', { method: 'POST' });
                this.busy = false;
                if (res.ok) k.status = 'closed'; else this.error = res.json?.message || 'Could not close.';
            },
            async destroy(k) {
                if (!confirm('Delete this Kolab? This cannot be undone.')) return;
                this.busy = true;
                const res = await window.kb.api('/kolabs/' + k.id, { method: 'DELETE' });
                this.busy = false;
                if (res.ok) this.items = this.items.filter(i => i.id !== k.id);
                else this.error = res.json?.message || 'Could not delete.';
            },
        };
    }
</script>
@endpush
@endsection
