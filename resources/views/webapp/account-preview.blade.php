@extends('webapp.layout')
@section('title', __('webapp.account.preview.title'))

@section('body')
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), accountPreviewPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'account'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[880px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        @include('webapp.partials.account-nav', ['accountActive' => 'preview'])

        <p class="mt-5 text-sm text-muted">{{ __('webapp.account.preview.help') }}</p>

        <template x-if="error">
            <div class="mt-4 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3" x-text="error"></div>
        </template>

        <template x-if="loading">
            <p class="mt-8 text-muted">{{ __('webapp.common.loading') }}</p>
        </template>

        <template x-if="!loading && profile">
        <div>
            {{-- ── Identity ────────────────────────────────────────────── --}}
            <div class="mt-6 bg-white border border-ink/[.08] rounded-2xl p-6 shadow-card">
                <div class="flex items-center gap-4">
                    <template x-if="avatar">
                        <img :src="avatar" alt="" class="w-20 h-20 rounded-full object-cover shrink-0 bg-cream-low">
                    </template>
                    <template x-if="!avatar">
                        <div class="w-20 h-20 rounded-full bg-primary/50 flex items-center justify-center text-2xl font-bold shrink-0"
                             x-text="window.kbInitial(profile.display_name)"></div>
                    </template>
                    <div class="min-w-0">
                        <p class="font-anton text-[22px] tracking-[.5px] truncate" x-text="profile.display_name"></p>
                        <p class="text-[12px] text-muted" x-text="[profile.type, profile.city_name].filter(Boolean).join(' · ')"></p>
                    </div>
                </div>

                <p x-show="profile.about" x-cloak class="mt-4 text-sm text-body" x-text="profile.about"></p>

                <div class="mt-5 flex flex-wrap gap-2">
                    <input type="text" readonly :value="profile.public_url || ''"
                           class="flex-1 min-w-[220px] h-11 px-4 rounded-2xl bg-cream-low border border-ink/[.12] text-sm">
                    <button type="button" @click="copyLink()"
                            class="h-11 px-4 rounded-pill bg-white border border-ink/[.12] text-sm font-bold"
                            x-text="copied ? '{{ __('webapp.account.preview.copied') }}' : '{{ __('webapp.account.preview.copy') }}'"></button>
                </div>
            </div>

            {{-- ── Gallery ─────────────────────────────────────────────── --}}
            <div class="mt-3 bg-white border border-ink/[.08] rounded-2xl p-6 shadow-card">
                <p class="text-[11px] font-semibold uppercase tracking-[.14em] text-muted">{{ __('webapp.account.tabs.gallery') }}</p>

                <template x-if="!gallery.length">
                    <div class="mt-3">
                        <p class="text-sm text-muted">{{ __('webapp.account.preview.gallery_empty') }}</p>
                        <a :href="kbPath('/account/gallery')" class="mt-3 inline-flex h-9 items-center px-4 rounded-pill bg-cream-low text-sm font-bold">{{ __('webapp.account.preview.go_gallery') }}</a>
                    </div>
                </template>

                <div x-show="gallery.length" x-cloak class="mt-4 grid grid-cols-3 sm:grid-cols-5 gap-2.5">
                    <template x-for="photo in gallery" :key="photo.id || photo.url">
                        <img :src="photo.url" alt="" class="w-full aspect-square object-cover rounded-xl bg-cream-low">
                    </template>
                </div>
            </div>

            {{-- ── Past events ─────────────────────────────────────────── --}}
            <div class="mt-3 bg-white border border-ink/[.08] rounded-2xl p-6 shadow-card">
                <p class="text-[11px] font-semibold uppercase tracking-[.14em] text-muted">
                    {{ __('webapp.account.tabs.events') }}
                    <span class="text-muted tabular-nums" x-text="'(' + pastEvents.length + ')'"></span>
                </p>

                <template x-if="!pastEvents.length">
                    <div class="mt-3">
                        <p class="text-sm text-muted">{{ __('webapp.account.preview.events_empty') }}</p>
                        <a :href="kbPath('/account/events')" class="mt-3 inline-flex h-9 items-center px-4 rounded-pill bg-cream-low text-sm font-bold">{{ __('webapp.account.preview.go_events') }}</a>
                    </div>
                </template>

                <div x-show="pastEvents.length" x-cloak class="mt-4 flex flex-col gap-3">
                    <template x-for="(event, index) in pastEvents" :key="index">
                        <div class="rounded-2xl bg-cream-low/70 p-4">
                            <div class="flex items-baseline justify-between gap-3">
                                <p class="font-semibold text-ink" x-text="event.name || '—'"></p>
                                <p class="text-[11px] text-muted shrink-0" x-text="window.kbDateShort(event.date)"></p>
                            </div>
                            <p class="text-[11px] text-muted mt-0.5">
                                <template x-if="event.partner_name">
                                    <span><span x-text="event.partner_name"></span> <span aria-hidden="true">·</span> </span>
                                </template>
                                <template x-if="event.attendee_count">
                                    <span><span x-text="t('account.events.attendee_count', { count: event.attendee_count })"></span></span>
                                </template>
                            </p>
                            <div x-show="event.media?.length" x-cloak class="mt-3 grid grid-cols-4 sm:grid-cols-6 gap-2">
                                <template x-for="(media, i) in (event.media || [])" :key="i">
                                    <img :src="media.url || media" alt="" class="w-full aspect-square object-cover rounded-lg bg-white">
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
        </template>
    </div>
    </main>
</div>
@endsection

@push('scripts')
<script>
    function accountPreviewPage() {
        return {
            t: (key, params) => window.t(key, params),
            kbPath: (p) => window.kbPath(p),
            loading: true,
            error: '',
            profile: null,
            copied: false,

            get gallery() { return this.profile?.gallery || []; },
            get pastEvents() { return this.profile?.past_events || []; },
            get avatar() { return this.profile?.avatar_url || this.profile?.profile_photo || ''; },

            async init() {
                await this.loadShell();
                if (!this.me?.id) { this.loading = false; return; }
                await this.load();
            },

            async load() {
                this.loading = true;
                // The rich endpoint — it carries gallery, past_events and public_url
                // for business and community profiles alike.
                const res = await window.kb.api('/profiles/' + this.me.id + '/public-profile');
                this.loading = false;
                if (!res.ok) { this.error = window.kb.errorText(res, window.t('account.preview.load_error')); return; }
                this.profile = res.json?.data || null;
            },

            async copyLink() {
                const url = this.profile?.public_url;
                if (!url) return;
                try {
                    await navigator.clipboard.writeText(url);
                    this.copied = true;
                    setTimeout(() => { this.copied = false; }, 2500);
                } catch (e) {
                    // Clipboard is permission-gated; the field is selectable instead.
                }
            },
        };
    }
</script>
@endpush
