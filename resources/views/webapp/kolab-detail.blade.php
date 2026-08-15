@extends('webapp.layout')
@section('title', __('webapp.intent.kolab'))

@section('body')
<div x-data="kolabDetail()" x-init="init()">
    @include('webapp.partials.nav')

    <main class="max-w-2xl mx-auto px-5 py-8">
        <template x-if="loading"><p class="text-off-black/50">{{ __('webapp.common.loading') }}</p></template>
        <template x-if="error"><div class="rounded-xl bg-red-50 text-red-700 text-sm px-4 py-3" x-text="error"></div></template>

        <template x-if="k">
            <div>
                <template x-if="k.offer_photo">
                    <img :src="k.offer_photo" alt="" class="w-full h-56 object-cover rounded-2xl">
                </template>

                <div class="flex items-center gap-2 mt-4">
                    <span class="text-xs font-semibold text-off-black/50" x-text="intentLabel(k.intent_type)"></span>
                    <template x-if="k.status !== 'published'">
                        <span class="text-[10px] uppercase tracking-wide font-bold px-2 py-0.5 rounded-full bg-off-black/10 text-off-black/60" x-text="statusLabel(k.status)"></span>
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
                            <div><p class="text-sm font-semibold">{{ __('webapp.detail.looking_for') }}</p><p class="text-sm text-off-black/60" x-text="k.needs.join(', ')"></p></div>
                        </template>
                        <template x-if="k.offers_in_return?.length">
                            <div><p class="text-sm font-semibold">{{ __('webapp.detail.offers_in_return') }}</p><p class="text-sm text-off-black/60" x-text="k.offers_in_return.join(', ')"></p></div>
                        </template>
                        <template x-if="k.typical_attendance">
                            <div><p class="text-sm font-semibold">{{ __('webapp.detail.typical_attendance') }}</p><p class="text-sm text-off-black/60" x-text="k.typical_attendance"></p></div>
                        </template>
                    </div>
                </template>

                {{-- Product specifics --}}
                <template x-if="k.intent_type === 'product_promotion' && k.product_name">
                    <div class="mt-5"><p class="text-sm font-semibold">{{ __('webapp.detail.product') }}</p><p class="text-sm text-off-black/60" x-text="k.product_name"></p></div>
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
                        <a :href="(window.KB_BASE || '') + '/kolabs/' + k.id + '/edit'" class="rounded-xl bg-off-black/5 text-sm font-semibold px-4 py-2">{{ __('webapp.detail.edit') }}</a>
                    </template>
                    <template x-if="k.is_own && k.status === 'draft'">
                        <button @click="publish()" :disabled="busy" class="rounded-xl bg-off-black text-off-white text-sm font-semibold px-4 py-2 disabled:opacity-50">{{ __('webapp.detail.publish') }}</button>
                    </template>
                    <template x-if="k.is_own">
                        <a href="{{ $base }}/applications" class="rounded-xl bg-off-black text-off-white text-sm font-semibold px-4 py-2">{{ __('webapp.detail.view_applications') }}</a>
                    </template>
                    <template x-if="!k.is_own">
                        <button @click="toggleSave()" class="rounded-xl bg-off-black/5 text-sm font-semibold px-4 py-2">
                            <span x-text="k.is_saved ? t('detail.saved') : t('detail.save')"></span>
                        </button>
                    </template>
                    <template x-if="!k.is_own && viewerType === 'community' && k.status === 'published' && !applied && !k.has_applied && !applyOpen">
                        <button @click="applyOpen = true" class="rounded-xl bg-off-black text-off-white text-sm font-semibold px-4 py-2">{{ __('webapp.detail.apply') }}</button>
                    </template>
                    <template x-if="!k.is_own && viewerType === 'business'">
                        <a href="{{ $base }}/welcome" class="rounded-xl bg-off-black text-off-white text-sm font-semibold px-4 py-2">{{ __('webapp.detail.open_app') }}</a>
                    </template>
                </div>

                <template x-if="!k.is_own && (applied || k.has_applied)">
                    <p class="mt-3 text-sm text-green-700 font-semibold">{{ __('webapp.detail.applied') }}</p>
                </template>

                {{-- Apply form (community) --}}
                <template x-if="applyOpen && !applied">
                    <div class="mt-4 rounded-2xl border border-off-black/10 p-5">
                        <p class="font-semibold">{{ __('webapp.detail.apply_title') }}</p>
                        <template x-if="applyError"><div class="mt-2 rounded-lg bg-red-50 text-red-700 text-sm px-3 py-2 whitespace-pre-line" x-text="applyError"></div></template>
                        <label class="text-sm font-semibold block mt-3">{{ __('webapp.detail.your_message') }}</label>
                        <textarea x-model="apply.message" rows="3" maxlength="2000" placeholder="{{ __('webapp.detail.message_placeholder') }}"
                                  class="mt-1 w-full rounded-xl border-off-black/15 px-4 py-3 focus:border-off-black focus:ring-0"></textarea>
                        <label class="text-sm font-semibold block mt-3">{{ __('webapp.detail.your_availability') }} <span class="text-off-black/40 font-normal">({{ __('webapp.detail.availability_hint') }})</span></label>
                        <textarea x-model="apply.availability" rows="2" maxlength="500" placeholder="{{ __('webapp.detail.availability_placeholder') }}"
                                  class="mt-1 w-full rounded-xl border-off-black/15 px-4 py-3 focus:border-off-black focus:ring-0"></textarea>
                        <div class="mt-3 flex gap-2">
                            <button @click="submitApply()" :disabled="busy" class="rounded-xl bg-off-black text-off-white text-sm font-semibold px-4 py-2 disabled:opacity-50">
                                <span x-text="busy ? t('detail.sending') : t('detail.send')"></span>
                            </button>
                            <button @click="applyOpen = false" class="rounded-xl bg-off-black/5 text-sm font-semibold px-4 py-2">{{ __('webapp.common.cancel') }}</button>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </main>
</div>

@push('scripts')
<script>
    function kolabDetail() {
        return {
            k: null, loading: true, busy: false, error: '',
            id: location.pathname.slice((window.KB_BASE || '').length).split('/')[2],
            viewerType: '', applied: false, applyOpen: false, applyError: '',
            apply: { message: '', availability: '' },
            fallbackAvatar: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" fill="%23e5e2da"/></svg>',
            intentLabel(type) {
                const map = { community_seeking: 'intent.community_seeking', venue_promotion: 'intent.venue_promotion', product_promotion: 'intent.product_promotion' };
                return window.t(map[type] || 'intent.kolab');
            },
            statusLabel(s) { return window.t('status.' + s); },
            async init() {
                if (!window.kb.requireAuth()) return;
                const [res, me] = await Promise.all([
                    window.kb.api('/kolabs/' + this.id),
                    window.kb.api('/auth/me'),
                ]);
                if (res.ok) this.k = res.json?.data;
                else this.error = res.status === 404 ? t('detail.not_found') : (res.json?.message || t('detail.load_error'));
                if (me.ok) this.viewerType = me.json?.data?.user_type || '';
                this.loading = false;
            },
            async submitApply() {
                this.applyError = '';
                if ((this.apply.availability || '').trim().length < 20) {
                    this.applyError = t('detail.availability_too_short');
                    return;
                }
                this.busy = true;
                const res = await window.kb.api('/kolabs/' + this.id + '/applications', {
                    method: 'POST', body: { message: this.apply.message, availability: this.apply.availability },
                });
                this.busy = false;
                if (res.ok) { this.applied = true; this.applyOpen = false; }
                else if (res.status === 422 && res.json?.errors) this.applyError = Object.values(res.json.errors).flat().join('\n');
                else this.applyError = res.json?.message || t('detail.apply_error');
            },
            async publish() {
                this.error = ''; this.busy = true;
                const res = await window.kb.api('/kolabs/' + this.id + '/publish', { method: 'POST' });
                this.busy = false;
                if (res.status === 402) { window.nav('/subscription'); return; }
                if (res.ok) this.k = res.json?.data || this.k;
                else this.error = res.json?.message || t('detail.publish_error');
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
