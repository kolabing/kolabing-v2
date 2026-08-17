@extends('webapp.layout')
@section('title', __('webapp.form.create_title'))

@section('body')
<div class="min-h-screen md:flex" x-data="kbMerge(kbShell(), createFlow())" x-init="init()">
    @include('webapp.partials.sidebar', ['active' => 'kolabs'])

    <main class="flex-1 min-w-0 overflow-x-hidden">
    <div class="max-w-[620px] mx-auto px-5 md:px-10 py-8 md:py-10 pb-20 kb-fade-up">

        {{-- ── Wizard header ───────────────────────────────────────────── --}}
        <div class="flex items-center gap-3.5">
            <button type="button" @click="back()"
                    class="w-10 h-10 rounded-full bg-white border border-ink/10 hover:border-ink/30 transition flex items-center justify-center shrink-0" aria-label="{{ __('webapp.common.back') }}">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#19150F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
            </button>
            <p class="flex-1 text-center text-[13px] font-semibold tracking-[1.2px] uppercase text-ink" x-text="wizardTitle">{{ __('webapp.form.create_title') }}</p>
            <span class="w-10 shrink-0"></span>
        </div>

        <template x-if="loading">
            <p class="mt-8 text-muted">{{ __('webapp.common.loading') }}</p>
        </template>
        <template x-if="error">
            <div class="mt-5 rounded-2xl bg-[#F8D7DA] text-[#721C24] text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
        </template>

        {{-- ══ Intent picker ═══════════════════════════════════════════ --}}
        <template x-if="!loading && !intent">
            <div class="mt-7">
                <h1 class="font-anton text-[26px] text-ink" x-text="intentTitle"></h1>
                <p class="text-sm text-body mt-2" x-text="intentSub"></p>
                <div class="flex flex-col gap-3.5 mt-7">
                    <template x-for="io in intentOptions" :key="io.value">
                        <button type="button" @click="pickIntent(io.value)"
                                class="text-left p-[22px] rounded-[20px] bg-white border border-ink/10 hover:border-primary hover:bg-primary-tint hover:-translate-y-px transition flex items-center gap-4">
                            <span class="w-12 h-12 rounded-[14px] bg-cream-low flex items-center justify-center font-anton text-xl text-ink shrink-0" x-text="io.glyph"></span>
                            <span class="flex-1 min-w-0">
                                <span class="flex items-center gap-2">
                                    <span class="text-[15px] font-bold text-ink" x-text="io.title"></span>
                                    <span x-show="io.free" x-cloak class="px-2 py-[3px] rounded-pill bg-[#D4EDDA] text-[#155724] text-[10px] font-bold tracking-[.5px]">{{ __('webapp.form.free') }}</span>
                                </span>
                                <span class="block text-[13px] text-muted mt-[3px]" x-text="io.sub"></span>
                            </span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8C8474" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </template>
                </div>
            </div>
        </template>

        {{-- ══ Step flow ═══════════════════════════════════════════════ --}}
        <template x-if="!loading && intent">
            <div class="mt-[22px]">
                <div class="flex gap-1.5">
                    <template x-for="(s, i) in visibleSteps" :key="s.key">
                        <div class="flex-1 h-1 rounded-sm transition" :class="i <= stepIndex ? 'bg-primary' : 'bg-ink/10'"></div>
                    </template>
                </div>

                <div class="mt-[26px]">
                    <p class="text-xs font-bold tracking-[1px] uppercase text-body" x-text="step.eyebrow"></p>
                    <p class="text-[19px] font-bold text-ink mt-1.5" x-text="step.q"></p>
                    <p class="text-[13.5px] text-body mt-1" x-text="step.sub"></p>

                    {{-- single-select options --}}
                    <template x-if="step.kind === 'options'">
                        <div class="flex flex-col gap-2.5 mt-[18px]">
                            <template x-for="op in stepOptions" :key="op.value">
                                <button type="button" @click="form[step.field] = op.value"
                                        class="text-left flex items-center gap-3 p-4 rounded-xl border transition"
                                        :class="form[step.field] === op.value ? 'bg-primary-tint border-primary' : 'bg-white border-ink/10'">
                                    <span class="w-6 h-6 rounded-full shrink-0 flex items-center justify-center border-[1.5px]"
                                          :class="form[step.field] === op.value ? 'bg-primary border-primary' : 'border-ink/20'">
                                        <template x-if="form[step.field] === op.value">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#19150F" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                        </template>
                                    </span>
                                    <span>
                                        <span class="block text-sm text-ink" :class="form[step.field] === op.value ? 'font-bold' : 'font-medium'" x-text="op.label"></span>
                                        <span x-show="op.description" x-cloak class="block text-xs text-muted mt-0.5" x-text="op.description"></span>
                                    </span>
                                </button>
                            </template>
                        </div>
                    </template>

                    {{-- multi-select chips --}}
                    <template x-if="step.kind === 'chips'">
                        <div class="flex gap-2 flex-wrap mt-[18px]">
                            <template x-for="op in stepOptions" :key="op.value">
                                <button type="button" @click="toggleArray(step.field, op.value)"
                                        class="px-[18px] py-2.5 rounded-pill text-[13px] font-semibold border transition"
                                        :class="(form[step.field] || []).includes(op.value) ? 'bg-primary-tint border-primary text-ink' : 'bg-white border-ink/[.12] text-ink'"
                                        x-text="op.label"></button>
                            </template>
                        </div>
                    </template>

                    {{-- text / number / select fields --}}
                    <template x-if="step.kind === 'text'">
                        <div class="flex flex-col gap-3.5 mt-[18px]">
                            <template x-for="f in step.fields" :key="f.field">
                                <div class="flex flex-col gap-1.5">
                                    <label class="text-xs font-semibold text-body" x-text="f.label"></label>
                                    <template x-if="f.type === 'textarea'">
                                        <textarea x-model="form[f.field]" :placeholder="f.ph" rows="4" :maxlength="f.max || 5000"
                                                  class="rounded-2xl border border-transparent bg-cream-input px-4 py-3.5 text-sm text-ink resize-y"></textarea>
                                    </template>
                                    <template x-if="f.type === 'select'">
                                        <select x-model="form[f.field]" class="h-[50px] rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
                                            <option value="">{{ __('webapp.common.select') }}</option>
                                            <template x-for="o in (lookups[f.source] || [])" :key="o.value">
                                                <option :value="o.value" x-text="o.label"></option>
                                            </template>
                                        </select>
                                    </template>
                                    <template x-if="f.type === 'city'">
                                        <select x-model="form[f.field]" class="h-[50px] rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
                                            <option value="">{{ __('webapp.common.select') }}</option>
                                            <template x-for="c in cities" :key="c.id">
                                                <option :value="c.name" x-text="c.name"></option>
                                            </template>
                                        </select>
                                    </template>
                                    <template x-if="f.type === 'number'">
                                        <input x-model.number="form[f.field]" type="number" min="1" :placeholder="f.ph"
                                               class="h-[50px] rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
                                    </template>
                                    <template x-if="!f.type || f.type === 'input'">
                                        <input x-model="form[f.field]" type="text" :placeholder="f.ph" :maxlength="f.max || 255"
                                               class="h-[50px] rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- media --}}
                    <template x-if="step.kind === 'media'">
                        <div class="mt-[18px]">
                            <label class="block border-[1.5px] border-dashed border-ink/20 rounded-[18px] px-5 py-11 text-center cursor-pointer hover:border-ink transition">
                                <input type="file" accept="image/*" multiple class="hidden" @change="onMedia($event)">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#8C8474" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="mx-auto"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                                <p class="text-sm font-semibold text-ink mt-2.5">{{ __('webapp.form.drop_photos') }}</p>
                                <p class="text-[12.5px] text-muted mt-1" x-text="step.mediaHint"></p>
                                <p class="text-[12.5px] text-muted mt-1" x-show="uploading" x-cloak>{{ __('webapp.common.uploading') }}</p>
                            </label>
                            <div class="grid grid-cols-3 gap-2.5 mt-3.5" x-show="form.media.length" x-cloak>
                                <template x-for="(m, i) in form.media" :key="m.url">
                                    <div class="relative rounded-xl overflow-hidden" style="aspect-ratio:1;">
                                        <img :src="m.url" alt="" class="w-full h-full object-cover">
                                        <button type="button" @click="form.media.splice(i, 1)"
                                                class="absolute top-1.5 right-1.5 w-7 h-7 rounded-full bg-ink/75 text-white text-xs font-bold flex items-center justify-center" aria-label="{{ __('webapp.common.remove') }}">✕</button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- availability --}}
                    <template x-if="step.kind === 'avail'">
                        <div class="mt-[18px] flex flex-col gap-4">
                            <div class="flex gap-3">
                                <div class="flex-1 flex flex-col gap-1.5">
                                    <label class="text-xs font-semibold text-body">{{ __('webapp.detail.from') }}</label>
                                    <input x-model="form.availability_start" type="date" :min="minDate"
                                           class="h-[50px] w-full rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
                                </div>
                                <div class="flex-1 flex flex-col gap-1.5">
                                    <label class="text-xs font-semibold text-body">{{ __('webapp.detail.until') }}</label>
                                    <input x-model="form.availability_end" type="date" :min="form.availability_start || minDate"
                                           class="h-[50px] w-full rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
                                </div>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-body">{{ __('webapp.form.which_days') }}</label>
                                <div class="flex gap-2 mt-2 flex-wrap">
                                    <template x-for="(d, i) in dayLabels" :key="d">
                                        <button type="button" @click="toggleArray('recurring_days', i + 1)"
                                                class="w-[42px] h-[42px] rounded-full text-xs font-bold border transition"
                                                :class="form.recurring_days.includes(i + 1) ? 'bg-primary border-primary text-ink' : 'bg-white border-ink/[.12] text-ink'"
                                                x-text="d"></button>
                                    </template>
                                </div>
                            </div>
                            <div class="flex flex-col gap-1.5 max-w-[200px]">
                                <label class="text-xs font-semibold text-body">{{ __('webapp.form.selected_time') }} <span class="font-normal text-muted">({{ __('webapp.common.optional') }})</span></label>
                                <input x-model="form.selected_time" type="time"
                                       class="h-[50px] rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
                            </div>
                        </div>
                    </template>

                    {{-- review --}}
                    <template x-if="step.kind === 'review'">
                        <div class="mt-[18px] bg-white border border-ink/[.08] rounded-[20px] p-[22px] shadow-card">
                            <template x-for="rr in reviewRows" :key="rr.label">
                                <div class="flex gap-4 py-2.5 border-b border-ink/[.06]">
                                    <div class="w-[130px] shrink-0 text-xs font-semibold tracking-[.5px] uppercase text-muted pt-px" x-text="rr.label"></div>
                                    <div class="text-[13.5px] font-medium text-ink min-w-0 break-words" x-text="rr.value"></div>
                                </div>
                            </template>
                            <p class="text-[12.5px] text-muted mt-3.5" x-text="t('form.publish_note', { audience: reviewAudience })"></p>
                        </div>
                    </template>
                </div>

                <div class="flex gap-2.5 mt-7">
                    <button type="button" @click="back()"
                            class="h-12 px-6 rounded-pill bg-white border border-line text-ink text-sm font-bold hover:border-ink transition">{{ __('webapp.common.back') }}</button>
                    <button type="button" @click="next()" :disabled="busy"
                            class="flex-1 h-12 rounded-pill text-sm font-bold shadow-btn hover:-translate-y-px transition disabled:opacity-50"
                            :class="isReview ? 'bg-ink text-primary' : 'bg-primary text-ink'"
                            x-text="busy ? t('form.saving') : nextLabel"></button>
                </div>
            </div>
        </template>
    </div>
    </main>

    {{-- ── Published sheet ─────────────────────────────────────────────── --}}
    <div x-show="doneOpen" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4 sm:p-8"
         style="background: rgba(13,17,20,.62); backdrop-filter: blur(3px);">
        <div class="bg-white rounded-[22px] w-full max-w-[400px] px-8 py-9 text-center kb-fade-up-fast">
            <div class="w-16 h-16 rounded-full bg-[#56624D] mx-auto flex items-center justify-center">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            </div>
            <p class="text-xl font-bold text-ink mt-[18px]" x-text="doneTitle"></p>
            <p class="text-sm text-body leading-relaxed mt-2" x-text="doneBody"></p>
            <a href="{{ $base }}/kolabs"
               class="w-full h-[50px] mt-[22px] rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark transition flex items-center justify-center">{{ __('webapp.common.done') }}</a>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function createFlow() {
        // /kolabs/{id}/edit  →  edit mode (intent is locked to the saved Kolab).
        const parts = location.pathname.slice((window.KB_BASE || '').length).split('/');
        const editId = parts[3] === 'edit' ? parts[2] : null;

        return {
            loading: true, busy: false, uploading: false, error: '',
            isEdit: !!editId, editId,
            intent: null, stepIndex: 0, doneOpen: false, doneTitle: '', doneBody: '',
            cities: [],
            lookups: { needs: [], deliverables: [], offerings: [], goals: [], product_types: [], community_types: [] },
            dayLabels: [],
            minDate: window.kbDayOffset(1),
            form: {
                title: '', description: '', offer_headline: '', preferred_city: '',
                needs: [], typical_attendance: null, offers_in_return: [],
                goal: '', offering: [], seeking_communities: [],
                venue_name: '', capacity: null,
                product_name: '', product_type: '',
                media: [], recurring_days: [], availability_start: '', availability_end: '', selected_time: '',
            },

            // ── Intent config ───────────────────────────────────────────
            get intentOptions() {
                if (this.isCommunity) {
                    return [{ value: 'community', glyph: 'V', free: true, title: t('form.intent_community'), sub: t('form.intent_community_sub') }];
                }
                return [
                    { value: 'venue', glyph: 'V', free: false, title: t('form.intent_venue'), sub: t('form.intent_venue_sub') },
                    { value: 'product', glyph: 'P', free: false, title: t('form.intent_product'), sub: t('form.intent_product_sub') },
                ];
            },
            get intentTitle() { return this.isCommunity ? t('form.intent_title_community') : t('form.intent_title_business'); },
            get intentSub() { return this.isCommunity ? t('form.intent_sub_community') : t('form.intent_sub_business'); },
            get wizardTitle() {
                if (this.isEdit) return t('form.edit_title');
                if (!this.intent) return t('form.create_title');
                return { community: t('form.intent_community'), venue: t('form.intent_venue'), product: t('form.intent_product') }[this.intent];
            },
            get apiIntent() {
                return { community: 'community_seeking', venue: 'venue_promotion', product: 'product_promotion' }[this.intent];
            },
            get reviewAudience() { return this.intent === 'community' ? t('form.audience_business') : t('form.audience_community'); },

            // ── Steps ───────────────────────────────────────────────────
            get steps() {
                const media = (required) => ({
                    key: 'media', kind: 'media', required, eyebrow: t('form.eyebrow_media'), q: t('form.q_media'),
                    sub: required ? t('form.sub_media_required') : t('form.sub_media'),
                    mediaHint: required ? t('form.media_hint_required') : t('form.media_hint'),
                });
                const avail = (required) => ({
                    key: 'avail', kind: 'avail', required, eyebrow: t('form.eyebrow_avail'), q: t('form.q_avail'),
                    sub: required ? t('form.sub_avail_required') : t('form.sub_avail'),
                });
                const review = { key: 'review', kind: 'review', eyebrow: t('form.eyebrow_review'), q: t('form.q_review'), sub: t('form.sub_review') };
                const goal = {
                    key: 'goal', kind: 'options', field: 'goal', source: 'goals',
                    eyebrow: t('form.eyebrow_goal'), q: t('form.q_goal'), sub: t('form.sub_goal'),
                };
                const offering = {
                    key: 'offering', kind: 'chips', field: 'offering', source: 'offerings',
                    eyebrow: t('form.eyebrow_offer'), q: t('form.q_offer'), sub: t('form.sub_offer'),
                };
                const ideal = {
                    key: 'ideal', kind: 'chips', field: 'seeking_communities', source: 'community_types', optional: true,
                    eyebrow: t('form.eyebrow_ideal'), q: t('form.q_ideal'), sub: t('form.sub_ideal'),
                };

                if (this.intent === 'community') {
                    return [
                        { key: 'needs', kind: 'chips', field: 'needs', source: 'needs',
                          eyebrow: t('form.eyebrow_need'), q: t('form.q_need'), sub: t('form.sub_need') },
                        { key: 'details', kind: 'text', eyebrow: t('form.eyebrow_kolab'), q: t('form.q_details_community'), sub: t('form.sub_details_community'),
                          fields: [
                            { field: 'title', label: t('form.headline'), ph: t('form.ph_headline_community') },
                            { field: 'description', label: t('form.what_you_bring'), ph: t('form.ph_about_community'), type: 'textarea' },
                            { field: 'preferred_city', label: t('account.city'), type: 'city' },
                            { field: 'typical_attendance', label: t('form.typical_attendance'), ph: '40', type: 'number' },
                          ] },
                        { key: 'return', kind: 'chips', field: 'offers_in_return', source: 'deliverables',
                          eyebrow: t('form.eyebrow_return'), q: t('form.q_return'), sub: t('form.sub_return') },
                        avail(false), media(false), review,
                    ];
                }
                if (this.intent === 'venue') {
                    return [
                        { key: 'details', kind: 'text', eyebrow: t('form.eyebrow_venue'), q: t('form.q_details_venue'), sub: t('form.sub_details_venue'),
                          fields: [
                            { field: 'title', label: t('form.headline'), ph: t('form.ph_headline_venue') },
                            { field: 'description', label: t('form.about_kolab'), ph: t('form.ph_about_venue'), type: 'textarea' },
                            { field: 'venue_name', label: t('register.venue_name'), ph: '', optional: true },
                            { field: 'capacity', label: t('register.capacity'), ph: '40', type: 'number', optional: true },
                          ] },
                        goal, media(true), offering, ideal, avail(true), review,
                    ];
                }
                return [
                    { key: 'details', kind: 'text', eyebrow: t('form.eyebrow_product'), q: t('form.q_details_product'), sub: t('form.sub_details_product'),
                      fields: [
                        { field: 'title', label: t('form.headline'), ph: t('form.ph_headline_product') },
                        { field: 'description', label: t('form.about_product'), ph: t('form.ph_about_product'), type: 'textarea' },
                        { field: 'product_name', label: t('form.product_name'), ph: '' },
                        { field: 'product_type', label: t('form.product_type'), type: 'select', source: 'product_types' },
                        { field: 'preferred_city', label: t('account.city'), type: 'city' },
                      ] },
                    goal, media(false), offering, ideal, avail(false), review,
                ];
            },
            /**
             * A taxonomy the admin has not populated yet (e.g. `/lookup/goals` is
             * empty on some environments) would otherwise render a step with zero
             * options that the user can never satisfy. Every such field is optional
             * on the API, so drop the step instead of dead-ending the wizard.
             */
            get visibleSteps() {
                return this.steps.filter(s => {
                    if (s.kind !== 'options' && s.kind !== 'chips') return true;
                    return (this.lookups[s.source] || []).length > 0;
                });
            },
            get step() { return this.visibleSteps[this.stepIndex] || {}; },
            get stepOptions() { return this.lookups[this.step.source] || []; },
            get isReview() { return this.step.kind === 'review'; },
            get nextLabel() {
                if (!this.isReview) return t('common.continue');
                return this.isEdit ? t('form.save_changes') : t('form.publish_kolab');
            },

            get reviewRows() {
                const f = this.form, rows = [];
                const label = (src, v) => (this.lookups[src] || []).find(o => o.value === v)?.label || window.kbHumanize(v);
                const labels = (src, arr) => (arr || []).map(v => label(src, v)).join(', ') || '—';

                rows.push({ label: t('form.headline'), value: f.title || t('form.untitled') });
                if (this.intent === 'community') {
                    rows.push({ label: t('form.review_need'), value: labels('needs', f.needs) });
                    rows.push({ label: t('form.review_you_bring'), value: labels('deliverables', f.offers_in_return) });
                    rows.push({ label: t('form.typical_attendance'), value: f.typical_attendance || '—' });
                } else {
                    rows.push({ label: t('form.review_goal'), value: f.goal ? label('goals', f.goal) : '—' });
                    rows.push({ label: t('form.review_you_offer'), value: labels('offerings', f.offering) });
                    rows.push({ label: t('form.review_ideal'), value: (f.seeking_communities || []).length ? labels('community_types', f.seeking_communities) : t('form.open_to_all') });
                }
                if (f.preferred_city) rows.push({ label: t('account.city'), value: f.preferred_city });
                rows.push({ label: t('form.review_window'), value: this.windowSummary() });
                rows.push({ label: t('form.review_photos'), value: f.media.length ? t('form.n_photos', { n: f.media.length }) : '—' });
                return rows;
            },
            windowSummary() {
                const f = this.form;
                const from = window.kbDateShort(f.availability_start);
                const to = window.kbDateShort(f.availability_end);
                const range = from && to ? `${from} – ${to}` : (from || t('kolabs.no_window'));
                const days = f.recurring_days.length
                    ? f.recurring_days.slice().sort((a, b) => a - b).map(d => this.dayLabels[d - 1]).join(' ')
                    : '';
                return [range, days, f.selected_time].filter(Boolean).join(' · ');
            },

            // ── Lifecycle ───────────────────────────────────────────────
            async init() {
                if (!window.kb.requireAuth()) return;
                const me = await this.loadShell();
                if (!me) return;
                this.dayLabels = t('detail.day_initials').split(',');
                await this.loadLookups();
                if (this.isEdit) await this.loadExisting();
                else if (this.isCommunity) this.intent = 'community'; // communities have a single path
                this.loading = false;
            },
            async loadLookups() {
                const map = {
                    needs: '/lookup/needs', deliverables: '/lookup/deliverables', offerings: '/lookup/offerings',
                    goals: '/lookup/goals', product_types: '/lookup/product-types',
                    community_types: '/lookup/community-types',
                };
                const jobs = Object.entries(map).map(async ([k, path]) => {
                    const res = await window.kb.api(path, { auth: false });
                    if (res.ok) this.lookups[k] = window.kb.rows(res);
                });
                jobs.push((async () => {
                    const res = await window.kb.api('/cities', { auth: false });
                    if (res.ok) this.cities = window.kb.rows(res).filter(c => c.id && c.id !== 'other');
                })());
                await Promise.all(jobs);
            },
            async loadExisting() {
                const res = await window.kb.api('/kolabs/' + this.editId);
                if (!res.ok) { this.error = window.kb.errorText(res, t('form.load_error')); return; }
                const k = res.json?.data || {};
                this.intent = { community_seeking: 'community', venue_promotion: 'venue', product_promotion: 'product' }[k.intent_type] || 'product';
                for (const f of ['title', 'description', 'offer_headline', 'preferred_city', 'venue_name',
                                 'product_name', 'product_type', 'goal', 'typical_attendance', 'capacity',
                                 'availability_start', 'availability_end', 'selected_time']) {
                    if (k[f] != null) this.form[f] = k[f];
                }
                for (const f of ['needs', 'offers_in_return', 'offering', 'seeking_communities', 'recurring_days']) {
                    if (Array.isArray(k[f])) this.form[f] = [...k[f]];
                }
                if (Array.isArray(k.media)) this.form.media = k.media.map((m, i) => ({ url: m.url, type: m.type || 'image', sort_order: i }));
            },

            pickIntent(v) { this.intent = v; this.stepIndex = 0; this.error = ''; },
            toggleArray(field, value) {
                const arr = this.form[field] || [];
                const i = arr.indexOf(value);
                if (i === -1) arr.push(value); else arr.splice(i, 1);
                this.form[field] = arr;
            },
            back() {
                this.error = '';
                if (!this.intent) { window.nav('/kolabs'); return; }
                if (this.stepIndex === 0) {
                    // Communities have no intent choice to fall back to.
                    if (this.isEdit || this.isCommunity) { window.nav('/kolabs'); return; }
                    this.intent = null;
                    return;
                }
                this.stepIndex -= 1;
            },
            async next() {
                this.error = '';
                const problem = this.validateStep();
                if (problem) { this.error = problem; return; }
                if (!this.isReview) { this.stepIndex += 1; window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
                await this.submit();
            },
            /**
             * Generic over the step definitions: what is optional and what is required
             * is declared on the step/field itself (`optional`, `required`), so adding
             * or renaming a step never means editing this function.
             */
            validateStep() {
                const s = this.step, f = this.form;
                if (s.kind === 'text') {
                    for (const fld of s.fields) {
                        if (fld.optional) { continue; }
                        const v = f[fld.field];
                        if (v === '' || v === null || v === undefined) return t('form.err_required', { field: fld.label });
                    }
                    return null;
                }
                if ((s.kind === 'chips' || s.kind === 'options') && !s.optional) {
                    const picked = s.kind === 'chips' ? (f[s.field] || []).length > 0 : !!f[s.field];
                    if (!picked) return t('form.err_pick_one');
                }
                if (s.kind === 'media' && s.required && f.media.length === 0) return t('form.err_photo_required');
                if (s.kind === 'avail' && s.required && !f.availability_start) return t('form.err_start_required');
                return null;
            },

            async onMedia(e) {
                const files = Array.from(e.target.files || []);
                if (!files.length) return;
                this.error = ''; this.uploading = true;
                for (const file of files) {
                    const r = await window.kb.uploadFile(file, 'kolabs');
                    if (r.ok && r.json?.data?.url) {
                        this.form.media.push({ url: r.json.data.url, type: 'image', sort_order: this.form.media.length });
                    } else {
                        this.error = window.kb.errorText(r, t('form.upload_error'));
                    }
                }
                this.uploading = false;
                e.target.value = '';
            },

            payload() {
                const f = this.form;
                const body = {
                    intent_type: this.apiIntent,
                    title: f.title,
                    description: f.description,
                };
                if (f.offer_headline) body.offer_headline = f.offer_headline;
                if (f.media.length) body.media = f.media.map((m, i) => ({ url: m.url, type: m.type || 'image', sort_order: i }));
                if (f.availability_start) body.availability_start = f.availability_start;
                if (f.availability_end) body.availability_end = f.availability_end;
                if (f.selected_time) body.selected_time = f.selected_time;
                if (f.recurring_days.length) body.recurring_days = f.recurring_days;
                // The API requires a mode whenever a window is set; recurring days imply "recurring".
                if (f.availability_start || f.recurring_days.length) {
                    body.availability_mode = f.recurring_days.length ? 'recurring' : 'flexible';
                }

                if (this.intent === 'community') {
                    return {
                        ...body,
                        preferred_city: f.preferred_city,
                        needs: f.needs,
                        typical_attendance: f.typical_attendance,
                        offers_in_return: f.offers_in_return,
                    };
                }

                body.offering = f.offering;
                if (f.goal) body.goal = f.goal;
                if (f.seeking_communities.length) body.seeking_communities = f.seeking_communities;

                if (this.intent === 'venue') {
                    if (f.venue_name) body.venue_name = f.venue_name;
                    if (f.capacity) body.capacity = f.capacity;
                    // venue_promotion is location-bound: the API does not want preferred_city.
                    return body;
                }

                body.preferred_city = f.preferred_city;
                body.product_name = f.product_name;
                body.product_type = f.product_type;
                return body;
            },

            async submit() {
                this.busy = true;
                const res = this.isEdit
                    ? await window.kb.api('/kolabs/' + this.editId, { method: 'PUT', body: this.payload() })
                    : await window.kb.api('/kolabs', { method: 'POST', body: this.payload() });

                if (!res.ok) {
                    this.busy = false;
                    this.error = window.kb.errorText(res, t('form.save_error'));
                    return;
                }

                const id = res.json?.data?.id || this.editId;
                if (this.isEdit) {
                    this.busy = false;
                    window.nav('/kolabs/' + id);
                    return;
                }

                // Fresh Kolabs are created as drafts — publishing is the second call.
                const pub = await window.kb.api('/kolabs/' + id + '/publish', { method: 'POST' });
                this.busy = false;
                if (pub.status === 402) { window.nav('/subscription?reason=publish'); return; }
                if (pub.ok) {
                    this.doneTitle = t('form.published_title');
                    this.doneBody = t('form.published_body', { audience: this.reviewAudience });
                } else {
                    // Saved, but not live — say so instead of implying it published.
                    this.doneTitle = t('form.saved_title');
                    this.doneBody = window.kb.errorText(pub, t('form.saved_body'));
                }
                this.doneOpen = true;
            },
        };
    }
</script>
@endpush
@endsection
