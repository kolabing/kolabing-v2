{{-- Kolab detail drawer + apply modal + success sheet.
     Behaviour lives in window.kbModalMixin(); merge it into the page component:
     x-data="kbMerge(kbShell(), kbModalMixin(), somePage())" --}}

{{--
    ── Detail drawer ──────────────────────────────────────────────────────

    This used to be a centred modal. A modal is the wrong shape for this content:
    it says "deal with me and go back", while reading a Kolab is a browsing act —
    you skim one, then the next, then the next. So it is a drawer on the right, the
    list stays where it was, and the two arrow buttons walk that list in place —
    no close, no scroll-position loss, no round trip through the grid.

    Rendered once per page and driven entirely by `dk`, so Explore, the Kolabs list
    and the detail page all get the same panel.
--}}
<div x-show="dk || detailLoading" x-cloak @click="closeDetail()"
     x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
     x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
     x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="kb-overlay fixed inset-0 z-50"></div>

<aside x-show="dk || detailLoading" x-cloak
       @keydown.escape.window="closeDetail()"
       x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
       x-transition:enter-start="opacity-0 translate-x-6" x-transition:enter-end="opacity-100 translate-x-0"
       x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
       x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 translate-x-6"
       class="fixed inset-y-0 right-0 z-[55] w-full sm:w-[460px] lg:w-[520px] bg-cream sm:border-l border-ink/10 flex flex-col"
       style="box-shadow: -18px 0 46px rgba(0,0,0,.16);"
       role="dialog" aria-modal="true">

    {{-- ── Top bar: leave, share, walk the list ────────────────────────── --}}
    <header class="shrink-0 h-14 px-3 flex items-center gap-2 border-b border-ink/[.08] bg-cream/95">
        <button type="button" @click="closeDetail()"
                class="w-9 h-9 rounded-xl bg-cream-low hover:bg-cream-low-hover transition flex items-center justify-center text-body shrink-0"
                aria-label="{{ __('webapp.common.close') }}" title="{{ __('webapp.common.close') }}">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m13 17 5-5-5-5"/><path d="m6 17 5-5-5-5"/></svg>
        </button>

        <template x-if="dk">
            <button type="button" @click="copyLink()"
                    class="h-9 px-3 rounded-xl bg-cream-low hover:bg-cream-low-hover transition flex items-center gap-1.5 text-[12.5px] font-bold text-body shrink-0">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
                <span x-text="copied ? t('detail.link_copied') : t('detail.copy_link')"></span>
            </button>
        </template>

        <template x-if="dk">
            <a :href="kbPath('/kolabs/' + dk.id)"
               class="h-9 px-3 rounded-xl bg-cream-low hover:bg-cream-low-hover transition flex items-center gap-1.5 text-[12.5px] font-bold text-body shrink-0">
                <span>{{ __('webapp.detail.full_page') }}</span>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7"/><path d="M8 7h9v9"/></svg>
            </a>
        </template>

        {{-- Only where there is a list to walk: Explore and the Kolabs page, not the detail page. --}}
        <template x-if="neighbourIds.length > 1">
            <div class="ml-auto flex gap-1.5 shrink-0">
                <button type="button" @click="openNeighbour(-1)" :disabled="!hasNeighbour(-1)"
                        class="w-9 h-9 rounded-xl bg-cream-low hover:bg-cream-low-hover transition flex items-center justify-center text-body disabled:opacity-35 disabled:hover:bg-cream-low"
                        aria-label="{{ __('webapp.detail.previous') }}" title="{{ __('webapp.detail.previous') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
                </button>
                <button type="button" @click="openNeighbour(1)" :disabled="!hasNeighbour(1)"
                        class="w-9 h-9 rounded-xl bg-cream-low hover:bg-cream-low-hover transition flex items-center justify-center text-body disabled:opacity-35 disabled:hover:bg-cream-low"
                        aria-label="{{ __('webapp.detail.next') }}" title="{{ __('webapp.detail.next') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                </button>
            </div>
        </template>
    </header>

    <template x-if="detailLoading && !dk">
        <div class="flex-1 flex items-center justify-center text-muted text-sm">{{ __('webapp.common.loading') }}</div>
    </template>

    <template x-if="dk">
        <div class="flex-1 overflow-y-auto kb-scroll px-5 sm:px-7 py-6">

            {{-- Cover. Absent rather than a grey placeholder — an empty frame reads as broken. --}}
            <template x-if="dkCover">
                <img :src="dkCover" :alt="dk.title"
                     class="w-full rounded-2xl object-cover bg-cream-input" style="aspect-ratio: 16/10;">
            </template>

            <template x-if="dk.preferred_city">
                <div class="inline-flex items-center gap-2 mt-5 pl-1.5 pr-3 py-1.5 rounded-pill bg-white border border-ink/[.08]">
                    <span class="w-6 h-6 rounded-full bg-peach text-peach-ink flex items-center justify-center shrink-0">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    </span>
                    <span class="text-[12.5px] text-muted">{{ __('webapp.detail.in_city') }}
                        <span class="font-bold text-ink" x-text="dk.preferred_city"></span>
                    </span>
                </div>
            </template>

            <h2 class="font-anton text-[26px] sm:text-[30px] leading-[1.1] tracking-[.5px] text-ink mt-4" x-text="dk.title"></h2>

            {{-- Who is behind it. Business or community, the name links to the profile. --}}
            <div class="flex items-center gap-2.5 mt-3.5">
                <span class="w-7 h-7 rounded-full bg-primary/35 overflow-hidden flex items-center justify-center text-[12px] font-bold text-ink shrink-0">
                    <template x-if="dkAvatar"><img :src="dkAvatar" :alt="dkName" class="w-full h-full object-cover"></template>
                    <template x-if="!dkAvatar"><span x-text="initialOf(dkName)"></span></template>
                </span>
                <template x-if="dk?.creator_profile?.id">
                    <a :href="window.kbPath('/profiles/' + dk.creator_profile.id)"
                       class="text-[15px] font-bold text-ink hover:underline" x-text="dkName"></a>
                </template>
                <template x-if="!dk?.creator_profile?.id">
                    <span class="text-[15px] font-bold text-ink" x-text="dkName"></span>
                </template>
                <span class="px-2.5 py-[3px] rounded-pill bg-cream-low text-[11px] font-semibold text-body shrink-0" x-text="dkTypeLabel"></span>
            </div>

            {{-- ── When and where, the two facts people check first ────────── --}}
            <div class="mt-6 flex flex-col gap-3">
                <div class="flex items-center gap-3.5">
                    {{-- The tile shows the soonest day this can actually be booked. --}}
                    <div class="w-[52px] shrink-0 rounded-xl border border-ink/[.10] bg-white overflow-hidden text-center">
                        <p class="text-[9.5px] font-bold tracking-[.8px] uppercase text-white bg-ink py-[3px]" x-text="dkSoonest ? dkSoonest.month : t('detail.open')"></p>
                        <p class="text-[19px] font-bold text-ink leading-none py-1.5 tabular-nums" x-text="dkSoonest ? dkSoonest.day : '—'"></p>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[14.5px] font-bold text-ink" x-text="dkSoonest ? dkSoonest.long : t('feed.when_flexible')"></p>
                        <p class="text-[13px] text-muted mt-0.5" x-text="dkWindow || t('detail.dates_flexible')"></p>
                    </div>
                </div>

                <template x-if="dkCityLine">
                    <div class="flex items-center gap-3.5">
                        <span class="w-[52px] h-[52px] shrink-0 rounded-xl border border-ink/[.10] bg-white flex items-center justify-center text-body">
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        </span>
                        <div class="min-w-0">
                            <p class="text-[14.5px] font-bold text-ink truncate" x-text="dkCityLine"></p>
                            <p class="text-[13px] text-muted mt-0.5" x-text="dkGroup || t('detail.location_tbc')"></p>
                        </div>
                    </div>
                </template>
            </div>

            {{-- ── The action card ────────────────────────────────────────── --}}
            <div class="mt-6 rounded-2xl bg-white border border-ink/[.08] p-4 sm:p-5 shadow-card">
                <p class="text-[15px] font-bold text-ink" x-text="dkActionTitle"></p>
                <p class="text-[13px] text-body leading-relaxed mt-1" x-text="dkActionBody"></p>

                <template x-if="detailError">
                    <div class="mt-3.5 rounded-xl bg-bad-surface text-bad-ink text-[13px] px-3.5 py-2.5 whitespace-pre-line" x-text="detailError"></div>
                </template>

                <div class="flex gap-2 flex-wrap mt-4">
                    <template x-if="dkCta.kind === 'apply'">
                        <button type="button" @click="openApply()"
                                class="kb-on-yellow flex-1 min-w-[160px] h-12 rounded-pill bg-primary text-ink text-[14.5px] font-bold shadow-btn hover:bg-primary-dark hover:-translate-y-px transition"
                                x-text="dkCta.label"></button>
                    </template>
                    <template x-if="dkCta.kind === 'link'">
                        <a :href="dkCta.href"
                           class="kb-on-yellow flex-1 min-w-[160px] h-12 rounded-pill bg-primary text-ink text-[14.5px] font-bold shadow-btn hover:bg-primary-dark hover:-translate-y-px transition flex items-center justify-center"
                           x-text="dkCta.label"></a>
                    </template>
                    <template x-if="dkCta.kind === 'done'">
                        <div class="flex-1 min-w-[160px] h-12 rounded-pill bg-ok-surface text-ok-ink text-[14.5px] font-bold flex items-center justify-center" x-text="dkCta.label"></div>
                    </template>

                    {{-- Owner controls: edit always, publish only while it is still a draft. --}}
                    <template x-if="dk.is_own">
                        <a :href="kbPath('/kolabs/' + dk.id + '/edit')"
                           class="h-12 px-5 rounded-pill bg-white border border-line text-[14.5px] font-bold hover:border-ink transition flex items-center">{{ __('webapp.common.edit') }}</a>
                    </template>
                    <template x-if="dk.is_own && dk.status === 'draft'">
                        <button type="button" @click="publishKolab()" :disabled="applyBusy"
                                class="h-12 px-5 rounded-pill bg-inverse text-on-inverse text-[14.5px] font-bold hover:-translate-y-px transition disabled:opacity-50">{{ __('webapp.kolabs.publish') }}</button>
                    </template>
                    <template x-if="!dk.is_own">
                        <button type="button" @click="toggleDetailSave()"
                                class="h-12 px-5 rounded-pill bg-white border border-line text-[14.5px] font-bold hover:border-ink transition"
                                x-text="dk.is_saved ? t('detail.saved') : t('detail.save')"></button>
                    </template>
                </div>
            </div>

            {{-- ── What is actually on the table ──────────────────────────── --}}
            <template x-if="dkOffer">
                <div class="mt-7 bg-primary-tint border border-primary rounded-2xl p-4">
                    <p class="text-[11px] font-bold tracking-[1px] uppercase text-amber" x-text="dkOfferHead"></p>
                    <div class="flex gap-2 items-start mt-2">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-0.5"><path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><path d="M7 7h.01"/></svg>
                        <p class="text-sm font-semibold text-ink leading-snug" x-text="dkOffer"></p>
                    </div>
                </div>
            </template>

            <template x-if="dk.description">
                <div class="mt-7">
                    <p class="text-[11px] font-bold tracking-[1px] uppercase text-muted pb-2.5 border-b border-ink/[.08]">{{ __('webapp.detail.about') }}</p>
                    <p class="text-[14.5px] text-body leading-relaxed mt-3.5 whitespace-pre-line" x-text="dk.description"></p>
                </div>
            </template>

            {{-- Two lists, not one merged pile: what you get, and what is being asked of you. --}}
            <template x-if="dkGives.length || dkWants.length">
                <div class="mt-7">
                    <p class="text-[11px] font-bold tracking-[1px] uppercase text-muted pb-2.5 border-b border-ink/[.08]">{{ __('webapp.detail.the_deal') }}</p>
                    <div class="grid gap-3 mt-3.5 sm:grid-cols-2">
                        <template x-if="dkGives.length">
                            <div class="rounded-2xl bg-white border border-ink/[.08] p-4">
                                <p class="text-[11.5px] font-bold uppercase tracking-[.6px] text-muted">{{ __('webapp.detail.on_offer') }}</p>
                                <div class="flex gap-1.5 flex-wrap mt-2.5">
                                    <template x-for="ch in dkGives" :key="'g' + ch">
                                        <span class="px-2.5 py-1 rounded-pill bg-cream-low text-body text-[11.5px] font-semibold" x-text="ch"></span>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <template x-if="dkWants.length">
                            <div class="rounded-2xl bg-white border border-ink/[.08] p-4">
                                <p class="text-[11.5px] font-bold uppercase tracking-[.6px] text-muted">{{ __('webapp.detail.looking_for') }}</p>
                                <div class="flex gap-1.5 flex-wrap mt-2.5">
                                    <template x-for="ch in dkWants" :key="'w' + ch">
                                        <span class="px-2.5 py-1 rounded-pill bg-peach text-peach-ink text-[11.5px] font-semibold" x-text="ch"></span>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <template x-if="dkDayCells.length">
                <div class="mt-7">
                    <p class="text-[11px] font-bold tracking-[1px] uppercase text-muted pb-2.5 border-b border-ink/[.08]">{{ __('webapp.detail.available_days') }}</p>
                    <div class="flex gap-[7px] mt-3.5 flex-wrap">
                        <template x-for="dd in dkDayCells" :key="dd.l">
                            <div class="w-[38px] h-[38px] rounded-full flex items-center justify-center text-[11.5px] font-bold border"
                                 :class="dd.on ? 'bg-ink text-white border-ink' : 'bg-white text-faint border-ink/[.12]'"
                                 x-text="dd.l"></div>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Luma closes on the host; so does this, because the next step is talking to them. --}}
            <div class="mt-7 pb-2">
                <p class="text-[11px] font-bold tracking-[1px] uppercase text-muted pb-2.5 border-b border-ink/[.08]">{{ __('webapp.detail.posted_by') }}</p>
                <div class="flex items-center gap-3 mt-3.5 rounded-2xl bg-white border border-ink/[.08] p-3.5">
                    <span class="w-11 h-11 rounded-full bg-primary/35 overflow-hidden flex items-center justify-center text-base font-bold text-ink shrink-0">
                        <template x-if="dkAvatar"><img :src="dkAvatar" :alt="dkName" class="w-full h-full object-cover"></template>
                        <template x-if="!dkAvatar"><span x-text="initialOf(dkName)"></span></template>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-ink truncate" x-text="dkName"></p>
                        <p class="text-[12.5px] text-muted truncate" x-text="dkTypeLabel"></p>
                    </div>
                    <template x-if="dk?.creator_profile?.id">
                        <a :href="window.kbPath('/profiles/' + dk.creator_profile.id)"
                           class="h-9 px-4 rounded-pill bg-cream-low hover:bg-cream-low-hover transition text-[12.5px] font-bold text-body flex items-center shrink-0">{{ __('webapp.detail.view_profile') }}</a>
                    </template>
                </div>
            </div>
        </div>
    </template>
</aside>

{{-- ── Apply modal ───────────────────────────────────────────────────── --}}
<div x-show="applyOpen" x-cloak @click="applyOpen = false"
     class="kb-overlay fixed inset-0 z-[60] flex items-center justify-center p-4 sm:p-8">
    <div @click.stop class="bg-white rounded-[22px] w-full max-w-[520px] max-h-[88vh] flex flex-col overflow-hidden kb-fade-up-fast">
        <div class="flex-1 overflow-y-auto kb-scroll px-7 pt-[26px] pb-[18px]">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[19px] font-bold text-ink" x-text="applyCtaTitle"></p>
                    <p class="text-[13px] text-muted mt-[3px] truncate" x-text="dkName + ' · ' + (dk?.title || '')"></p>
                </div>
                <button type="button" @click="applyOpen = false" class="w-9 h-9 rounded-full bg-cream-low hover:bg-cream-low-hover transition flex items-center justify-center shrink-0" aria-label="{{ __('webapp.common.close') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>

            <template x-if="applyErr">
                <div class="mt-4 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="applyErr"></div>
            </template>

            <div style="margin-top:22px;">
                <p class="text-xs font-bold tracking-[.8px] uppercase text-body">{{ __('webapp.detail.when_available') }}</p>
                <p class="text-[12.5px] text-muted mt-[3px]">{{ __('webapp.detail.when_available_hint') }}</p>
                <div class="flex gap-2 flex-wrap mt-3">
                    <template x-for="dt in dateChips" :key="dt.value">
                        <button type="button" @click="toggleDate(dt.value)"
                                class="w-16 py-2.5 rounded-[14px] text-center border transition"
                                :class="applyDates.includes(dt.value) ? 'bg-primary-tint border-primary' : 'bg-white border-ink/[.12]'">
                            <span class="block text-[10.5px] font-semibold tracking-[.4px] text-muted" x-text="dt.top"></span>
                            <span class="block text-[13px] font-bold text-ink mt-px" x-text="dt.bot"></span>
                        </button>
                    </template>
                </div>
            </div>

            <div class="flex gap-3 mt-5">
                <div class="flex-1 flex flex-col gap-1.5">
                    <label class="text-xs font-semibold text-body">{{ __('webapp.detail.from') }}</label>
                    <select x-model="applyStart" class="h-12 rounded-2xl border border-transparent bg-cream-input px-3 text-sm text-ink cursor-pointer">
                        <template x-for="o in timeOptions" :key="'s' + o"><option :value="o" x-text="o"></option></template>
                    </select>
                </div>
                <div class="flex-1 flex flex-col gap-1.5">
                    <label class="text-xs font-semibold text-body">{{ __('webapp.detail.until') }}</label>
                    <select x-model="applyEnd" class="h-12 rounded-2xl border border-transparent bg-cream-input px-3 text-sm text-ink cursor-pointer">
                        <template x-for="o in timeOptions" :key="'e' + o"><option :value="o" x-text="o"></option></template>
                    </select>
                </div>
            </div>

            <div class="flex flex-col gap-1.5 mt-[18px]">
                <label class="text-xs font-semibold text-body" x-text="t('detail.message_to', { name: dkName })"></label>
                <textarea x-model="applyMsg" rows="4" maxlength="2000" placeholder="{{ __('webapp.detail.message_placeholder') }}"
                          class="rounded-2xl border border-transparent bg-cream-input px-4 py-3.5 text-sm text-ink leading-relaxed resize-y"></textarea>
            </div>

            <div class="flex flex-col gap-1.5 mt-3.5">
                <label class="text-xs font-semibold text-body">{{ __('webapp.detail.availability_notes') }} <span class="font-normal text-muted">({{ __('webapp.common.optional') }})</span></label>
                <input x-model="applyNotes" type="text" maxlength="200" placeholder="{{ __('webapp.detail.availability_notes_placeholder') }}"
                       class="h-12 rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
            </div>
        </div>
        <div class="px-7 pt-4 pb-5 border-t border-ink/[.08]">
            <button type="button" @click="submitApply()" :disabled="applyBusy"
                    class="kb-on-yellow w-full h-[52px] rounded-pill bg-primary text-ink text-[15px] font-bold shadow-btn hover:bg-primary-dark hover:-translate-y-px transition disabled:opacity-50">
                <span x-text="applyBusy ? t('detail.sending') : t('detail.send')">{{ __('webapp.detail.send') }}</span>
            </button>
        </div>
    </div>
</div>

{{-- ── Success sheet ─────────────────────────────────────────────────── --}}
<div x-show="applySuccess" x-cloak
     class="kb-overlay fixed inset-0 z-[70] flex items-center justify-center p-4 sm:p-8">
    <div class="bg-white rounded-[22px] w-full max-w-[400px] px-8 py-9 text-center kb-fade-up-fast">
        <div class="w-16 h-16 rounded-full bg-success-solid mx-auto flex items-center justify-center">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        </div>
        <p class="text-xl font-bold text-ink mt-[18px]">{{ __('webapp.detail.sent_title') }}</p>
        <p class="text-sm text-body leading-relaxed mt-2" x-text="t('detail.sent_body', { name: dkName })"></p>
        <a href="{{ $base }}/kolabs?tab=requests"
           class="kb-on-yellow w-full h-[50px] rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark transition flex items-center justify-center" style="margin-top:22px;">{{ __('webapp.detail.view_my_applications') }}</a>
        <button type="button" @click="closeSuccess()"
                class="w-full h-11 mt-2 rounded-pill bg-transparent text-muted hover:text-ink text-[13px] font-semibold transition">{{ __('webapp.detail.keep_exploring') }}</button>
    </div>
</div>

@push('scripts')
<script>
    /**
     * Detail + apply behaviour shared by Explore and the Kolab detail page.
     * Requires the host component to also carry kbShell() (for isBusiness/me).
     */
    window.kbModalMixin = function () {
        return {
            initialOf(name) { return window.kbInitial(name); },
            kbPath(p) { return window.kbPath(p); },

            /** Transient "Copied" state on the drawer's share button. */
            copied: false,

            // ── Drawer chrome ───────────────────────────────────────────
            /**
             * The ids the arrow buttons walk. Whatever page hosts the drawer exposes
             * its list as `cards`; the Kolab detail page has none, so the arrows are
             * simply absent there rather than dead.
             */
            get neighbourIds() { return Array.isArray(this.cards) ? this.cards.map(c => c.id) : []; },
            hasNeighbour(delta) {
                const at = this.neighbourIds.indexOf(this.dk?.id);
                return at !== -1 && this.neighbourIds[at + delta] !== undefined;
            },
            openNeighbour(delta) {
                const at = this.neighbourIds.indexOf(this.dk?.id);
                const next = at === -1 ? undefined : this.neighbourIds[at + delta];
                if (next) { this.detailError = ''; this.openDetail(next); }
            },
            /**
             * Share the link that works for the person receiving it.
             *
             * A published Kolab *can* have a page on the open web
             * (kolabing.com/kolabs/{id}), which opens for someone with no account — that
             * is the useful link when it exists. Three things have to be true for it to
             * exist, and all three are checked here:
             *
             *  - the Kolab is published (a draft has no public page, ROLES §4.3),
             *  - we know the marketing host,
             *  - and **the open-web marketplace is switched on at all**.
             *
             * That last one is the one this button got wrong (BE-FX-31).
             * `public_kolabs.enabled` is off in production while test listings are still
             * in the data (BE-FX-24), and off means the routes 404 — so for every
             * published Kolab this button copied a dead URL and the sharer had no way to
             * know. When the marketplace is off the in-app URL is the only honest link,
             * exactly as for a draft.
             */
            async copyLink() {
                const k = this.dk;
                if (!k) return;
                const marketing = String(window.KB_CONFIG?.marketingUrl || '').replace(/\/$/, '');
                const publicPageExists = k.status === 'published'
                    && !!marketing
                    && !!window.KB_CONFIG?.publicKolabs;
                const url = publicPageExists
                    ? marketing + '/kolabs/' + k.id
                    : location.origin + window.kbPath('/kolabs/' + k.id);
                try {
                    if (navigator.clipboard?.writeText) {
                        await navigator.clipboard.writeText(url);
                    } else {
                        // The Clipboard API needs a secure origin; this keeps the button
                        // honest when the panel is opened over plain http in development.
                        const field = document.createElement('textarea');
                        field.value = url;
                        field.setAttribute('readonly', '');
                        field.style.position = 'fixed';
                        field.style.opacity = '0';
                        document.body.appendChild(field);
                        field.select();
                        document.execCommand('copy');
                        field.remove();
                    }
                    this.copied = true;
                    setTimeout(() => { this.copied = false; }, 1800);
                } catch (error) {
                    this.detailError = t('detail.copy_failed');
                }
            },

            // ── Derived detail fields ───────────────────────────────────
            get dkName() { return this.dk?.creator_profile?.display_name || t('feed.a_partner'); },
            get dkAvatar() { return this.dk?.offer_photo || this.dk?.creator_profile?.avatar_url || ''; },
            get dkTypeLabel() {
                const k = this.dk; if (!k) return '';
                const c = k.creator_profile || {};
                const role = c.user_type === 'business' ? t('nav.role_business') : t('nav.role_community');
                const sub = window.kbHumanize(c.business_type || c.community_type) || window.kbIntentLabel(k.intent_type);
                return role + ' · ' + sub;
            },
            get dkOfferHead() {
                return this.dk?.intent_type === 'community_seeking' ? t('detail.what_they_bring') : t('detail.whats_on_offer');
            },
            get dkOffer() {
                const k = this.dk; if (!k) return '';
                if (k.intent_type === 'community_seeking') {
                    return (k.offers_in_return || []).map(window.kbHumanize).join(' · ');
                }
                return k.offer_headline || k.base_offer || '';
            },
            get dkCityLine() {
                const k = this.dk; if (!k) return '';
                return [k.area, k.preferred_city || k.venue_address].filter(Boolean).join(', ');
            },
            get dkWindow() {
                const k = this.dk; if (!k) return '';
                const from = window.kbDateShort(k.availability_start);
                const to = window.kbDateShort(k.availability_end);
                const range = from && to ? `${from} – ${to}` : (from || '');
                return [range, k.selected_time].filter(Boolean).join(' · ');
            },
            get dkGroup() {
                const k = this.dk; if (!k) return '';
                if (k.typical_attendance) return t('detail.group_people', { count: k.typical_attendance });
                if (k.min_community_size) return t('detail.group_min', { count: k.min_community_size });
                if (k.capacity) return t('detail.group_capacity', { count: k.capacity });
                return '';
            },
            get dkDayCells() {
                const k = this.dk;
                const days = Array.isArray(k?.recurring_days) ? k.recurring_days : [];
                if (!days.length) return [];
                const labels = t('detail.day_initials').split(',');
                return labels.map((l, i) => ({ l, on: days.includes(i + 1) }));
            },
            get dkChips() {
                const k = this.dk; if (!k) return [];
                const raw = [...(k.needs || []), ...(k.community_types || []), ...(k.offering || []), ...(k.seeking_communities || [])];
                return [...new Set(raw.filter(Boolean).map(window.kbHumanize))].slice(0, 6);
            },
            get dkCta() {
                const k = this.dk;
                if (!k) return { kind: 'none' };
                if (k.is_own) return { kind: 'link', label: t('detail.view_applications'), href: window.kbPath('/kolabs?tab=requests') };
                if (this.hasApplied) return { kind: 'done', label: t('detail.applied_short') };
                if (k.status !== 'published') return { kind: 'none' };
                return { kind: 'apply', label: this.isBusiness ? t('detail.send_proposal') : t('detail.apply') };
            },
            get applyCtaTitle() { return this.isBusiness ? t('detail.send_proposal') : t('detail.apply_title'); },
            get hasApplied() { return !!(this.appliedIds || []).includes(this.dk?.id); },

            /** The drawer's cover. Falls back through the media it might have, then nothing. */
            get dkCover() {
                const k = this.dk;
                if (!k) return '';
                return k.cover_photo_url || k.offer_photo || (k.media || [])[0]?.url || '';
            },
            /**
             * The soonest day this Kolab can actually be booked, for the date tile.
             * Same rule as the apply picker and the feed's rail — see kbNextDates().
             */
            get dkSoonest() {
                const next = window.kbNextDates(this.dk, 1)[0];
                if (!next) return null;
                const locale = window.KB_LOCALE || 'en';
                return {
                    month: next.date.toLocaleDateString(locale, { month: 'short' }),
                    day: String(next.date.getDate()),
                    long: next.date.toLocaleDateString(locale, { weekday: 'long', day: 'numeric', month: 'long' }),
                };
            },
            /**
             * What this Kolab gives, and what it asks for — two lists, not one merged
             * pile, because which side of the trade a chip sits on is the whole point.
             * Which column each field belongs to flips with the intent: a community
             * seeking a partner offers `offers_in_return` and needs `needs`; a venue or
             * product promotion offers `offering` and expects `expects`.
             */
            get dkGives() {
                const k = this.dk;
                if (!k) return [];
                return this.labelList(k.intent_type === 'community_seeking' ? k.offers_in_return : k.offering);
            },
            get dkWants() {
                const k = this.dk;
                if (!k) return [];
                return this.labelList(k.intent_type === 'community_seeking'
                    ? k.needs
                    : (k.expects ?? k.seeking_communities?.types));
            },
            /**
             * Two shapes exist in the wild for these columns: the list of slugs the API
             * validates and production stores (`["food_drink"]`), and an older
             * associative-boolean map (`{food_drink: true}`) that KolabFactory still
             * writes (BACKLOG BE-FX-25). Accept both; drop the falses either way.
             */
            labelList(raw) {
                let items = [];
                if (Array.isArray(raw)) {
                    items = raw;
                } else if (raw && typeof raw === 'object') {
                    items = Object.keys(raw).filter(key => !!raw[key]);
                }
                return [...new Set(items
                    .filter(v => typeof v === 'string' && v.trim() !== '')
                    .map(window.kbHumanize))].slice(0, 8);
            },
            /** The action card says where the viewer stands before it offers a button. */
            get dkActionTitle() {
                const k = this.dk;
                if (!k) return '';
                if (k.is_own) return t('detail.your_kolab');
                if (this.hasApplied) return t('detail.applied_title');
                if (k.status !== 'published') return t('detail.not_open');
                return this.applyCtaTitle;
            },
            get dkActionBody() {
                const k = this.dk;
                if (!k) return '';
                if (k.is_own) return t(k.status === 'draft' ? 'detail.your_kolab_draft' : 'detail.your_kolab_live');
                if (this.hasApplied) return t('detail.applied_body');
                if (k.status !== 'published') return t('detail.not_open_body');
                return t('detail.apply_card_body');
            },

            // ── Detail lifecycle ────────────────────────────────────────
            async openDetail(id) {
                this.detailLoading = true; this.detailError = ''; this.copied = false;
                this.lockScroll(true);
                const res = await window.kb.api('/kolabs/' + id);
                this.detailLoading = false;
                if (!res.ok) {
                    this.detailError = window.kb.errorText(res, t('detail.load_error'));
                    this.lockScroll(false);
                    return;
                }
                this.dk = res.json?.data || null;
            },
            closeDetail() { this.dk = null; this.detailError = ''; this.lockScroll(false); },
            /**
             * The drawer is full-height, so the list behind it must hold still — a page
             * that scrolls under an open panel loses the reader's place in the feed.
             * Only the drawer touches this; the apply modal opens on top of it and must
             * not release the lock when it closes.
             */
            lockScroll(on) { document.body.style.overflow = on ? 'hidden' : ''; },

            /**
             * Seed the already-applied set. Without this the detail sheet shows the
             * primary "Apply" CTA for a Kolab the viewer applied to days ago, and the
             * one-application-per-Kolab constraint only rejects it after they have
             * picked dates and written a message.
             */
            async loadAppliedIds() {
                const res = await window.kb.api('/me/applications?per_page=100');
                if (res.ok) {
                    this.appliedIds = window.kb.rows(res)
                        .map(a => a.kolab_id || a.collab_opportunity_id)
                        .filter(Boolean);
                }
            },

            async publishKolab() {
                this.detailError = ''; this.applyBusy = true;
                const res = await window.kb.api('/kolabs/' + this.dk.id + '/publish', { method: 'POST' });
                this.applyBusy = false;
                if (res.status === 402) { window.nav('/subscription?reason=publish'); return; }
                if (res.ok) { this.dk = res.json?.data || { ...this.dk, status: 'published' }; return; }
                this.detailError = window.kb.errorText(res, t('kolabs.publish_error'));
            },
            async toggleDetailSave() {
                const saved = !!this.dk.is_saved;
                const res = await window.kb.api('/kolabs/' + this.dk.id + '/save', { method: saved ? 'DELETE' : 'POST' });
                if (res.ok || res.status === 204) this.dk.is_saved = !saved;
            },

            // ── Apply lifecycle ─────────────────────────────────────────
            /*
             * The bookable-dates rule lives in window.kbNextDates() — shared with the
             * feed's date rail, which groups Kolabs by the soonest of these. Two copies
             * of "tomorrow, ISO weekdays, empty means any day" would drift.
             */
            get dateChips() { return window.kbNextDates(this.dk, 8); },
            openApply() {
                this.applyOpen = true; this.applyErr = '';
                this.applyDates = []; this.applyMsg = ''; this.applyNotes = '';
            },
            toggleDate(v) {
                this.applyErr = '';
                this.applyDates = this.applyDates.includes(v)
                    ? this.applyDates.filter(d => d !== v)
                    : [...this.applyDates, v];
            },
            /** The API stores availability as one free-text string (min 20 chars). */
            buildAvailability() {
                const labels = this.dateChips.filter(d => this.applyDates.includes(d.value)).map(d => `${d.top} ${d.bot}`);
                const parts = [t('detail.available_on', { dates: labels.join(', ') }), `${this.applyStart}–${this.applyEnd}`];
                if (this.applyNotes.trim()) parts.push(this.applyNotes.trim());
                return parts.join(' · ').slice(0, 500);
            },
            async submitApply() {
                this.applyErr = '';
                if (this.applyDates.length === 0) { this.applyErr = t('detail.pick_a_date'); return; }
                if (!this.applyMsg.trim()) { this.applyErr = t('detail.message_required'); return; }

                this.applyBusy = true;
                const res = await window.kb.api('/kolabs/' + this.dk.id + '/applications', {
                    method: 'POST',
                    body: { message: this.applyMsg.trim(), availability: this.buildAvailability() },
                });
                this.applyBusy = false;

                if (res.ok) {
                    this.appliedIds = [...(this.appliedIds || []), this.dk.id];
                    this.applyOpen = false;
                    this.applySuccess = true;
                    return;
                }
                // Applying is subscription-gated for businesses (402 from the service,
                // 403 when the policy denies it) — send them to the plan either way.
                if (res.status === 402 || (res.status === 403 && this.needsPlan)) { window.nav('/subscription?reason=apply'); return; }
                this.applyErr = window.kb.errorText(res, t('detail.apply_error'));
            },
            closeSuccess() { this.applySuccess = false; this.dk = null; this.lockScroll(false); },
        };
    };
</script>
@endpush
