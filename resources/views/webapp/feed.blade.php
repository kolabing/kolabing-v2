@extends('webapp.layout')
@section('title', 'Feed')

@section('body')
<div x-data="feedPage()" x-init="init()">
    @include('webapp.partials.nav', ['active' => 'feed'])

    <main class="max-w-4xl mx-auto px-5 py-8">
        <h1 class="font-montserrat font-black text-2xl tracking-tight">Discover Kolabs</h1>

        <div class="mt-4 flex flex-wrap gap-2">
            <input x-model="filters.search" @keydown.enter="reload()" type="search" placeholder="Search…"
                   class="rounded-xl border-off-black/15 px-3 py-2 text-sm flex-1 min-w-[8rem] focus:border-off-black focus:ring-0">
            <input x-model="filters.city" @keydown.enter="reload()" type="text" placeholder="City"
                   class="rounded-xl border-off-black/15 px-3 py-2 text-sm w-32 focus:border-off-black focus:ring-0">
            <select x-model="filters.intent_type" @change="reload()"
                    class="rounded-xl border-off-black/15 px-3 py-2 text-sm focus:border-off-black focus:ring-0">
                <option value="">All types</option>
                <option value="community_seeking">Community seeking</option>
                <option value="venue_promotion">Venue</option>
                <option value="product_promotion">Product</option>
            </select>
            <button @click="reload()" class="rounded-xl bg-off-black text-off-white text-sm font-semibold px-4">Search</button>
        </div>

        <template x-if="loading"><p class="mt-8 text-off-black/50">Loading…</p></template>
        <template x-if="!loading && items.length === 0"><p class="mt-8 text-off-black/50">No Kolabs found. Try a wider search.</p></template>

        <div class="mt-5 grid sm:grid-cols-2 gap-4">
            <template x-for="k in items" :key="k.id">
                <div class="rounded-2xl border border-off-black/10 overflow-hidden flex flex-col">
                    <a :href="(window.KB_BASE || '') + '/kolabs/' + k.id" class="block">
                        <template x-if="k.offer_photo">
                            <img :src="k.offer_photo" alt="" class="w-full h-36 object-cover">
                        </template>
                        <template x-if="!k.offer_photo">
                            <div class="w-full h-36 bg-off-black/5 flex items-center justify-center text-off-black/30 text-sm" x-text="intentLabel(k.intent_type)"></div>
                        </template>
                    </a>
                    <div class="p-4 flex flex-col flex-1">
                        <span class="text-xs font-semibold text-off-black/50" x-text="intentLabel(k.intent_type) + (k.preferred_city ? ' · ' + k.preferred_city : '')"></span>
                        <a :href="(window.KB_BASE || '') + '/kolabs/' + k.id" class="font-semibold mt-1 leading-snug" x-text="k.title"></a>
                        <p class="text-sm text-off-black/60 mt-1 line-clamp-2 flex-1" x-text="k.offer_headline || k.description"></p>
                        <div class="flex items-center justify-between mt-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <img :src="k.creator_profile?.avatar_url || fallbackAvatar" alt="" class="w-6 h-6 rounded-full object-cover bg-off-black/10">
                                <span class="text-xs text-off-black/60 truncate" x-text="k.creator_profile?.display_name"></span>
                            </div>
                            <button @click="toggleSave(k)" class="text-off-black/50 hover:text-off-black" :title="k.is_saved ? 'Unsave' : 'Save'">
                                <span x-text="k.is_saved ? '★' : '☆'" class="text-lg"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="mt-6 text-center" x-show="!loading && page < lastPage">
            <button @click="loadMore()" class="rounded-xl border border-off-black/20 text-sm font-semibold px-5 py-2">Load more</button>
        </div>
    </main>
</div>

@push('scripts')
<script>
    function feedPage() {
        return {
            items: [], loading: true, page: 1, lastPage: 1,
            filters: { search: '', city: '', intent_type: '' },
            fallbackAvatar: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"><rect width="24" height="24" fill="%23e5e2da"/></svg>',
            intentLabel(t) {
                return { community_seeking: 'Community seeking', venue_promotion: 'Venue', product_promotion: 'Product' }[t] || 'Kolab';
            },
            async init() {
                if (!window.kb.requireAuth()) return;
                await this.load(1);
            },
            reload() { this.load(1); },
            async load(page) {
                this.loading = true;
                const p = new URLSearchParams({ page, per_page: 20 });
                if (this.filters.search) p.set('search', this.filters.search);
                if (this.filters.city) p.set('city', this.filters.city);
                if (this.filters.intent_type) p.set('intent_type', this.filters.intent_type);
                const res = await window.kb.api('/kolabs?' + p.toString());
                if (res.ok) {
                    const rows = res.json?.data || [];
                    this.items = page === 1 ? rows : this.items.concat(rows);
                    this.page = res.json?.meta?.current_page || page;
                    this.lastPage = res.json?.meta?.last_page || page;
                }
                this.loading = false;
            },
            loadMore() { this.load(this.page + 1); },
            async toggleSave(k) {
                const method = k.is_saved ? 'DELETE' : 'POST';
                const res = await window.kb.api('/kolabs/' + k.id + '/save', { method });
                if (res.ok || res.status === 204) k.is_saved = !k.is_saved;
            },
        };
    }
</script>
@endpush
@endsection
