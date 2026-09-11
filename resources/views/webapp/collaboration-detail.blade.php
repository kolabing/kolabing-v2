@extends('webapp.layout')
@section('title', __('webapp.collab.title'))

@section('body')
{{-- One collaboration, from "agreed" to "reviewed".

     The panel had a list of collaborations and nothing behind it, so a web-only user
     could accept an application and then never touch the thing again: no start, no
     completion confirmation, no review — even though the dashboard itself was already
     telling businesses to "Leave your review". This page is the missing half.

     Everything here is driven by the server's own answer, never by guessing from the
     status: `actions.can_*`, `viewer_must_confirm_completion`, `has_reviewed`,
     `viewer_must_submit_feedback` and `viewer_must_resubscribe` all come off
     CollaborationResource, so the page cannot offer a button the API would refuse. --}}
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), collaborationPage())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'kolabs'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[760px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-24 kb-fade-up">

        <a :href="window.kbPath('/kolabs') + '?tab=' + (isFinished ? 'finished' : 'active')"
           class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-muted hover:text-ink transition">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            {{ __('webapp.collab.back') }}
        </a>

        <template x-if="pageError">
            <div class="mt-5 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="pageError"></div>
        </template>
        <template x-if="loading">
            <p class="mt-6 text-muted">{{ __('webapp.common.loading') }}</p>
        </template>

        {{-- ROLES §2.8: a business whose plan lapsed loses its ongoing collaborations
             until it resubscribes. One-sided — the community counterparty never sees
             this, and a *completed* collab is not "ongoing", so the review and the
             feedback stay reachable after a lapse. --}}
        <template x-if="!loading && c && c.viewer_must_resubscribe">
            <section class="mt-6 rounded-[22px] border border-warn-ink/25 bg-warn-surface p-7 text-center">
                <p class="font-anton text-[22px] tracking-[.6px] text-ink">{{ __('webapp.collab.regate_title') }}</p>
                <p class="mt-2 text-[13.5px] text-body max-w-[46ch] mx-auto">{{ __('webapp.collab.regate_body') }}</p>
                <a :href="window.kbPath('/subscription')"
                   class="kb-on-yellow inline-flex mt-5 h-11 px-6 items-center rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark transition">{{ __('webapp.collab.regate_cta') }}</a>
            </section>
        </template>

        <template x-if="!loading && c && !c.viewer_must_resubscribe">
            <div>
                {{-- ── Who and what ─────────────────────────────────────────── --}}
                <div class="mt-4 flex items-start gap-4">
                    <div class="w-12 h-12 rounded-full bg-primary/40 flex items-center justify-center text-[17px] font-semibold text-ink shrink-0 overflow-hidden">
                        <template x-if="partner.avatar">
                            <img :src="partner.avatar" alt="" class="w-full h-full object-cover">
                        </template>
                        <template x-if="!partner.avatar">
                            <span x-text="initialOf(partner.name)"></span>
                        </template>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[11.5px] font-semibold tracking-[1px] uppercase text-muted" x-text="myRoleLabel"></p>
                        <h1 class="mt-1 font-anton text-[26px] leading-[1.1] tracking-[.6px] text-ink" x-text="title"></h1>
                        <p class="mt-1.5 text-[13.5px] text-body">
                            {{ __('webapp.collab.with') }}
                            <template x-if="partner.id">
                                <a :href="window.kbPath('/profiles/' + partner.id)" class="font-semibold text-ink hover:underline" x-text="partner.name"></a>
                            </template>
                            <template x-if="!partner.id">
                                <span class="font-semibold text-ink" x-text="partner.name"></span>
                            </template>
                        </p>
                    </div>
                    <span class="px-3 py-1 rounded-xl text-[11px] font-bold tracking-[.4px] shrink-0"
                          :style="`background:${statusPill(c.status).bg};color:${statusPill(c.status).c}`"
                          x-text="statusPill(c.status).label"></span>
                </div>

                {{-- ── Where it is ──────────────────────────────────────────── --}}
                <template x-if="c.status !== 'cancelled'">
                    <ol class="mt-7 flex items-center gap-2">
                        <template x-for="(s, i) in stages" :key="s.key">
                            <li class="flex-1 flex items-center gap-2">
                                <div class="flex-1">
                                    <div class="h-1.5 rounded-pill" :class="i <= stageIndex ? 'bg-ink' : 'bg-ink/12'"></div>
                                    <p class="mt-2 text-[11.5px] font-semibold tracking-[.4px]"
                                       :class="i <= stageIndex ? 'text-ink' : 'text-muted'" x-text="s.label"></p>
                                </div>
                            </li>
                        </template>
                    </ol>
                </template>

                {{-- ── Facts ────────────────────────────────────────────────── --}}
                <dl class="mt-7 grid gap-2.5 sm:grid-cols-2">
                    <div class="rounded-2xl border border-ink/[.08] bg-white p-4">
                        <dt class="text-[11px] font-semibold tracking-[.8px] uppercase text-muted">{{ __('webapp.collab.date') }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-ink" x-text="c.scheduled_date ? fmtDate(c.scheduled_date) : t('collab.no_date')"></dd>
                    </div>
                    <div class="rounded-2xl border border-ink/[.08] bg-white p-4">
                        <dt class="text-[11px] font-semibold tracking-[.8px] uppercase text-muted">{{ __('webapp.collab.talk') }}</dt>
                        <dd class="mt-1">
                            <a :href="window.kbPath('/chats') + '?collaboration=' + c.id"
                               class="text-sm font-semibold text-ink hover:underline">{{ __('webapp.collab.open_chat') }}</a>
                        </dd>
                    </div>
                </dl>

                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-[12.5px]">
                    <template x-if="kolabId">
                        <a :href="window.kbPath('/kolabs/' + kolabId)" class="font-semibold text-muted hover:text-ink transition">{{ __('webapp.collab.view_kolab') }}</a>
                    </template>
                    {{-- The door only appears once a happening exists. Creating one is the
                         event side's job, not this page's. --}}
                    <template x-if="c.event_id">
                        <a :href="window.kbPath('/events/' + c.event_id)" class="font-semibold text-muted hover:text-ink transition">{{ __('webapp.collab.door') }}</a>
                    </template>
                </div>

                <template x-if="actionError">
                    <div class="mt-5 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="actionError"></div>
                </template>

                {{-- ── Cancelled: nothing more happens ──────────────────────── --}}
                <template x-if="c.status === 'cancelled'">
                    <section class="mt-6 rounded-[22px] border border-ink/[.08] bg-white p-6 text-center">
                        <p class="font-bold text-[16px] text-ink">{{ __('webapp.collab.cancelled_title') }}</p>
                        <p class="mt-1.5 text-[13px] text-body max-w-[40ch] mx-auto">{{ __('webapp.collab.cancelled_body') }}</p>
                    </section>
                </template>

                {{-- ── Scheduled: start it ──────────────────────────────────── --}}
                <template x-if="c.status === 'scheduled'">
                    <section class="mt-6 rounded-[22px] border border-ink/[.08] bg-white p-6">
                        <p class="font-bold text-[16px] text-ink">{{ __('webapp.collab.start_title') }}</p>
                        <p class="mt-1.5 text-[13px] text-body max-w-[46ch]">{{ __('webapp.collab.start_body') }}</p>
                        <div class="mt-5 flex flex-wrap items-center gap-3">
                            <button type="button" x-show="canActivate" x-cloak @click="activate()" :disabled="busy"
                                    class="kb-on-yellow h-12 px-7 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark transition disabled:opacity-50"
                                    x-text="busy ? t('common.saving') : t('collab.start_cta')"></button>
                            <button type="button" x-show="canCancel" x-cloak @click="openCancel()"
                                    class="h-11 px-5 rounded-pill bg-white border border-line text-[12.5px] font-bold text-danger hover:border-danger transition">{{ __('webapp.collab.cancel_cta') }}</button>
                        </div>
                    </section>
                </template>

                {{-- ── Active: the confirmation, then the finish ─────────────── --}}
                <template x-if="c.status === 'active'">
                    <section class="mt-6 rounded-[22px] border border-ink/[.08] bg-white p-6">
                        <p class="font-bold text-[16px] text-ink">{{ __('webapp.collab.confirm_title') }}</p>
                        <p class="mt-1.5 text-[13px] text-body max-w-[52ch]">{{ __('webapp.collab.confirm_body') }}</p>

                        {{-- Both answers, side by side. Seeing the partner's answer is what
                             makes the gate legible instead of a mysterious 422. --}}
                        <div class="mt-5 flex flex-wrap gap-2">
                            <span class="px-3 py-1.5 rounded-pill bg-cream-input text-[12px] font-semibold text-body"
                                  x-text="ownAnswer ? t('collab.confirm_saved', { answer: answerLabel(ownAnswer) }) : t('collab.confirm_none')"></span>
                            <span class="px-3 py-1.5 rounded-pill bg-cream-input text-[12px] font-semibold text-body"
                                  x-text="partnerAnswer ? t('collab.partner_answered', { answer: answerLabel(partnerAnswer) }) : t('collab.partner_waiting')"></span>
                        </div>

                        <div class="mt-5 flex flex-wrap gap-2.5">
                            <template x-for="opt in answerOptions" :key="opt.value">
                                <button type="button" @click="confirmCompletion(opt.value)" :disabled="busy"
                                        class="h-10 px-4 rounded-pill border text-[12.5px] font-bold transition disabled:opacity-50"
                                        :class="ownAnswer === opt.value ? 'kb-on-yellow bg-primary border-primary text-ink' : 'bg-white border-line text-ink hover:border-ink'"
                                        x-text="opt.label"></button>
                            </template>
                        </div>

                        <label class="block mt-4">
                            <span class="text-[12px] font-semibold text-muted">{{ __('webapp.collab.confirm_note') }}</span>
                            <textarea x-model="note" rows="2" maxlength="500"
                                      class="mt-1.5 w-full rounded-2xl border border-line bg-cream-input px-4 py-3 text-sm text-ink placeholder:text-muted focus:border-ink outline-none"
                                      :placeholder="t('collab.confirm_note_ph')"></textarea>
                        </label>

                        <div class="mt-5 pt-5 border-t border-ink/[.08] flex flex-wrap items-center gap-3">
                            <button type="button" x-show="canComplete" x-cloak @click="complete()" :disabled="busy"
                                    class="kb-on-yellow h-12 px-7 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark transition disabled:opacity-50"
                                    x-text="busy ? t('common.saving') : t('collab.finish_cta')"></button>
                            <button type="button" x-show="canCancel" x-cloak @click="openCancel()"
                                    class="h-11 px-5 rounded-pill bg-white border border-line text-[12.5px] font-bold text-danger hover:border-danger transition">{{ __('webapp.collab.cancel_cta') }}</button>
                        </div>
                    </section>
                </template>

                {{-- ── Completed: the review, then the private numbers ───────── --}}
                <template x-if="c.status === 'completed'">
                    <div class="mt-6 flex flex-col gap-4">
                        <section class="rounded-[22px] border border-ink/[.08] bg-white p-6">
                            <template x-if="!c.has_reviewed">
                                <div>
                                    <p class="font-bold text-[16px] text-ink">{{ __('webapp.collab.review_title') }}</p>
                                    <p class="mt-1.5 text-[13px] text-body max-w-[50ch]">{{ __('webapp.collab.review_body') }}</p>
                                    <button type="button" @click="reviewOpen = true"
                                            class="kb-on-yellow mt-5 h-12 px-7 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark transition">{{ __('webapp.collab.review_cta') }}</button>
                                </div>
                            </template>
                            <template x-if="c.has_reviewed">
                                <div class="flex items-start gap-3">
                                    <div class="w-9 h-9 rounded-full bg-ok-surface text-ok-ink flex items-center justify-center shrink-0">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                    </div>
                                    <div>
                                        <p class="font-bold text-[15px] text-ink">{{ __('webapp.collab.reviewed_title') }}</p>
                                        <p class="mt-0.5 text-[13px] text-body">{{ __('webapp.collab.reviewed_body') }}</p>
                                    </div>
                                </div>
                            </template>
                        </section>

                        <section class="rounded-[22px] border border-ink/[.08] bg-white p-6">
                            <p class="font-bold text-[16px] text-ink">{{ __('webapp.collab.impact_title') }}</p>
                            <p class="mt-1.5 text-[13px] text-body max-w-[50ch]">{{ __('webapp.collab.impact_body') }}</p>

                            <template x-if="!c.own_feedback">
                                <button type="button" @click="openFeedback()"
                                        class="mt-5 h-11 px-5 rounded-pill bg-white border border-line text-[12.5px] font-bold text-ink hover:border-ink transition">{{ __('webapp.collab.impact_cta') }}</button>
                            </template>
                            {{-- Editing is allowed only until the partner submits; after that the
                                 API locks both rows, so the button would be a lie. --}}
                            <template x-if="c.own_feedback && !c.partner_feedback">
                                <button type="button" @click="openFeedback()"
                                        class="mt-5 h-11 px-5 rounded-pill bg-white border border-line text-[12.5px] font-bold text-ink hover:border-ink transition">{{ __('webapp.collab.impact_edit') }}</button>
                            </template>
                            <template x-if="c.own_feedback && c.partner_feedback">
                                <p class="mt-4 text-[12.5px] font-semibold text-muted">{{ __('webapp.collab.impact_locked') }}</p>
                            </template>
                        </section>
                    </div>
                </template>
            </div>
        </template>
    </div>
    </main>

    {{-- ── Cancel ───────────────────────────────────────────────────────────── --}}
    <div x-show="cancelOpen" x-cloak class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center p-0 sm:p-6">
        <div class="absolute inset-0 bg-ink/45" @click="cancelOpen = false"></div>
        <div class="relative w-full sm:max-w-[460px] bg-white rounded-t-[26px] sm:rounded-[26px] p-6 shadow-xl"
             role="dialog" aria-modal="true">
            <p class="font-anton text-[20px] tracking-[.6px] text-ink">{{ __('webapp.collab.cancel_title') }}</p>
            <p class="mt-2 text-[13px] text-body">{{ __('webapp.collab.cancel_body') }}</p>
            <label class="block mt-4">
                <span class="text-[12px] font-semibold text-muted">{{ __('webapp.collab.cancel_reason') }}</span>
                <textarea x-model="cancelReason" rows="3" maxlength="500"
                          class="mt-1.5 w-full rounded-2xl border border-line bg-cream-input px-4 py-3 text-sm text-ink focus:border-ink outline-none"></textarea>
            </label>
            <p class="mt-1.5 text-[11.5px] text-muted" x-text="t('collab.cancel_reason_hint', { n: Math.max(0, 20 - cancelReason.trim().length) })"></p>
            <div class="mt-5 flex gap-2.5">
                <button type="button" @click="cancelOpen = false"
                        class="flex-1 h-11 rounded-pill bg-white border border-line text-[13px] font-bold text-ink hover:border-ink transition">{{ __('webapp.collab.keep') }}</button>
                <button type="button" @click="cancel()" :disabled="busy || cancelReason.trim().length < 20"
                        class="flex-1 h-11 rounded-pill bg-danger text-white text-[13px] font-bold transition disabled:opacity-40"
                        x-text="busy ? t('common.saving') : t('collab.cancel_confirm')"></button>
            </div>
        </div>
    </div>

    {{-- ── Review ───────────────────────────────────────────────────────────── --}}
    <div x-show="reviewOpen" x-cloak class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center p-0 sm:p-6">
        <div class="absolute inset-0 bg-ink/45" @click="reviewOpen = false"></div>
        <div class="relative w-full sm:max-w-[500px] max-h-[92vh] overflow-y-auto bg-white rounded-t-[26px] sm:rounded-[26px] p-6 shadow-xl"
             role="dialog" aria-modal="true">
            <p class="font-anton text-[20px] tracking-[.6px] text-ink">{{ __('webapp.collab.review_title') }}</p>
            <p class="mt-2 text-[13px] text-body" x-text="t('collab.review_of', { name: partner.name })"></p>

            <div class="mt-5 flex flex-col gap-3.5">
                <template x-for="row in ratingRows" :key="row.key">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-[13px] font-semibold text-ink" x-text="row.label"></span>
                        <div class="flex gap-1">
                            <template x-for="n in 5" :key="n">
                                <button type="button" @click="review[row.key] = n"
                                        class="w-8 h-8 rounded-lg flex items-center justify-center transition"
                                        :class="review[row.key] >= n ? 'bg-primary text-ink' : 'bg-cream-input text-muted hover:bg-ink/10'"
                                        :aria-label="row.label + ' ' + n">
                                    <svg width="15" height="15" viewBox="0 0 24 24" :fill="review[row.key] >= n ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <label class="block mt-5">
                <span class="text-[12px] font-semibold text-muted">{{ __('webapp.collab.review_comment') }}</span>
                <textarea x-model="review.public_comment" rows="3" maxlength="2000"
                          class="mt-1.5 w-full rounded-2xl border border-line bg-cream-input px-4 py-3 text-sm text-ink focus:border-ink outline-none"></textarea>
            </label>

            <div class="mt-4">
                <span class="text-[12px] font-semibold text-muted">{{ __('webapp.collab.review_again') }}</span>
                <div class="mt-2 flex gap-2">
                    <button type="button" @click="review.would_collaborate_again = true"
                            class="h-10 px-5 rounded-pill border text-[12.5px] font-bold transition"
                            :class="review.would_collaborate_again === true ? 'kb-on-yellow bg-primary border-primary text-ink' : 'bg-white border-line text-ink hover:border-ink'">{{ __('webapp.collab.yes') }}</button>
                    <button type="button" @click="review.would_collaborate_again = false"
                            class="h-10 px-5 rounded-pill border text-[12.5px] font-bold transition"
                            :class="review.would_collaborate_again === false ? 'bg-ink border-ink text-white' : 'bg-white border-line text-ink hover:border-ink'">{{ __('webapp.collab.no') }}</button>
                </div>
            </div>

            <template x-if="modalError">
                <p class="mt-4 text-[12.5px] text-bad-ink whitespace-pre-line" x-text="modalError"></p>
            </template>

            <div class="mt-5 flex gap-2.5">
                <button type="button" @click="reviewOpen = false"
                        class="flex-1 h-11 rounded-pill bg-white border border-line text-[13px] font-bold text-ink hover:border-ink transition">{{ __('webapp.common.cancel') }}</button>
                <button type="button" @click="submitReview()" :disabled="busy || !reviewComplete"
                        class="kb-on-yellow flex-1 h-11 rounded-pill bg-primary text-ink text-[13px] font-bold shadow-btn hover:bg-primary-dark transition disabled:opacity-40"
                        x-text="busy ? t('common.saving') : t('collab.submit_review')"></button>
            </div>
        </div>
    </div>

    {{-- ── Private impact numbers ───────────────────────────────────────────── --}}
    <div x-show="feedbackOpen" x-cloak class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center p-0 sm:p-6">
        <div class="absolute inset-0 bg-ink/45" @click="feedbackOpen = false"></div>
        <div class="relative w-full sm:max-w-[500px] max-h-[92vh] overflow-y-auto bg-white rounded-t-[26px] sm:rounded-[26px] p-6 shadow-xl"
             role="dialog" aria-modal="true">
            <p class="font-anton text-[20px] tracking-[.6px] text-ink">{{ __('webapp.collab.impact_title') }}</p>
            <p class="mt-2 text-[13px] text-body">{{ __('webapp.collab.impact_body') }}</p>

            <div class="mt-5 flex items-center justify-between gap-3">
                <span class="text-[13px] font-semibold text-ink">{{ __('webapp.collab.f_rating') }}</span>
                <div class="flex gap-1">
                    <template x-for="n in 5" :key="n">
                        <button type="button" @click="feedback.rating = n"
                                class="w-8 h-8 rounded-lg flex items-center justify-center transition"
                                :class="feedback.rating >= n ? 'bg-primary text-ink' : 'bg-cream-input text-muted hover:bg-ink/10'"
                                :aria-label="'{{ __('webapp.collab.f_rating') }} ' + n">
                            <svg width="15" height="15" viewBox="0 0 24 24" :fill="feedback.rating >= n ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        </button>
                    </template>
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-3">
                <template x-for="q in yesNoRows" :key="q.key">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-[13px] font-semibold text-ink" x-text="q.label"></span>
                        <div class="flex gap-2">
                            <button type="button" @click="feedback[q.key] = true"
                                    class="h-9 px-4 rounded-pill border text-[12px] font-bold transition"
                                    :class="feedback[q.key] === true ? 'kb-on-yellow bg-primary border-primary text-ink' : 'bg-white border-line text-ink hover:border-ink'">{{ __('webapp.collab.yes') }}</button>
                            <button type="button" @click="feedback[q.key] = false"
                                    class="h-9 px-4 rounded-pill border text-[12px] font-bold transition"
                                    :class="feedback[q.key] === false ? 'bg-ink border-ink text-white' : 'bg-white border-line text-ink hover:border-ink'">{{ __('webapp.collab.no') }}</button>
                        </div>
                    </div>
                </template>
            </div>

            <label class="block mt-4">
                <span class="text-[12px] font-semibold text-muted">{{ __('webapp.collab.f_posts') }}</span>
                <input type="number" min="0" max="10000" x-model="feedback.posts_reels"
                       class="mt-1.5 w-full h-11 rounded-2xl border border-line bg-cream-input px-4 text-sm text-ink focus:border-ink outline-none">
            </label>

            {{-- Role-shaped, and the API agrees: `stories_posted`/`revenue` are
                 `prohibited` for a community, `benefits` is `prohibited` for a
                 business. Offering the wrong field would guarantee a 422. --}}
            <template x-if="isBusiness">
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-[12px] font-semibold text-muted">{{ __('webapp.collab.f_stories') }}</span>
                        <input type="number" min="0" max="10000" x-model="feedback.stories_posted"
                               class="mt-1.5 w-full h-11 rounded-2xl border border-line bg-cream-input px-4 text-sm text-ink focus:border-ink outline-none">
                    </label>
                    <label class="block">
                        <span class="text-[12px] font-semibold text-muted">{{ __('webapp.collab.f_revenue') }}</span>
                        <input type="number" min="0" step="0.01" x-model="feedback.revenue"
                               class="mt-1.5 w-full h-11 rounded-2xl border border-line bg-cream-input px-4 text-sm text-ink focus:border-ink outline-none">
                    </label>
                </div>
            </template>
            <template x-if="!isBusiness">
                <label class="block mt-4">
                    <span class="text-[12px] font-semibold text-muted">{{ __('webapp.collab.f_benefits') }}</span>
                    <textarea x-model="feedback.benefits" rows="3" maxlength="2000"
                              class="mt-1.5 w-full rounded-2xl border border-line bg-cream-input px-4 py-3 text-sm text-ink focus:border-ink outline-none"></textarea>
                </label>
            </template>

            <template x-if="modalError">
                <p class="mt-4 text-[12.5px] text-bad-ink whitespace-pre-line" x-text="modalError"></p>
            </template>

            <div class="mt-5 flex gap-2.5">
                <button type="button" @click="feedbackOpen = false"
                        class="flex-1 h-11 rounded-pill bg-white border border-line text-[13px] font-bold text-ink hover:border-ink transition">{{ __('webapp.common.cancel') }}</button>
                <button type="button" @click="submitFeedback()" :disabled="busy || !feedbackComplete"
                        class="kb-on-yellow flex-1 h-11 rounded-pill bg-primary text-ink text-[13px] font-bold shadow-btn hover:bg-primary-dark transition disabled:opacity-40"
                        x-text="busy ? t('common.saving') : t('collab.submit_impact')"></button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function collaborationPage() {
        return {
            c: null, loading: true, pageError: '', actionError: '', modalError: '', busy: false,
            note: '',
            cancelOpen: false, cancelReason: '',
            reviewOpen: false,
            review: {
                communication_rating: 0, reliability_rating: 0, fit_rating: 0,
                value_rating: 0, repeat_rating: 0, public_comment: '', would_collaborate_again: null,
            },
            feedbackOpen: false,
            feedback: {
                rating: 0, expectation_match: null, would_recommend: null, would_collaborate_again: null,
                posts_reels: '', stories_posted: '', revenue: '', benefits: '',
            },

            id: location.pathname.slice((window.KB_BASE || '').length).split('/')[2],

            statusPill(s) { return window.kbStatus(s); },
            fmtDate(v) { return window.kbDate(v); },
            initialOf(v) { return window.kbInitial(v); },

            get isFinished() { return this.c?.status === 'completed' || this.c?.status === 'cancelled'; },
            get canActivate() { return !!this.c?.actions?.can_activate; },
            get canComplete() { return !!this.c?.actions?.can_complete; },
            get canCancel() { return !!this.c?.actions?.can_cancel; },
            get ownAnswer() { return this.c?.own_completion?.status || null; },
            get partnerAnswer() { return this.c?.partner_completion_status || null; },
            get kolabId() { return this.c?.kolab_id || this.c?.kolab?.id || null; },
            get title() {
                return this.c?.kolab?.title || this.c?.collab_opportunity?.title || window.t('collab.title');
            },
            get myRoleLabel() {
                return this.c?.my_role === 'creator' ? window.t('collab.role_creator') : window.t('collab.role_applicant');
            },
            /**
             * The other side, resolved the same way My Kolabs resolves it: off the
             * `creator_profile` / `applicant_profile` pair, by id.
             *
             * Two traps here. `ProfileSummaryResource` names it `display_name`, not
             * `name` — reading `name` gets undefined and every partner renders as
             * "Partner". And the collaboration's own `business_profile` /
             * `community_profile` describe the two SIDES of the deal, not who is
             * looking, so they are only safe as a logo fallback keyed to the partner's
             * own role, never as the source of the name.
             */
            get partner() {
                const mine = this.me?.id;
                const p = this.c?.creator_profile?.id === mine
                    ? this.c?.applicant_profile
                    : this.c?.creator_profile;
                const side = p?.user_type === 'business' ? this.c?.business_profile : this.c?.community_profile;
                return {
                    id: p?.id || null,
                    name: p?.display_name || window.t('dashboard.partner'),
                    avatar: p?.avatar_url || side?.logo_url || side?.profile_photo || '',
                };
            },
            get stages() {
                return [
                    { key: 'scheduled', label: window.t('collab.stage_scheduled') },
                    { key: 'active', label: window.t('collab.stage_active') },
                    { key: 'completed', label: window.t('collab.stage_completed') },
                ];
            },
            get stageIndex() {
                return { scheduled: 0, active: 1, completed: 2 }[this.c?.status] ?? 0;
            },
            get answerOptions() {
                return [
                    { value: 'yes', label: window.t('collab.confirm_yes') },
                    { value: 'not_yet', label: window.t('collab.confirm_not_yet') },
                    { value: 'no', label: window.t('collab.confirm_no') },
                ];
            },
            answerLabel(v) { return window.tOr('collab.answer_' + v, v); },
            get ratingRows() {
                return [
                    { key: 'communication_rating', label: window.t('collab.r_communication') },
                    { key: 'reliability_rating', label: window.t('collab.r_reliability') },
                    { key: 'fit_rating', label: window.t('collab.r_fit') },
                    { key: 'value_rating', label: window.t('collab.r_value') },
                    { key: 'repeat_rating', label: window.t('collab.r_repeat') },
                ];
            },
            /* The 5-star format is all-or-nothing server-side: send one star field and
               the other four become `required`. So the button waits for all five. */
            get reviewComplete() {
                return this.ratingRows.every(r => this.review[r.key] >= 1);
            },
            get yesNoRows() {
                return [
                    { key: 'expectation_match', label: window.t('collab.f_expectation') },
                    { key: 'would_recommend', label: window.t('collab.f_recommend') },
                    { key: 'would_collaborate_again', label: window.t('collab.f_again') },
                ];
            },
            get feedbackComplete() {
                return this.feedback.rating >= 1
                    && this.yesNoRows.every(q => typeof this.feedback[q.key] === 'boolean');
            },

            async init() {
                if (!window.kb.requireAuth()) return;
                if (!await this.loadShell()) return;
                await this.load();
            },

            async load() {
                const res = await window.kb.api('/collaborations/' + this.id);
                this.loading = false;
                if (!res.ok) { this.pageError = window.kb.errorText(res, window.t('collab.load_error')); return; }
                this.c = res.json?.data || null;
                this.note = this.c?.own_completion?.note || '';
            },

            /**
             * Lifecycle errors carry their meaning in `error_code`, and their `errors`
             * bag holds context (`own_status`, `pending_completion_from`) rather than
             * validation messages — so errorText() would flatten it into "no" or
             * "completion_not_confirmed". Read the code first, always.
             */
            errorFor(res, fallbackKey) {
                const code = res?.json?.error_code || res?.json?.errors?.error_code;
                const known = {
                    awaiting_own_completion_confirmation: 'collab.gate_own',
                    awaiting_partner_completion_confirmation: 'collab.gate_partner',
                    completion_not_confirmed: 'collab.gate_not_confirmed',
                    invalid_status_transition: 'collab.gate_stale',
                }[code];
                if (known) return window.t(known);
                return window.kb.errorText(res, window.t(fallbackKey));
            },

            async act(path, body = null, method = 'POST') {
                this.busy = true;
                this.actionError = '';
                const res = await window.kb.api('/collaborations/' + this.id + path, { method, body });
                this.busy = false;
                if (!res.ok) { this.actionError = this.errorFor(res, 'collab.action_error'); return false; }
                await this.load();
                return true;
            },

            activate() { return this.act('/activate'); },
            complete() { return this.act('/complete', {}); },

            openCancel() { this.cancelReason = ''; this.actionError = ''; this.cancelOpen = true; },
            async cancel() {
                if (this.cancelReason.trim().length < 20) return;
                if (await this.act('/cancel', { reason: this.cancelReason.trim() })) this.cancelOpen = false;
            },

            /** The gate step. Re-answering updates the same row, so this is safe to repeat. */
            async confirmCompletion(status) {
                const body = { status };
                if (this.note.trim() !== '') body.note = this.note.trim();
                await this.act('/completion', body);
            },

            async submitReview() {
                if (!this.reviewComplete) return;
                this.busy = true;
                this.modalError = '';
                const body = {
                    communication_rating: this.review.communication_rating,
                    reliability_rating: this.review.reliability_rating,
                    fit_rating: this.review.fit_rating,
                    value_rating: this.review.value_rating,
                    repeat_rating: this.review.repeat_rating,
                };
                if (this.review.public_comment.trim() !== '') body.public_comment = this.review.public_comment.trim();
                if (typeof this.review.would_collaborate_again === 'boolean') {
                    body.would_collaborate_again = this.review.would_collaborate_again;
                }
                const res = await window.kb.api('/collaborations/' + this.id + '/review', { method: 'POST', body });
                this.busy = false;
                if (!res.ok) { this.modalError = this.errorFor(res, 'collab.review_error'); return; }
                this.reviewOpen = false;
                await this.load();
            },

            openFeedback() {
                this.modalError = '';
                const own = this.c?.own_feedback;
                if (own) {
                    this.feedback = {
                        rating: own.rating ?? 0,
                        expectation_match: own.expectation_match,
                        would_recommend: own.would_recommend,
                        would_collaborate_again: own.would_collaborate_again,
                        posts_reels: own.posts_reels ?? '',
                        stories_posted: own.stories_posted ?? '',
                        revenue: own.revenue ?? '',
                        benefits: own.benefits ?? '',
                    };
                }
                this.feedbackOpen = true;
            },

            async submitFeedback() {
                if (!this.feedbackComplete) return;
                this.busy = true;
                this.modalError = '';

                const body = {
                    rating: Number(this.feedback.rating),
                    expectation_match: this.feedback.expectation_match,
                    would_recommend: this.feedback.would_recommend,
                    would_collaborate_again: this.feedback.would_collaborate_again,
                };
                const num = (v) => (v === '' || v === null ? null : Number(v));
                if (num(this.feedback.posts_reels) !== null) body.posts_reels = num(this.feedback.posts_reels);
                if (this.isBusiness) {
                    if (num(this.feedback.stories_posted) !== null) body.stories_posted = num(this.feedback.stories_posted);
                    if (num(this.feedback.revenue) !== null) body.revenue = num(this.feedback.revenue);
                } else if (this.feedback.benefits.trim() !== '') {
                    body.benefits = this.feedback.benefits.trim();
                }

                const editing = !!this.c?.own_feedback;
                const res = await window.kb.api('/collaborations/' + this.id + '/feedback', {
                    method: editing ? 'PUT' : 'POST', body,
                });
                this.busy = false;
                if (!res.ok) { this.modalError = this.errorFor(res, 'collab.impact_error'); return; }
                this.feedbackOpen = false;
                await this.load();
            },
        };
    }
</script>
@endpush
@endsection
