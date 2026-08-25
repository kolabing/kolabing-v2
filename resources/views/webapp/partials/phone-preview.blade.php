{{--
    Phone-frame preview of the public profile as the Flutter app renders it.

    ⚠️ THIS FILE MIRRORS THE MOBILE APP. When any of these change, change this too:
        kolabing-app/lib/features/profile/screens/public_profile_screen.dart
            (_buildProfileContent, _ProfileSliverHeader, _SectionCard,
             _SocialLinkChip, _RecentReviewsSection, _PublicProfileReviewCard,
             _buildCollaborationsSection, _buildSocialLinksSection)
        kolabing-app/lib/features/profile/widgets/reputation_summary_card.dart
        kolabing-app/lib/features/profile/widgets/past_collaboration_card.dart
        kolabing-app/lib/widgets/gallery/public_gallery_section.dart
        kolabing-app/lib/widgets/cards/kolabing_cards.dart  (PrimaryContentCard, EmptyStateCard)
        kolabing-app/lib/features/event/widgets/past_events_section.dart
        kolabing-app/lib/features/event/widgets/event_card.dart

    It is a SECOND rendering of the profile UI, which is normally the wrong call —
    the web Preview tab renders the real page precisely to avoid drift. Here the
    target is a Flutter screen that cannot be embedded, so the cost is bounded
    instead: read-only (no lightbox, no pagination, no tap targets), one file, and
    the mirrored Dart files named above.

    ── Every value below comes from the Dart source, not from taste ──────────────
    Section order (public_profile_screen.dart):
        reputation → about → gallery → past events → past Kolabs → recent reviews
        → social links → 32px tail
    Spacing   xxxs 2 · xxs 4 · xs 8 · sm 12 · md 16 · lg 24 · xl 32
    Radius    xs 4 · sm 8 · md 12 · lg 16 · card 24 · round pill
    Type      titleMedium 20/700 lh28 (section titles) · titleSmall 14/600 lh1.4
              bodyMedium 16/400 lh24 · bodySmall 14/400 lh20 · captionSecondary 13/400
              labelSmall 11/500 ls .55
    Colours   primary #FFE28C · onPrimary/onSurface #19150F · background #FAF5EA
              surface #FFFFFF · surfaceVariant #F5EFE3 · onSurfaceVariant #3F3A32
              textTertiary #8C8A82 · hairline #EDE5D5 · success #56624D
              softYellow #FFF4C2 · secondary (text buttons) #615B71
    Shadows   section card   0 2px 10px  rgba(0,0,0,.05)
              design card    0 2px 8px rgba(25,21,15,.04), 0 8px 24px rgba(25,21,15,.024)
              collab card    0 4px 24px  rgba(28,28,22,.04)
              event card     0 2px 8px   rgba(0,0,0,.1)
    Header    180 tall · content inset 16/56/16/16 · avatar 64 · name 22/700 max 2 lines
    Gallery   112 thumbs, radius 12, 8 gaps
    Events    rail 220, gaps 12, cards 180 wide, full-bleed cover + overlay
    Kolabs    rail 110, gaps 12, cards 240 wide, radius 12, hairline

    Colours are written as literal hex, never `bg-white`/`text-ink`: those tokens are
    theme-aware in the panel, and the replica must keep showing the app's light theme
    whatever theme the panel is in.

    Three things here look wrong and are faithful — do not "fix" them:
      · The past-events count badge is primary text on primary/10, i.e. barely legible.
        The app does exactly that (past_events_section.dart).
      · "Gallery", "with {partner}", "{n} attendees" and "Completed" stay English in
        every locale, because the Dart hardcodes those strings.
      · The past-events rail can carry a future-dated event: the app calls
        GET /events?profile_id= with no time filter, and so does the preview.

    Usage: spread kbPhonePreview() into the page's x-data and call
    initPreview() from the page's init(); call refreshPreview() after any
    successful mutation so the phone is never stale.
--}}
@php
    // One definition each, so a card cannot drift from its siblings.
    $card = 'rounded-2xl bg-[#FFFFFF] p-4';
    $cardShadow = 'box-shadow:0 2px 10px rgba(0,0,0,.05)';
    $designShadow = 'box-shadow:0 2px 8px rgba(25,21,15,.04),0 8px 24px rgba(25,21,15,.024)';
    $title = 'text-[20px] font-bold leading-[28px] text-[#19150F]';
    $icon = 'shrink-0 text-[#FFE28C]';
@endphp
<aside class="hidden xl:block w-[360px] shrink-0" x-data>
    <div class="sticky top-8">
        <p class="text-[11px] font-semibold tracking-[.16em] uppercase text-muted mb-3">
            {{ __('webapp.account.phone.title') }}
        </p>

        {{-- ── Device frame ─────────────────────────────────────────────── --}}
        <div class="relative w-[340px] h-[700px] rounded-[44px] bg-ink p-[10px] shadow-cardhover">
            <div class="relative w-full h-full rounded-[35px] overflow-hidden bg-[#FFFFFF]">

                {{-- Notch --}}
                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[110px] h-[26px] bg-ink rounded-b-[14px] z-20"></div>

                <div class="w-full h-full overflow-y-auto kb-scroll font-sans" style="background:#FAF5EA">

                    <template x-if="previewLoading">
                        <div class="p-4 pt-12 flex flex-col gap-4">
                            <div class="h-[140px] rounded-2xl bg-black/5 animate-pulse"></div>
                            <div class="h-24 rounded-2xl bg-black/5 animate-pulse"></div>
                            <div class="h-24 rounded-2xl bg-black/5 animate-pulse"></div>
                        </div>
                    </template>

                    <template x-if="!previewLoading && previewError">
                        <div class="p-6 pt-16 text-center">
                            <p class="text-[14px] leading-[20px] text-[#3F3A32]" x-text="previewError"></p>
                        </div>
                    </template>

                    <template x-if="!previewLoading && !previewError && previewProfile">
                    <div>
                        {{-- ── Header: 180 tall, primary → primary/70 gradient ── --}}
                        <div class="h-[180px] px-4 pt-14 pb-4 flex items-center"
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
                                    <p class="text-[22px] font-bold leading-tight text-[#19150F] line-clamp-2"
                                       x-text="previewProfile.display_name"></p>
                                    {{-- type_label, not the raw `type` slug. --}}
                                    <p x-show="previewTypeLabel" x-cloak
                                       class="mt-0.5 text-[14px] font-medium leading-[20px] text-[#3F3A32]"
                                       x-text="previewTypeLabel"></p>
                                    <p x-show="previewProfile.city_name" x-cloak
                                       class="mt-0.5 flex items-center gap-1 text-[13px] text-[#3F3A32]">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
                                        <span class="truncate" x-text="previewProfile.city_name"></span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- ── Body: 16 padding, 16 between cards ────────────── --}}
                        <div class="p-4 flex flex-col gap-4">

                            {{-- 1. Reputation — ReputationSummaryCard.
                                 No reviews → EmptyStateCard. With reviews →
                                 PrimaryContentCard, which unlike the section cards
                                 carries a hairline border and the design shadow. --}}
                            <template x-if="!previewHasReviews">
                                <div class="rounded-2xl bg-[#FFFFFF] border border-[#EDE5D5] p-8 flex flex-col items-center"
                                     style="{{ $designShadow }}">
                                    <div class="w-14 h-14 rounded-full bg-[#FFF4C2] flex items-center justify-center shrink-0">
                                        <svg width="26" height="26" viewBox="0 0 24 24" fill="#19150F"><path d="m12 3.6 2.36 4.78 5.28.77-3.82 3.72.9 5.26L12 15.65l-4.72 2.48.9-5.26-3.82-3.72 5.28-.77z"/></svg>
                                    </div>
                                    <p class="mt-4 text-[18px] font-bold leading-[28px] text-[#19150F] text-center">{{ __('webapp.account.phone.reputation_empty_title') }}</p>
                                    <p class="mt-3 text-[14px] leading-[20px] text-[#3F3A32] text-center">{{ __('webapp.account.phone.reputation_empty_body') }}</p>
                                </div>
                            </template>

                            <template x-if="previewHasReviews">
                                <div class="rounded-2xl bg-[#FFFFFF] border border-[#EDE5D5] p-4 flex items-center justify-between gap-2"
                                     style="{{ $designShadow }}">
                                    <div class="flex items-center gap-1 shrink-0">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="#FFE28C"><path d="m12 3.6 2.36 4.78 5.28.77-3.82 3.72.9 5.26L12 15.65l-4.72 2.48.9-5.26-3.82-3.72 5.28-.77z"/></svg>
                                        <span class="text-[20px] font-bold leading-[28px] text-[#19150F]" x-text="previewRating"></span>
                                    </div>
                                    <span class="min-w-0 truncate text-[14px] leading-[20px] text-[#3F3A32]"
                                          x-text="previewCount('reviews', previewReviewCount)"></span>
                                    {{-- Both counts hide at zero: "0 partners" is noise on a fresh profile. --}}
                                    <span x-show="previewPartnerCount > 0" x-cloak
                                          class="min-w-0 truncate text-[14px] leading-[20px] text-[#3F3A32]"
                                          x-text="previewCount('partners', previewPartnerCount)"></span>
                                    <span x-show="previewCompletedCount > 0" x-cloak
                                          class="min-w-0 truncate text-[14px] leading-[20px] text-[#3F3A32]"
                                          x-text="previewCount('completed', previewCompletedCount)"></span>
                                </div>
                            </template>

                            {{-- 2. About --}}
                            <template x-if="previewProfile.about">
                                <div class="{{ $card }}" style="{{ $cardShadow }}">
                                    <div class="flex items-center gap-2">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="{{ $icon }}"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"/><path d="M14 2v5h5"/></svg>
                                        <span class="{{ $title }}">{{ __('webapp.account.phone.about') }}</span>
                                    </div>
                                    <p class="mt-4 text-[16px] text-[#3F3A32] whitespace-pre-line" style="line-height:1.5" x-text="previewProfile.about"></p>
                                </div>
                            </template>

                            {{-- 3. Gallery — 112 thumbs, 8 gaps. The count here is plain
                                 tertiary text, NOT the pill the other sections use. --}}
                            <template x-if="previewGallery.length">
                                <div class="{{ $card }}" style="{{ $cardShadow }}">
                                    <div class="flex items-center gap-2">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="{{ $icon }}"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.35-4.35a2 2 0 0 0-2.83 0L3 21"/></svg>
                                        <span class="{{ $title }}">{{ __('webapp.account.phone.gallery') }}</span>
                                        <span class="text-[14px] leading-[20px] text-[#8C8A82]" x-text="previewGallery.length"></span>
                                    </div>
                                    <div class="mt-4 flex gap-2 overflow-x-auto kb-scroll -mx-1 px-1">
                                        <template x-for="(photo, i) in previewGallery" :key="i">
                                            <div class="w-[112px] h-[112px] shrink-0 rounded-xl overflow-hidden bg-[#F5EFE3]">
                                                <template x-if="photo.url || (typeof photo === 'string' && photo)">
                                                    <img :src="photo.url || photo" alt="" class="w-full h-full object-cover">
                                                </template>
                                                <template x-if="!(photo.url || (typeof photo === 'string' && photo))">
                                                    <div class="w-full h-full flex items-center justify-center text-[#8C8A82]">
                                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="2" x2="22" y1="2" y2="22"/><path d="M10.41 10.41a2 2 0 1 1-2.83-2.83"/><line x1="13.5" x2="6" y1="13.5" y2="21"/><line x1="18" x2="21" y1="12" y2="15"/><path d="M3.59 3.59A1.99 1.99 0 0 0 3 5v14a2 2 0 0 0 2 2h14c.55 0 1.05-.22 1.41-.59"/><path d="M21 15V5a2 2 0 0 0-2-2H9"/></svg>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- 4. Past events — hidden entirely when the list is empty,
                                 exactly as _buildPublicView does. Rail 220, gaps 12. --}}
                            <template x-if="previewEvents.length">
                                <div class="{{ $card }}" style="{{ $cardShadow }}">
                                    <div class="flex items-center gap-2">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="{{ $icon }}"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                        <span class="{{ $title }}">{{ __('webapp.account.phone.past_events') }}</span>
                                        {{-- primary on primary/10 — near-invisible in the app too. --}}
                                        <span class="px-2 py-0.5 rounded-lg bg-[#FFE28C]/10 text-[11px] font-semibold tracking-[.55px] text-[#FFE28C]"
                                              x-text="previewEvents.length"></span>
                                    </div>
                                    <div class="mt-4 h-[220px] flex gap-3 overflow-x-auto kb-scroll -mx-1 px-1">
                                        <template x-for="(event, i) in previewEvents" :key="i">
                                            {{-- EventCard: 180 wide, full-bleed cover, text over a gradient. --}}
                                            <div class="relative w-[180px] h-full shrink-0 rounded-2xl overflow-hidden bg-[#F5EFE3]"
                                                 style="box-shadow:0 2px 8px rgba(0,0,0,.1)">
                                                <template x-if="previewEventCover(event)">
                                                    <img :src="previewEventCover(event)" alt="" class="absolute inset-0 w-full h-full object-cover">
                                                </template>
                                                <template x-if="!previewEventCover(event)">
                                                    <div class="absolute inset-0 flex items-center justify-center text-[#8C8A82]">
                                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.35-4.35a2 2 0 0 0-2.83 0L3 21"/></svg>
                                                    </div>
                                                </template>

                                                <div class="absolute inset-0" style="background:linear-gradient(to bottom, transparent 30%, rgba(0,0,0,.3) 60%, rgba(0,0,0,.8) 100%)"></div>

                                                {{-- Date badge, top right --}}
                                                <span class="absolute top-3 right-3 px-3 py-1 rounded-lg text-[10px] font-medium tracking-[.5px] text-white"
                                                      style="background:rgba(0,0,0,.6)"
                                                      x-text="previewDateBadge(event.date)"></span>

                                                {{-- Photo count, top left, only past one photo --}}
                                                <span x-show="previewEventPhotoCount(event) > 1" x-cloak
                                                      class="absolute top-3 left-3 px-2 py-0.5 rounded-lg flex items-center gap-0.5 text-[10px] font-medium text-white"
                                                      style="background:rgba(0,0,0,.6)">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.35-4.35a2 2 0 0 0-2.83 0L3 21"/></svg>
                                                    <span x-text="previewEventPhotoCount(event)"></span>
                                                </span>

                                                <div class="absolute left-3 right-3 bottom-3">
                                                    <p class="text-[14px] font-semibold text-white line-clamp-2" style="line-height:1.4" x-text="event.name || '—'"></p>

                                                    <div class="mt-1.5 flex items-center gap-1.5">
                                                        <div class="w-5 h-5 rounded-full bg-[#FFE28C] border border-white/50 flex items-center justify-center shrink-0 text-[10px] font-bold text-[#19150F]"
                                                             x-text="(event.partner_name || '').trim() ? (event.partner_name || '').trim()[0].toUpperCase() : '?'"></div>
                                                        <span class="text-[11px] text-white/90 truncate" x-text="event.partner_name || ''"></span>
                                                    </div>

                                                    <div class="mt-1.5 flex items-center gap-1 text-white/80">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg>
                                                        {{-- English in every locale: event_card.dart hardcodes it. --}}
                                                        <span class="text-[10px]" x-text="previewAttendeeCount(event) + ' attendees'"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- 5. Past Kolabs — always rendered, with its own empty state.
                                 The count is completed_kolabs_count, not the list length,
                                 and the list/empty branch keys off the LIST so a count
                                 without rows shows the empty state rather than a blank box. --}}
                            <div class="{{ $card }}" style="{{ $cardShadow }}">
                                <div class="flex items-center gap-2">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="{{ $icon }}"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
                                    <span class="{{ $title }}">{{ __('webapp.account.phone.past_kolabs') }}</span>
                                    <span x-show="previewKolabsCount > 0" x-cloak
                                          class="px-1.5 py-0.5 rounded-[10px] bg-[#FFE28C]/[.15] text-[12px] font-semibold text-[#19150F]"
                                          x-text="previewKolabsCount"></span>
                                </div>

                                <template x-if="!previewCollaborations.length">
                                    <div class="py-4 flex flex-col items-center">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-[#8C8A82]"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                        <p class="mt-2 text-[16px] leading-[24px] text-[#8C8A82] text-center">{{ __('webapp.account.phone.no_past_kolabs') }}</p>
                                    </div>
                                </template>

                                <template x-if="previewCollaborations.length">
                                    <div class="mt-4 h-[110px] flex gap-3 overflow-x-auto kb-scroll -mx-1 px-1">
                                        <template x-for="(collab, i) in previewCollaborations" :key="i">
                                            <div class="w-[240px] shrink-0 rounded-xl bg-[#FFFFFF] border border-[#EDE5D5] p-3"
                                                 style="box-shadow:0 4px 24px rgba(28,28,22,.04)">
                                                <p class="text-[14px] font-semibold text-[#19150F] truncate" style="line-height:1.4" x-text="collab.title || '—'"></p>

                                                <div class="mt-2 flex items-center gap-2">
                                                    <template x-if="collab.partner_avatar_url">
                                                        <img :src="collab.partner_avatar_url" alt="" class="w-6 h-6 rounded-full object-cover shrink-0">
                                                    </template>
                                                    <template x-if="!collab.partner_avatar_url">
                                                        <div class="w-6 h-6 rounded-full bg-[#FFE28C]/20 flex items-center justify-center shrink-0 text-[16px] font-semibold leading-none text-[#19150F]"
                                                             x-text="window.kbInitial(collab.partner_name)"></div>
                                                    </template>
                                                    {{-- English in every locale: past_collaboration_card.dart hardcodes it. --}}
                                                    <span class="min-w-0 truncate text-[14px] leading-[20px] text-[#3F3A32]"
                                                          x-text="'with ' + (collab.partner_name || '')"></span>
                                                </div>

                                                <div class="mt-2 flex items-center gap-1">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 text-[#8C8A82]"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                                    <span class="text-[12px] text-[#8C8A82]" x-text="previewCollabMonth(collab.completed_at)"></span>
                                                    <span class="ml-auto px-1.5 py-0.5 rounded bg-[#56624D]/[.15] flex items-center gap-[3px] shrink-0">
                                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#56624D" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
                                                        <span class="text-[10px] font-semibold text-[#56624D]">Completed</span>
                                                    </span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            {{-- 6. Recent reviews — at most 3 from the API. The "View more"
                                 button is inert here: the replica has no tap targets. --}}
                            <template x-if="previewReviews.length">
                                <div class="{{ $card }}" style="{{ $cardShadow }}">
                                    <div class="flex items-center gap-2">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="#FFE28C" class="shrink-0"><path d="m12 3.6 2.36 4.78 5.28.77-3.82 3.72.9 5.26L12 15.65l-4.72 2.48.9-5.26-3.82-3.72 5.28-.77z"/></svg>
                                        <span class="{{ $title }}">{{ __('webapp.account.phone.recent_reviews') }}</span>
                                        <span class="ml-auto text-[14px] font-semibold tracking-[.7px] text-[#615B71]">{{ __('webapp.account.phone.view_more') }}</span>
                                    </div>

                                    <div class="mt-4 flex flex-col gap-3">
                                        <template x-for="(review, i) in previewReviews" :key="i">
                                            <div class="rounded-xl bg-[#FAF5EA] border border-[#EDE5D5] p-3">
                                                <div class="flex items-center gap-3">
                                                    <template x-if="review.reviewer?.avatar_url">
                                                        <img :src="review.reviewer.avatar_url" alt="" class="w-9 h-9 rounded-full object-cover shrink-0">
                                                    </template>
                                                    <template x-if="!review.reviewer?.avatar_url">
                                                        <div class="w-9 h-9 rounded-full bg-[#FFE28C]/40 flex items-center justify-center shrink-0 text-[13px] font-bold text-[#19150F]"
                                                             x-text="window.kbInitial(review.reviewer?.display_name)"></div>
                                                    </template>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-[14px] font-semibold leading-[20px] text-[#19150F] truncate" x-text="review.reviewer?.display_name || '—'"></p>
                                                        <p class="text-[14px] leading-[20px] text-[#8C8A82]" x-text="previewReviewDate(review.created_at)"></p>
                                                    </div>
                                                    <div class="flex items-center shrink-0">
                                                        <template x-for="star in 5" :key="star">
                                                            <svg width="16" height="16" viewBox="0 0 24 24" stroke="#FFE28C" stroke-width="1.5"
                                                                 :fill="star <= Number(review.rating || 0) ? '#FFE28C' : 'none'"><path d="m12 3.6 2.36 4.78 5.28.77-3.82 3.72.9 5.26L12 15.65l-4.72 2.48.9-5.26-3.82-3.72 5.28-.77z"/></svg>
                                                        </template>
                                                    </div>
                                                </div>
                                                <p x-show="review.body" x-cloak
                                                   class="mt-3 text-[16px] text-[#3F3A32] whitespace-pre-line" style="line-height:1.45"
                                                   x-text="review.body"></p>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- 7. Social links --}}
                            <template x-if="previewHasSocials">
                                <div class="{{ $card }}" style="{{ $cardShadow }}">
                                    <div class="flex items-center gap-2">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="{{ $icon }}"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                        <span class="{{ $title }}">{{ __('webapp.account.phone.links') }}</span>
                                    </div>
                                    <div class="mt-4 flex flex-wrap gap-3">
                                        <template x-if="previewProfile.instagram">
                                            <span class="inline-flex items-center gap-1.5 max-w-full px-3 py-2 rounded-full bg-[#F5EFE3] border border-[#EDE5D5]">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FFE28C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/></svg>
                                                <span class="truncate text-[13px] font-medium text-[#19150F]" x-text="previewHandle(previewProfile.instagram)"></span>
                                            </span>
                                        </template>
                                        <template x-if="previewProfile.tiktok">
                                            <span class="inline-flex items-center gap-1.5 max-w-full px-3 py-2 rounded-full bg-[#F5EFE3] border border-[#EDE5D5]">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FFE28C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
                                                <span class="truncate text-[13px] font-medium text-[#19150F]" x-text="previewHandle(previewProfile.tiktok)"></span>
                                            </span>
                                        </template>
                                        <template x-if="previewProfile.website">
                                            <span class="inline-flex items-center gap-1.5 max-w-full px-3 py-2 rounded-full bg-[#F5EFE3] border border-[#EDE5D5]">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FFE28C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><circle cx="12" cy="12" r="10"/><line x1="2" x2="22" y1="12" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                                                <span class="truncate text-[13px] font-medium text-[#19150F]" x-text="previewProfile.website"></span>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- 32px tail, matching KolabingSpacing.xl --}}
                            <div class="h-8"></div>
                        </div>
                    </div>
                    </template>
                </div>
            </div>
        </div>

        <p class="mt-3 text-[11px] text-muted leading-relaxed max-w-[340px]">{{ __('webapp.account.phone.note') }}</p>
    </div>
</aside>
