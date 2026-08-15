@extends('webapp.layout')
@section('title', 'Kolab')

@section('body')
<div x-data="kolabDetail()" x-init="init()">
    @include('webapp.partials.nav')

    <main class="max-w-2xl mx-auto px-5 py-8">
        <template x-if="loading"><p class="text-off-black/50">Loading…</p></template>
        <template x-if="error"><div class="rounded-xl bg-red-50 text-red-700 text-sm px-4 py-3" x-text="error"></div></template>

        <template x-if="k">
            <div>
                <template x-if="k.offer_photo">
                    <img :src="k.offer_photo" alt="" class="w-full h-56 object-cover rounded-2xl">
                </template>

                <div class="flex items-center gap-2 mt-4">
                    <span class="text-xs font-semibold text-off-black/50" x-text="intentLabel(k.intent_type)"></span>
                    <template x-if="k.status !== 'published'">
                        <span class="text-[10px] uppercase tracking-wide font-bold px-2 py-0.5 rounded-full bg-off-black/10 text-off-black/60" x-text="k.status"></span>
                    </template>
                </div>

                <h1 class="font-montserrat font-black text-2xl tracking-tight mt-1" x-text="k.title"></h1>
                <p class="text-off-black/60 mt-1" x-text="[k.preferred_city, k.area].filter(Boolean).join(' · ')"></p>

                <template x-if="k.offer_headline">
                    <p class="mt-4 font-semibold" x-text="k.offer_headline"></p>
                </template>
                <p class="mt-2 whitespace-pre-line text-off-black/80" x-text="k.description"></p>

                {{-- Community-seeking specifics --}}
                <template x-if="k.intent_type === 'community_seeking'">
                    <div class="mt-5 grid sm:grid-cols-2 gap-4">
                        <template x-if="k.needs?.length">
                            <div><p class="text-sm font-semibold">Looking for</p><p class="text-sm text-off-black/60" x-text="k.needs.join(', ')"></p></div>
                        </template>
                        <template x-if="k.offers_in_return?.length">
                            <div><p class="text-sm font-semibold">Offers in return</p><p class="text-sm text-off-black/60" x-text="k.offers_in_return.join(', ')"></p></div>
                        </template>
                        <template x-if="k.typical_attendance">
                            <div><p class="text-sm font-semibold">Typical attendance</p><p class="text-sm text-off-black/60" x-text="k.typical_attendance"></p></div>
                        </template>
                    </div>
                </template>

                {{-- Product specifics --}}
                <template x-if="k.intent_type === 'product_promotion' && k.product_name">
                    <div class="mt-5"><p class="text-sm font-semibold">Product</p><p class="text-sm text-off-black/60" x-text="k.product_name"></p></div>
                </template>

                {{-- Creator --}}
                <div class="mt-6 flex items-center gap-3 border-t border-off-black/10 pt-5">
                    <img :src="k.creator_profile?.avatar_url || fallbackAvatar" alt="" class="w-10 h-10 rounded-full object-cover bg-off-black/10">
                    <div>
                        <p class="font-semibold text-sm" x-text="k.creator_profile?.display_name"></p>
                        <p class="text-xs text-off-black/50" x-text="k.creator_profile?.user_type"></p>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="mt-6 flex flex-wrap gap-2">
                    <template x-if="k.is_own">
                        <a :href="'/kolabs/' + k.id + '/edit'" class="rounded-xl bg-off-black/5 text-sm font-semibold px-4 py-2">Edit</a>
                    </template>
                    <template x-if="k.is_own && k.status === 'draft'">
                        <button @click="publish()" :disabled="busy" class="rounded-xl bg-off-black text-off-white text-sm font-semibold px-4 py-2 disabled:opacity-50">Publish</button>
                    </template>
                    <template x-if="!k.is_own">
                        <button @click="toggleSave()" class="rounded-xl bg-off-black/5 text-sm font-semibold px-4 py-2">
                            <span x-text="k.is_saved ? '★ Saved' : '☆ Save'"></span>
                        </button>
                    </template>
                    <template x-if="!k.is_own">
                        <a href="/welcome" class="rounded-xl bg-off-black text-off-white text-sm font-semibold px-4 py-2">Apply in the app</a>
                    </template>
                </div>
            </div>
        </template>
    </main>
</div>

@push('scripts')
<script>
    function kolabDetail() {
        return {
            k: null, loading: true, busy: false, error: '',
            id: location.pathname.split('/')[2],
            fallbackAvatar: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" fill="%23e5e2da"/></svg>',
            intentLabel(t) { return { community_seeking: 'Community seeking', venue_promotion: 'Venue', product_promotion: 'Product' }[t] || 'Kolab'; },
            async init() {
                if (!window.kb.requireAuth()) return;
                const res = await window.kb.api('/kolabs/' + this.id);
                if (res.ok) this.k = res.json?.data;
                else this.error = res.status === 404 ? 'Kolab not found.' : (res.json?.message || 'Could not load this Kolab.');
                this.loading = false;
            },
            async publish() {
                this.error = ''; this.busy = true;
                const res = await window.kb.api('/kolabs/' + this.id + '/publish', { method: 'POST' });
                this.busy = false;
                if (res.status === 402) { location.href = '/subscription'; return; }
                if (res.ok) this.k = res.json?.data || this.k;
                else this.error = res.json?.message || 'Could not publish.';
            },
            async toggleSave() {
                const method = this.k.is_saved ? 'DELETE' : 'POST';
                const res = await window.kb.api('/kolabs/' + this.id + '/save', { method });
                if (res.ok || res.status === 204) this.k.is_saved = !this.k.is_saved;
            },
        };
    }
</script>
@endpush
@endsection
