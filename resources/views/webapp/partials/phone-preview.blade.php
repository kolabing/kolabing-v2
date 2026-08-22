{{--
    Phone-frame preview of the public profile as the Flutter app renders it.

    ⚠️ THIS FILE MIRRORS THE MOBILE APP. When any of these change, change this too:
        kolabing-app/lib/features/profile/screens/public_profile_screen.dart
        kolabing-app/lib/widgets/gallery/public_gallery_section.dart
        kolabing-app/lib/features/event/widgets/past_events_section.dart

    It is a SECOND rendering of the profile UI, which is normally the wrong call —
    the web Preview tab renders the real page precisely to avoid drift. Here the
    target is a Flutter screen that cannot be embedded, so the cost is bounded
    instead: read-only (no lightbox, no pagination, no tap targets), one file, and
    the mirrored Dart files named above.

    Measurements come from the Dart source, not from taste:
      header 180px · avatar 64 · name 22/700 · gallery thumbs 112 (8px gaps)
      past-event cards 180w in a 220h rail (12px gaps) · body padding 16 · card gap 16
      spacing xs 8 / sm 12 / md 16 / lg 24 · radius sm 8 / md 12 / lg 16 / card 24
      primary #FFE28C — identical to the web palette, so no new colour is introduced

    Usage: spread kbPhonePreview() into the page's x-data and call
    initPreview() from the page's init(); call refreshPreview() after any
    successful mutation so the phone is never stale.
--}}
<aside class="hidden xl:block w-[360px] shrink-0" x-data>
    <div class="sticky top-8">
        <p class="text-[11px] font-semibold tracking-[.16em] uppercase text-muted mb-3">
            {{ __('webapp.account.phone.title') }}
        </p>

        {{-- ── Device frame ─────────────────────────────────────────────── --}}
        <div class="relative w-[340px] h-[700px] rounded-[44px] bg-ink p-[10px] shadow-cardhover">
            <div class="relative w-full h-full rounded-[35px] overflow-hidden bg-white">

                {{-- Notch --}}
                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[110px] h-[26px] bg-ink rounded-b-[14px] z-20"></div>

                <div class="w-full h-full overflow-y-auto kb-scroll" style="background:#FAF7F0">

                    <template x-if="previewLoading">
                        <div class="p-4 pt-12 flex flex-col gap-3">
                            <div class="h-[140px] rounded-2xl bg-black/5 animate-pulse"></div>
                            <div class="h-24 rounded-2xl bg-black/5 animate-pulse"></div>
                            <div class="h-24 rounded-2xl bg-black/5 animate-pulse"></div>
                        </div>
                    </template>

                    <template x-if="!previewLoading && previewError">
                        <div class="p-6 pt-16 text-center">
                            <p class="text-[13px] text-black/60" x-text="previewError"></p>
                        </div>
                    </template>

                    <template x-if="!previewLoading && !previewError && previewProfile">
                    <div>
                        {{-- ── Header: 180px, primary → primary/70 gradient ──── --}}
                        <div class="h-[180px] px-4 pb-4 flex items-end"
                             style="background:linear-gradient(to bottom, #FFE28C, rgba(255,226,140,.7))">
                            <div class="flex items-center gap-4 w-full">
                                <template x-if="previewAvatar">
                                    <img :src="previewAvatar" alt=""
                                         class="w-16 h-16 rounded-full object-cover shrink-0 bg-white/60">
                                </template>
                                <template x-if="!previewAvatar">
                                    <div class="w-16 h-16 rounded-full bg-white/60 flex items-center justify-center text-xl font-bold text-[#19150F] shrink-0"
                                         x-text="window.kbInitial(previewProfile.display_name)"></div>
                                </template>

                                <div class="min-w-0 flex-1">
                                    <p class="text-[22px] font-bold leading-tight text-[#19150F] truncate"
                                       x-text="previewProfile.display_name"></p>
                                    <p x-show="previewProfile.type" x-cloak
                                       class="text-[12px] font-medium text-[#19150F]/70 mt-0.5"
                                       x-text="previewProfile.type"></p>
                                    <p x-show="previewProfile.city_name" x-cloak
                                       class="flex items-center gap-1 text-[11px] text-[#19150F]/70 mt-0.5">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
                                        <span x-text="previewProfile.city_name"></span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- ── Body: 16px padding, 16px between cards ────────── --}}
                        <div class="p-4 flex flex-col gap-4">

                            {{-- 1. Reputation --}}
                            <div class="bg-white rounded-2xl p-4" style="box-shadow:0 1px 4px rgba(0,0,0,.05)">
                                <div class="flex items-center gap-2">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="#FFE28C" stroke="#FFE28C" stroke-width="1.5" stroke-linejoin="round"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
                                    <span class="text-[20px] font-bold text-[#19150F]" x-text="previewRating"></span>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-black/55">
                                    <span x-text="t('account.phone.reviews', { count: previewProfile.reputation?.review_count ?? 0 })"></span>
                                    <span x-text="t('account.phone.completed', { count: previewProfile.completed_collaborations_count ?? previewProfile.completed_kolabs_count ?? 0 })"></span>
                                </div>
                            </div>

                            {{-- 2. About --}}
                            <template x-if="previewProfile.about">
                                <div class="bg-white rounded-2xl p-4" style="box-shadow:0 1px 4px rgba(0,0,0,.05)">
                                    <div class="flex items-center gap-2">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFE28C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"/><path d="M14 2v5h5"/></svg>
                                        <span class="text-[14px] font-bold text-[#19150F]">{{ __('webapp.account.phone.about') }}</span>
                                    </div>
                                    <p class="mt-3 text-[13px] leading-relaxed text-black/60" x-text="previewProfile.about"></p>
                                </div>
                            </template>

                            {{-- 3. Gallery — horizontal, 112px thumbs, 8px gaps --}}
                            <template x-if="previewGallery.length">
                                <div class="bg-white rounded-2xl p-4" style="box-shadow:0 1px 4px rgba(0,0,0,.05)">
                                    <div class="flex items-center gap-2">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFE28C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.35-4.35a2 2 0 0 0-2.83 0L3 21"/></svg>
                                        <span class="text-[14px] font-bold text-[#19150F]">{{ __('webapp.account.phone.gallery') }}</span>
                                        <span class="text-[11px] text-black/45" x-text="previewGallery.length"></span>
                                    </div>
                                    <div class="mt-4 flex gap-2 overflow-x-auto kb-scroll -mx-1 px-1">
                                        <template x-for="(photo, i) in previewGallery" :key="i">
                                            <img :src="photo.url || photo" alt=""
                                                 class="w-[112px] h-[112px] shrink-0 rounded-xl object-cover bg-black/5">
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- 4. Past events — 180w cards in a 220h rail, 12px gaps --}}
                            <template x-if="previewPastEvents.length">
                                <div class="bg-white rounded-2xl p-4" style="box-shadow:0 1px 4px rgba(0,0,0,.05)">
                                    <div class="flex items-center gap-2">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFE28C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                        <span class="text-[14px] font-bold text-[#19150F]">{{ __('webapp.account.phone.past_events') }}</span>
                                        <span class="text-[11px] text-black/45" x-text="previewPastEvents.length"></span>
                                    </div>
                                    <div class="mt-4 h-[220px] flex gap-3 overflow-x-auto kb-scroll -mx-1 px-1">
                                        <template x-for="(event, i) in previewPastEvents" :key="i">
                                            <div class="w-[180px] shrink-0 rounded-2xl bg-white border border-black/[.06] overflow-hidden">
                                                <template x-if="previewEventCover(event)">
                                                    <img :src="previewEventCover(event)" alt="" class="w-full h-[120px] object-cover bg-black/5">
                                                </template>
                                                <template x-if="!previewEventCover(event)">
                                                    <div class="w-full h-[120px] bg-black/5"></div>
                                                </template>
                                                <div class="p-3">
                                                    <p class="text-[13px] font-semibold text-[#19150F] leading-tight line-clamp-2" x-text="event.name || '—'"></p>
                                                    <p class="mt-1 text-[11px] text-black/50" x-text="window.kbDateShort(event.date)"></p>
                                                    <p x-show="event.partner_name" x-cloak class="text-[11px] text-black/50 truncate" x-text="event.partner_name"></p>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- 5. Past collaborations --}}
                            <template x-if="previewCollaborations.length">
                                <div class="bg-white rounded-2xl p-4" style="box-shadow:0 1px 4px rgba(0,0,0,.05)">
                                    <div class="flex items-center gap-2">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFE28C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg>
                                        <span class="text-[14px] font-bold text-[#19150F]">{{ __('webapp.account.phone.collaborations') }}</span>
                                        <span class="text-[11px] text-black/45" x-text="previewCollaborations.length"></span>
                                    </div>
                                    <div class="mt-3 flex flex-col gap-2">
                                        <template x-for="(collab, i) in previewCollaborations.slice(0, 3)" :key="i">
                                            <p class="text-[12px] text-black/60 truncate" x-text="collab.title || collab.name || '—'"></p>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- 6. Social links --}}
                            <template x-if="previewSocials.length">
                                <div class="bg-white rounded-2xl p-4" style="box-shadow:0 1px 4px rgba(0,0,0,.05)">
                                    <div class="flex items-center gap-2">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFE28C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                        <span class="text-[14px] font-bold text-[#19150F]">{{ __('webapp.account.phone.links') }}</span>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <template x-for="link in previewSocials" :key="link">
                                            <span class="px-3 py-1.5 rounded-full bg-black/[.04] text-[11px] text-black/60 truncate max-w-full" x-text="link"></span>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <div class="h-6"></div>
                        </div>
                    </div>
                    </template>
                </div>
            </div>
        </div>

        <p class="mt-3 text-[11px] text-muted leading-relaxed max-w-[340px]">{{ __('webapp.account.phone.note') }}</p>
    </div>
</aside>
