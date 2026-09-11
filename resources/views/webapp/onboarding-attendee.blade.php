@extends('webapp.layout')
@section('title', __('webapp.attendee_onboarding.title'))

@section('body')
{{--
    Attendee onboarding — the same four steps the mobile app runs.

    Parity is the requirement here, not merely "something that works": an attendee
    who signs up on the phone and one who signs up in a browser must end up with the
    same profile, or the two clients start disagreeing about who someone is. The
    mobile flow is You → City → Interests → Join
    (`lib/features/onboarding/screens/attendee/`), only step 1 is required, every
    other step is skippable, and the payload omits empty optionals so a re-run never
    clobbers a value with a blank. All of that is mirrored below, against the same
    `PUT /onboarding/attendee`.

    No sidebar: this is a wall, not a page. Until it is finished the attendee has no
    handle, and the panel would be addressed to nobody.
--}}
<div class="min-h-screen bg-cream-alt" x-data="kbMerge(kbShell(), attendeeOnboarding())" x-init="init()">
    <div class="max-w-[560px] mx-auto px-5 py-10 md:py-14">

        <x-k-mark :size="28" class="mb-8" />

        {{-- Progress: four dots, because four steps is few enough to show honestly. --}}
        <div class="flex items-center gap-2 mb-7">
            <template x-for="n in 4" :key="n">
                <span class="h-1.5 flex-1 rounded-pill transition"
                      :class="n <= step ? 'bg-primary' : 'bg-ink/10'"></span>
            </template>
        </div>

        <template x-if="error">
            <div class="mb-5 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
        </template>

        {{-- ── Step 1 — You ─────────────────────────────────────────────── --}}
        <template x-if="step === 1">
            <div>
                <h1 class="font-anton text-[26px] leading-tight tracking-[.6px] text-ink">{{ __('webapp.attendee_onboarding.s1_title') }}</h1>
                <p class="text-sm text-muted mt-2">{{ __('webapp.attendee_onboarding.s1_sub') }}</p>

                <div class="mt-6 flex flex-col gap-4">
                    {{-- Photo is optional and says so; an empty avatar ring is a prompt, not an error. --}}
                    <div class="flex items-center gap-4">
                        <span class="w-16 h-16 rounded-full bg-primary/30 overflow-hidden flex items-center justify-center text-xl font-bold text-ink shrink-0">
                            <template x-if="photoPreview"><img :src="photoPreview" alt="" class="w-full h-full object-cover"></template>
                            <template x-if="!photoPreview"><span x-text="window.kbInitial(form.name || '?')"></span></template>
                        </span>
                        <div>
                            <label class="inline-flex items-center h-9 px-4 rounded-pill bg-white border border-line text-[13px] font-bold cursor-pointer hover:border-ink transition">
                                <input type="file" accept="image/*" class="hidden" @change="pickPhoto($event)">
                                {{ __('webapp.attendee_onboarding.add_photo') }}
                            </label>
                            <p class="text-[11.5px] text-muted mt-1">{{ __('webapp.common.optional') }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="text-[12px] font-semibold text-body">{{ __('webapp.attendee_onboarding.name_label') }}</label>
                        <input x-model="form.name" type="text" maxlength="255"
                               placeholder="{{ __('webapp.attendee_onboarding.name_ph') }}"
                               class="mt-1.5 w-full h-12 px-4 rounded-xl bg-white border border-ink/[.10] focus:border-ink/30 text-[14px] outline-none transition">
                    </div>

                    <div>
                        <label class="text-[12px] font-semibold text-body">{{ __('webapp.attendee_onboarding.handle_label') }}</label>
                        <div class="mt-1.5 flex items-center h-12 px-4 rounded-xl bg-white border transition"
                             :class="handleState === 'taken' ? 'border-danger' : (handleState === 'ok' ? 'border-ok-ink/40' : 'border-ink/[.10]')">
                            <span class="text-[14px] text-muted">@</span>
                            <input x-model="form.handle" @input="onHandleInput()" type="text" maxlength="20"
                                   autocapitalize="none" spellcheck="false" autocomplete="off"
                                   placeholder="{{ __('webapp.attendee_onboarding.handle_ph') }}"
                                   class="flex-1 min-w-0 ml-1 bg-transparent text-[14px] outline-none">
                            <span x-show="handleState === 'checking'" x-cloak class="text-[11.5px] text-muted">{{ __('webapp.attendee_onboarding.handle_checking') }}</span>
                            <span x-show="handleState === 'ok'" x-cloak class="text-ok-ink text-[15px] leading-none">✓</span>
                        </div>
                        <p class="mt-1 text-[11.5px]"
                           :class="handleState === 'taken' || handleState === 'bad' ? 'text-bad-ink' : 'text-muted'"
                           x-text="handleHint"></p>
                        {{-- When a handle is taken the API suggests free ones; offering them
                             is faster than making someone guess again. --}}
                        <div class="flex gap-1.5 flex-wrap mt-2" x-show="handleSuggestions.length" x-cloak>
                            <template x-for="sg in handleSuggestions" :key="sg">
                                <button type="button" @click="useSuggestion(sg)"
                                        class="px-3 py-1.5 rounded-pill bg-cream-low text-body text-[12px] font-semibold hover:bg-cream-low-hover transition"
                                        x-text="'@' + sg"></button>
                            </template>
                        </div>
                    </div>
                </div>

                <button type="button" @click="step = 2" :disabled="!step1Ready"
                        class="kb-on-yellow mt-7 w-full h-12 rounded-pill bg-primary text-ink text-[15px] font-bold shadow-btn hover:bg-primary-dark transition disabled:opacity-40">
                    {{ __('webapp.common.continue') }}
                </button>
            </div>
        </template>

        {{-- ── Step 2 — City ────────────────────────────────────────────── --}}
        <template x-if="step === 2">
            <div>
                <h1 class="font-anton text-[26px] leading-tight tracking-[.6px] text-ink">{{ __('webapp.attendee_onboarding.s2_title') }}</h1>
                <p class="text-sm text-muted mt-2">{{ __('webapp.attendee_onboarding.s2_sub') }}</p>

                <input x-model="citySearch" type="search" placeholder="{{ __('webapp.attendee_onboarding.city_search') }}"
                       class="mt-6 w-full h-12 px-4 rounded-xl bg-white border border-ink/[.10] focus:border-ink/30 text-[14px] outline-none transition">

                <div class="mt-3 flex flex-col gap-1.5 max-h-[320px] overflow-y-auto kb-scroll">
                    <template x-for="c in filteredCities" :key="c.id">
                        <button type="button" @click="form.city_id = (form.city_id === c.id ? null : c.id)"
                                class="flex items-center justify-between h-12 px-4 rounded-xl border text-[14px] text-left transition"
                                :class="form.city_id === c.id ? 'bg-primary-tint border-primary font-bold text-ink' : 'bg-white border-ink/[.10] text-body hover:border-ink/30'">
                            <span x-text="c.name"></span>
                            <span x-show="form.city_id === c.id" x-cloak>✓</span>
                        </button>
                    </template>
                    <p x-show="!citiesLoading && filteredCities.length === 0" x-cloak class="text-sm text-muted py-4 text-center">{{ __('webapp.attendee_onboarding.city_none') }}</p>
                </div>

                <div class="flex gap-2 mt-7">
                    <button type="button" @click="step = 1" class="h-12 px-5 rounded-pill bg-white border border-line text-sm font-bold hover:border-ink transition">{{ __('webapp.common.back') }}</button>
                    <button type="button" @click="step = 3" class="kb-on-yellow flex-1 h-12 rounded-pill bg-primary text-ink text-[15px] font-bold shadow-btn hover:bg-primary-dark transition">{{ __('webapp.common.continue') }}</button>
                </div>
                <button type="button" @click="step = 3" class="mt-2 w-full h-10 text-[13px] font-semibold text-muted hover:text-ink transition">{{ __('webapp.common.skip') }}</button>
            </div>
        </template>

        {{-- ── Step 3 — Interests ───────────────────────────────────────── --}}
        <template x-if="step === 3">
            <div>
                <h1 class="font-anton text-[26px] leading-tight tracking-[.6px] text-ink">{{ __('webapp.attendee_onboarding.s3_title') }}</h1>
                <p class="text-sm text-muted mt-2">{{ __('webapp.attendee_onboarding.s3_sub') }}</p>

                {{-- The same vocabulary communities pick from, so an attendee's
                     interests and a community's type are comparable strings. --}}
                <div class="flex gap-2 flex-wrap mt-6">
                    <template x-for="o in communityTypes" :key="o.value">
                        <button type="button" @click="toggleInterest(o.value)"
                                class="px-4 py-2.5 rounded-pill text-[13px] font-semibold border transition"
                                :class="form.interests.includes(o.value) ? 'bg-primary-tint border-primary text-ink' : 'bg-white border-ink/[.12] text-body hover:border-ink/30'"
                                x-text="o.label"></button>
                    </template>
                </div>

                <div class="flex gap-2 mt-7">
                    <button type="button" @click="step = 2" class="h-12 px-5 rounded-pill bg-white border border-line text-sm font-bold hover:border-ink transition">{{ __('webapp.common.back') }}</button>
                    <button type="button" @click="goToJoin()" class="kb-on-yellow flex-1 h-12 rounded-pill bg-primary text-ink text-[15px] font-bold shadow-btn hover:bg-primary-dark transition">{{ __('webapp.common.continue') }}</button>
                </div>
                <button type="button" @click="goToJoin()" class="mt-2 w-full h-10 text-[13px] font-semibold text-muted hover:text-ink transition">{{ __('webapp.common.skip') }}</button>
            </div>
        </template>

        {{-- ── Step 4 — Join ────────────────────────────────────────────── --}}
        <template x-if="step === 4">
            <div>
                <h1 class="font-anton text-[26px] leading-tight tracking-[.6px] text-ink">{{ __('webapp.attendee_onboarding.s4_title') }}</h1>
                <p class="text-sm text-muted mt-2">{{ __('webapp.attendee_onboarding.s4_sub') }}</p>

                <p x-show="communitiesLoading" x-cloak class="text-sm text-muted mt-6">{{ __('webapp.common.loading') }}</p>

                <div class="mt-6 flex flex-col gap-2">
                    <template x-for="c in communities" :key="c.id">
                        <button type="button" @click="toggleCommunity(c.id)"
                                class="flex items-center gap-3 p-3.5 rounded-2xl border text-left transition"
                                :class="form.community_ids.includes(c.id) ? 'bg-primary-tint border-primary' : 'bg-white border-ink/[.10] hover:border-ink/30'">
                            <span class="w-10 h-10 rounded-full bg-peach text-peach-ink font-bold flex items-center justify-center shrink-0 overflow-hidden">
                                <template x-if="c.logo_url"><img :src="c.logo_url" alt="" class="w-full h-full object-cover"></template>
                                <template x-if="!c.logo_url"><span x-text="window.kbInitial(c.name)"></span></template>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[14px] font-bold text-ink truncate" x-text="c.name"></span>
                                <span class="block text-[12.5px] text-muted truncate"
                                      x-text="[window.kbHumanize(c.type), c.city?.name].filter(Boolean).join(' · ')"></span>
                            </span>
                            <span class="shrink-0 text-[13px] font-bold"
                                  x-text="form.community_ids.includes(c.id) ? t('attendee_onboarding.joining') : t('attendee_onboarding.join')"></span>
                        </button>
                    </template>
                    <p x-show="!communitiesLoading && communities.length === 0" x-cloak
                       class="rounded-2xl border-[1.5px] border-dashed border-ink/20 py-10 text-center text-sm text-muted">{{ __('webapp.attendee_onboarding.no_communities') }}</p>
                </div>

                <div class="flex gap-2 mt-7">
                    <button type="button" @click="step = 3" class="h-12 px-5 rounded-pill bg-white border border-line text-sm font-bold hover:border-ink transition">{{ __('webapp.common.back') }}</button>
                    <button type="button" @click="submit()" :disabled="busy"
                            class="kb-on-yellow flex-1 h-12 rounded-pill bg-primary text-ink text-[15px] font-bold shadow-btn hover:bg-primary-dark transition disabled:opacity-50">
                        <span x-text="busy ? t('common.saving') : t('attendee_onboarding.finish')"></span>
                    </button>
                </div>
                {{-- Skipping still submits: the handle has to be claimed either way,
                     which is exactly what the mobile flow does on its Join step. --}}
                <button type="button" @click="submit()" :disabled="busy"
                        class="mt-2 w-full h-10 text-[13px] font-semibold text-muted hover:text-ink transition">{{ __('webapp.common.skip') }}</button>
            </div>
        </template>
    </div>
</div>

@push('scripts')
<script>
    function attendeeOnboarding() {
        return {
            step: 1, busy: false, error: '',
            form: { name: '', handle: '', city_id: null, interests: [], community_ids: [], photo: null },
            photoPreview: '',
            cities: [], citiesLoading: true, citySearch: '',
            communityTypes: [],
            communities: [], communitiesLoading: false, communitiesLoaded: false,
            handleState: '', handleSuggestions: [], handleTimer: null,

            get step1Ready() {
                return this.form.name.trim() !== '' && this.handleState === 'ok';
            },
            get handleHint() {
                if (this.handleState === 'bad') return t('attendee_onboarding.handle_bad');
                if (this.handleState === 'taken') return t('attendee_onboarding.handle_taken');
                return t('attendee_onboarding.handle_hint');
            },
            get filteredCities() {
                const q = this.citySearch.trim().toLowerCase();
                if (!q) return this.cities;
                return this.cities.filter(c => String(c.name || '').toLowerCase().includes(q));
            },

            async init() {
                if (!window.kb.requireAuth()) return;
                const me = await this.loadShell();
                if (!me) return;

                /*
                 * Only an attendee belongs here, and only one who has not finished.
                 * Anyone else is sent home rather than shown a flow that would
                 * overwrite their profile with attendee fields.
                 */
                if (!this.isAttendee) { window.nav('/dashboard'); return; }
                if (me.handle) { window.nav('/dashboard'); return; }

                // Prefill from whatever the account already has, so a re-run is a
                // continuation rather than a blank form.
                this.form.name = me.name || '';
                this.form.city_id = me.city?.id || me.city_id || null;
                this.form.interests = Array.isArray(me.interests) ? [...me.interests] : [];

                await Promise.all([this.loadCities(), this.loadCommunityTypes()]);
            },

            async loadCities() {
                const res = await window.kb.api('/cities?per_page=200', { auth: false });
                this.citiesLoading = false;
                if (res.ok) this.cities = window.kb.rows(res);
            },
            async loadCommunityTypes() {
                const res = await window.kb.api('/lookup/community-types', { auth: false });
                if (res.ok) this.communityTypes = window.kb.rows(res);
            },
            /** Loaded on demand: most people never reach step 4 in one sitting. */
            async goToJoin() {
                this.step = 4;
                if (this.communitiesLoaded) return;
                this.communitiesLoaded = true;
                this.communitiesLoading = true;
                const params = new URLSearchParams({ per_page: '12' });
                if (this.form.city_id) params.set('city_id', this.form.city_id);
                const res = await window.kb.api('/communities/discover?' + params.toString());
                this.communitiesLoading = false;
                if (res.ok) this.communities = window.kb.rows(res);
            },

            /**
             * Live handle check, debounced — the same 400ms the mobile handle field
             * uses, so the two clients feel the same and neither hammers the endpoint
             * on every keystroke.
             */
            onHandleInput() {
                const raw = this.form.handle.trim().toLowerCase();
                this.form.handle = raw;
                this.handleSuggestions = [];
                clearTimeout(this.handleTimer);

                if (raw === '') { this.handleState = ''; return; }
                if (!/^[a-z0-9_]{3,20}$/.test(raw)) { this.handleState = 'bad'; return; }

                this.handleState = 'checking';
                this.handleTimer = setTimeout(async () => {
                    const res = await window.kb.api('/handle/available?handle=' + encodeURIComponent(raw), { auth: false });
                    // A slow response for a handle the user has since edited must not
                    // overwrite the state of what they are typing now.
                    if (this.form.handle !== raw) return;
                    if (!res.ok) { this.handleState = ''; return; }
                    const data = res.json?.data || {};
                    this.handleState = data.available ? 'ok' : 'taken';
                    this.handleSuggestions = data.available ? [] : (data.suggestions || []).slice(0, 3);
                }, 400);
            },
            useSuggestion(handle) {
                this.form.handle = handle;
                this.handleSuggestions = [];
                this.onHandleInput();
            },

            toggleInterest(v) {
                this.form.interests = this.form.interests.includes(v)
                    ? this.form.interests.filter(i => i !== v)
                    : [...this.form.interests, v];
            },
            toggleCommunity(id) {
                this.form.community_ids = this.form.community_ids.includes(id)
                    ? this.form.community_ids.filter(i => i !== id)
                    : [...this.form.community_ids, id];
            },

            /** Photo travels as a data-URI, the shape the API and the mobile client both use. */
            pickPhoto(event) {
                const file = event.target.files?.[0];
                if (!file) return;
                if (file.size > 5 * 1024 * 1024) { this.error = t('attendee_onboarding.photo_too_big'); return; }
                const reader = new FileReader();
                reader.onload = () => {
                    this.form.photo = String(reader.result || '');
                    this.photoPreview = this.form.photo;
                };
                reader.readAsDataURL(file);
            },

            async submit() {
                this.error = '';
                if (!this.step1Ready) { this.step = 1; return; }

                this.busy = true;
                /*
                 * Optional keys are omitted when empty rather than sent as null. A
                 * re-run of onboarding must not blank a city or a photo the attendee
                 * set earlier — the same reason the mobile payload builder omits them.
                 */
                const body = {
                    name: this.form.name.trim(),
                    handle: this.form.handle.trim().toLowerCase(),
                    interests: this.form.interests,
                    community_ids: this.form.community_ids,
                };
                if (this.form.city_id) body.city_id = this.form.city_id;
                if (this.form.photo) body.photo = this.form.photo;

                const res = await window.kb.api('/onboarding/attendee', { method: 'PUT', body });
                this.busy = false;

                if (!res.ok) {
                    this.error = window.kb.errorText(res, t('attendee_onboarding.error'));
                    // A taken handle is the one failure that needs the user back on
                    // step 1 rather than staring at an error on the Join step.
                    if (res.json?.errors?.handle) { this.step = 1; this.handleState = 'taken'; }
                    return;
                }

                window.nav('/dashboard');
            },
        };
    }
</script>
@endpush
@endsection
