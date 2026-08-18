@extends('webapp.layout')
@section('title', __('webapp.meta.register_title'))
@section('description', __('webapp.meta.register_description'))
@section('robots', 'index,follow')

@section('body')
<div class="min-h-screen bg-cream-alt flex items-center justify-center px-6 py-10" x-data="kbMerge(kbThemeState(), registerPage())" x-init="init()">

    {{-- ── Step 1 · pick a role ─────────────────────────────────────────── --}}
    <div x-show="step === 'type'" class="w-full max-w-[480px] flex flex-col gap-4">
        <a href="{{ $base }}/" class="w-10 h-10 rounded-full bg-white border border-ink/10 hover:border-ink/30 transition flex items-center justify-center shrink-0" aria-label="{{ __('webapp.common.back') }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
        </a>
        <h1 class="font-anton text-[32px] text-ink mt-2">{{ __('webapp.register.heading') }}</h1>
        <p class="text-sm text-body">{{ __('webapp.register.subheading') }}</p>

        <button type="button" @click="pickRole('business')"
                class="mt-3 text-left p-5 rounded-[20px] bg-white border border-ink/10 hover:border-primary hover:bg-primary-tint transition flex items-center gap-3.5">
            <span class="w-11 h-11 rounded-[14px] bg-cream-low flex items-center justify-center shrink-0">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg>
            </span>
            <span>
                <span class="block text-[15px] font-bold text-ink">{{ __('webapp.register.business_title') }}</span>
                <span class="block text-[13px] text-muted mt-0.5">{{ __('webapp.register.business_sub') }}</span>
            </span>
        </button>

        <button type="button" @click="pickRole('community')"
                class="text-left p-5 rounded-[20px] bg-white border border-ink/10 hover:border-primary hover:bg-primary-tint transition flex items-center gap-3.5">
            <span class="w-11 h-11 rounded-[14px] bg-cream-low flex items-center justify-center shrink-0">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </span>
            <span>
                <span class="block text-[15px] font-bold text-ink">{{ __('webapp.register.community_title') }}</span>
                <span class="block text-[13px] text-muted mt-0.5">{{ __('webapp.register.community_sub') }}</span>
            </span>
        </button>

        <p class="text-center text-sm text-muted mt-2">
            {{ __('webapp.register.have_account') }}
            <a href="{{ $base }}/login" class="font-semibold text-ink hover:underline">{{ __('webapp.register.login') }}</a>
        </p>
    </div>

    {{-- ── Step 2 · account details ─────────────────────────────────────── --}}
    <div x-show="step === 'account'" class="w-full max-w-[460px] flex flex-col gap-[13px]" x-cloak>
        <button type="button" @click="step = 'type'" class="w-10 h-10 rounded-full bg-white border border-ink/10 hover:border-ink/30 transition flex items-center justify-center shrink-0" aria-label="{{ __('webapp.common.back') }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
        </button>
        <h1 class="font-anton text-[30px] text-ink">{{ __('webapp.register.account_heading') }}</h1>
        <p class="text-sm text-body -mt-1.5">
            <span x-text="t('register.joining_as')"></span>
            <span class="font-bold" x-text="roleLabel"></span> ·
            <a href="#" @click.prevent="step = 'type'" class="text-ink underline">{{ __('webapp.register.change') }}</a>
        </p>

        <template x-if="error">
            <div class="rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
        </template>

        <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-body" x-text="nameLabel"></label>
            <input x-model="form.name" type="text" maxlength="255" :placeholder="namePlaceholder"
                   class="h-[50px] rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
        </div>
        <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-body">{{ __('webapp.login.email') }}</label>
            <input x-model="form.email" type="email" autocomplete="email" placeholder="you@example.com"
                   class="h-[50px] rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
        </div>
        <div class="flex gap-3">
            <div class="flex-1 flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-body">{{ __('webapp.register.phone') }} <span class="font-normal text-muted">({{ __('webapp.common.optional') }})</span></label>
                <input x-model="form.phone_number" type="tel" placeholder="+34 123 456 789"
                       class="h-[50px] w-full rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
            </div>
            <div class="flex-1 flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-body">{{ __('webapp.login.password') }}</label>
                <input x-model="form.password" type="password" autocomplete="new-password" placeholder="{{ __('webapp.register.password_hint') }}"
                       class="h-[50px] w-full rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
            </div>
        </div>
        <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-body">{{ __('webapp.register.password_confirm') }}</label>
            <input x-model="form.password_confirmation" type="password" autocomplete="new-password"
                   class="h-[50px] rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
        </div>
        <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-body">
                {{ __('webapp.register.invite_code') }}
                <span class="font-normal text-muted" x-text="inviteHint"></span>
            </label>
            <input x-model="form.referral_code" type="text" placeholder="{{ __('webapp.register.invite_placeholder') }}"
                   class="h-[50px] rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink uppercase">
        </div>

        <button type="button" @click="goDetails()"
                class="kb-on-yellow mt-1.5 h-14 rounded-pill bg-primary text-ink text-base font-bold shadow-btn hover:bg-primary-dark hover:-translate-y-px transition">{{ __('webapp.common.continue') }}</button>

        {{-- Google sign-up: skips the password + profile steps entirely (the API
             creates the profile from the Google identity + the role picked above). --}}
        <div class="mt-2">
            <div class="flex items-center gap-3 text-muted text-xs mb-3">
                <span class="h-px bg-ink/10 flex-1"></span>{{ __('webapp.login.or') }}<span class="h-px bg-ink/10 flex-1"></span>
            </div>
            <div x-show="hasGoogle" x-cloak class="w-full max-w-[400px] mx-auto">
                {{-- Google renders inside its own card; clipping it to the design's
                     pill and giving the shell the surface colour stops that card
                     showing as a pale frame in dark theme. --}}
                <div id="googleBtn" class="rounded-pill overflow-hidden bg-white flex justify-center min-h-[44px]"></div>
            </div>
            <template x-if="!hasGoogle">
                <div>
                    <div class="w-full h-11 rounded-pill bg-white border border-line flex items-center justify-center gap-2.5 opacity-60 cursor-not-allowed select-none">
                        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
                            <path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.7-1.57 2.68-3.88 2.68-6.62z"/>
                            <path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.8.54-1.84.86-3.04.86-2.34 0-4.32-1.58-5.03-3.7H.96v2.33A9 9 0 0 0 9 18z"/>
                            <path fill="#FBBC05" d="M3.97 10.72a5.4 5.4 0 0 1 0-3.44V4.95H.96a9 9 0 0 0 0 8.1l3.01-2.33z"/>
                            <path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58C13.46.89 11.43 0 9 0A9 9 0 0 0 .96 4.95l3.01 2.33C4.68 5.16 6.66 3.58 9 3.58z"/>
                        </svg>
                        <span class="text-sm font-bold text-ink">{{ __('webapp.login.google_continue') }}</span>
                    </div>
                    <p class="text-[11px] text-muted text-center mt-2">{{ __('webapp.login.google_unavailable') }}</p>
                </div>
            </template>
            <p x-show="hasGoogle" x-cloak class="text-[11px] text-muted text-center mt-2" x-text="t('register.google_hint', { role: roleLabel })"></p>
        </div>
    </div>

    {{-- ── Step 3 · profile the API needs ───────────────────────────────── --}}
    <div x-show="step === 'details'" class="w-full max-w-[460px] flex flex-col gap-[13px]" x-cloak>
        <button type="button" @click="step = 'account'" class="w-10 h-10 rounded-full bg-white border border-ink/10 hover:border-ink/30 transition flex items-center justify-center shrink-0" aria-label="{{ __('webapp.common.back') }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
        </button>
        <h1 class="font-anton text-[30px] text-ink" x-text="detailsHeading"></h1>
        <p class="text-sm text-body -mt-1.5">{{ __('webapp.register.details_sub') }}</p>

        <template x-if="error">
            <div class="rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
        </template>

        {{-- Business: categories --}}
        <template x-if="role === 'business'">
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-body">{{ __('webapp.register.categories') }} <span class="font-normal text-muted">{{ __('webapp.account.categories_hint') }}</span></label>
                <div class="flex flex-wrap gap-2 mt-1">
                    <template x-for="o in businessTypes" :key="o.value">
                        <button type="button" @click="toggleCategory(o.value)"
                                class="px-4 py-2.5 rounded-pill text-[13px] font-semibold border transition"
                                :class="form.categories.includes(o.value) ? 'bg-primary-tint border-primary text-ink' : 'bg-white border-ink/[.12] text-ink'"
                                x-text="o.label"></button>
                    </template>
                </div>
            </div>
        </template>

        {{-- Community: type + size --}}
        <template x-if="role === 'community'">
            <div class="flex flex-col gap-[13px]">
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-semibold text-body">{{ __('webapp.account.community_type') }}</label>
                    <select x-model="form.community_type" class="h-[50px] rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
                        <option value="">{{ __('webapp.common.select') }}</option>
                        <template x-for="o in communityTypes" :key="o.value">
                            <option :value="o.value" x-text="o.label"></option>
                        </template>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-semibold text-body">{{ __('webapp.account.community_size') }} <span class="font-normal text-muted">({{ __('webapp.common.optional') }})</span></label>
                    <input x-model.number="form.community_size" type="number" min="1" placeholder="120"
                           class="h-[50px] rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
                </div>
            </div>
        </template>

        {{-- City (both roles) --}}
        <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-body">{{ __('webapp.account.city') }}</label>
            <select x-model="form.city_id" class="h-[50px] rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
                <option value="">{{ __('webapp.common.select') }}</option>
                <template x-for="c in cities" :key="c.id">
                    <option :value="c.id" x-text="c.name"></option>
                </template>
            </select>
        </div>

        {{-- Business: venue path --}}
        <template x-if="role === 'business'">
            <div class="flex flex-col gap-2.5">
                <label class="text-xs font-semibold text-body">{{ __('webapp.register.has_venue') }}</label>
                <div class="flex flex-col gap-2.5">
                    <template x-for="opt in venueOptions" :key="opt.value">
                        <button type="button" @click="form.has_venue = opt.value"
                                class="text-left flex items-center gap-3 p-4 rounded-xl border transition"
                                :class="form.has_venue === opt.value ? 'bg-primary-tint border-primary' : 'bg-white border-ink/10'">
                            <span class="w-6 h-6 rounded-full shrink-0 flex items-center justify-center border-[1.5px]"
                                  :class="kb-on-yellow form.has_venue === opt.value ? 'kb-on-yellow bg-primary border-primary' : 'border-ink/20'">
                                <template x-if="form.has_venue === opt.value">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                </template>
                            </span>
                            <span>
                                <span class="block text-sm text-ink" :class="form.has_venue === opt.value ? 'font-bold' : 'font-medium'" x-text="opt.title"></span>
                                <span class="block text-xs text-muted mt-0.5" x-text="opt.sub"></span>
                            </span>
                        </button>
                    </template>
                </div>
            </div>
        </template>

        {{-- Business venue detail (required by the API when has_venue = true) --}}
        <template x-if="role === 'business' && form.has_venue === true">
            <div class="flex flex-col gap-[13px] rounded-[20px] bg-white border border-ink/10 p-4">
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-semibold text-body">{{ __('webapp.register.venue_name') }}</label>
                    <input x-model="form.venue.name" type="text" maxlength="255"
                           class="h-[50px] rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
                </div>
                <div class="flex gap-3">
                    <div class="flex-1 flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-body">{{ __('webapp.register.venue_type') }}</label>
                        <select x-model="form.venue.venue_type" class="h-[50px] w-full rounded-2xl border border-transparent bg-cream-input px-3 text-sm text-ink">
                            <option value="">{{ __('webapp.common.select') }}</option>
                            <template x-for="o in venueTypes" :key="o.value">
                                <option :value="o.value" x-text="o.label"></option>
                            </template>
                        </select>
                    </div>
                    <div class="w-[130px] flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-body">{{ __('webapp.register.capacity') }}</label>
                        <input x-model.number="form.venue.capacity" type="number" min="1" placeholder="40"
                               class="h-[50px] w-full rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
                    </div>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-semibold text-body">{{ __('webapp.register.venue_address') }}</label>
                    <input x-model="form.venue.formatted_address" type="text" maxlength="500"
                           class="h-[50px] rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
                </div>
            </div>
        </template>

        <label class="flex items-start gap-3 mt-1 cursor-pointer">
            <input x-model="form.accepted_terms" type="checkbox" class="mt-0.5 w-5 h-5 rounded-md border-ink/20 text-ink focus:ring-0">
            <span class="text-[13px] text-body leading-snug">{!! __('webapp.register.terms', ['terms' => '<a href="https://kolabing.com/terms" target="_blank" rel="noopener" class="font-semibold text-ink underline">'.__('webapp.register.terms_word').'</a>', 'privacy' => '<a href="https://kolabing.com/privacy" target="_blank" rel="noopener" class="font-semibold text-ink underline">'.__('webapp.register.privacy_word').'</a>']) !!}</span>
        </label>

        <button type="button" @click="submit()" :disabled="busy"
                class="kb-on-yellow mt-1.5 h-14 rounded-pill bg-primary text-ink text-base font-bold shadow-btn hover:bg-primary-dark hover:-translate-y-px transition disabled:opacity-50">
            <span x-text="busy ? t('register.creating') : t('register.create_account')">{{ __('webapp.register.create_account') }}</span>
        </button>
    </div>
</div>

@push('scripts')
<script>
    function registerPage() {
        return {
            step: 'type', role: 'business', busy: false, error: '',
            hasGoogle: !!window.KB_CONFIG.googleClientId, googleLoaded: false,
            businessTypes: [], communityTypes: [], venueTypes: [], cities: [],
            form: {
                name: '', email: '', phone_number: '', password: '', password_confirmation: '',
                referral_code: '', accepted_terms: false,
                categories: [], community_type: '', community_size: null,
                city_id: '', has_venue: null,
                venue: { name: '', venue_type: '', capacity: null, formatted_address: '' },
            },
            get roleLabel() { return this.role === 'business' ? t('register.as_business') : t('register.as_community'); },
            get nameLabel() { return this.role === 'business' ? t('account.business_name') : t('account.community_name'); },
            get namePlaceholder() { return this.role === 'business' ? t('register.business_name_ph') : t('register.community_name_ph'); },
            get inviteHint() { return this.role === 'business' ? t('register.invite_hint_business') : t('register.invite_hint_community'); },
            get detailsHeading() { return this.role === 'business' ? t('register.details_business') : t('register.details_community'); },
            get venueOptions() {
                return [
                    { value: true,  title: t('register.venue_yes'), sub: t('register.venue_yes_sub') },
                    { value: false, title: t('register.venue_no'),  sub: t('register.venue_no_sub') },
                ];
            },
            /** Plan preselected on the public pricing page (?plan=…), carried to checkout. */
            plan: '',
            init() {
                if (!window.kb.requireGuest()) return;
                const params = new URLSearchParams(location.search);
                const q = params.get('type');
                if (q === 'business' || q === 'community') {
                    this.role = q; this.step = 'account';
                    if (this.hasGoogle) this.$nextTick(() => this.loadGoogle());
                }
                const plan = params.get('plan');
                if (plan === 'monthly' || plan === 'three_months') this.plan = plan;
                this.loadLookups();
            },
            /** Where a freshly registered account lands: businesses go straight to the offer. */
            postAuthPath() {
                if (this.role !== 'business') return '/dashboard';
                return '/subscription?reason=welcome' + (this.plan ? '&plan=' + this.plan : '');
            },
            async loadLookups() {
                const [bt, ct, vt, ci] = await Promise.all([
                    window.kb.api('/lookup/business-types', { auth: false }),
                    window.kb.api('/lookup/community-types', { auth: false }),
                    window.kb.api('/lookup/venue-types', { auth: false }),
                    window.kb.api('/cities', { auth: false }),
                ]);
                if (bt.ok) this.businessTypes = window.kb.rows(bt);
                if (ct.ok) this.communityTypes = window.kb.rows(ct);
                if (vt.ok) this.venueTypes = window.kb.rows(vt);
                if (ci.ok) this.cities = window.kb.rows(ci).filter(c => c.id && c.id !== 'other');
            },
            pickRole(role) {
                this.role = role; this.error = ''; this.step = 'account';
                // The GSI widget can only render once its container is on screen.
                if (this.hasGoogle) this.$nextTick(() => this.loadGoogle());
            },
            async loadGoogle() {
                if (this.googleLoaded) return;
                this.googleLoaded = true;
                this.hasGoogle = await this.renderGoogle();
                if (!this.hasGoogle) return;
                const again = () => { if (this.step === 'account') this.renderGoogle(); };
                window.addEventListener('kb:theme', again);
                window.addEventListener('resize', again);
            },
            renderGoogle() {
                return window.kbGoogle.render(document.getElementById('googleBtn'), {
                    text: 'signup_with',
                    dark: this.isDark,
                    onCredential: (resp) => this.onGoogle(resp),
                });
            },
            async onGoogle(resp) {
                this.error = '';
                // Google sign-up carries the role picked in step 1; the API creates the
                // extended profile, so the remaining steps are not needed.
                let res = await window.kb.api('/auth/google', {
                    method: 'POST', auth: false,
                    body: { id_token: resp.credential, user_type: this.role },
                });

                // Someone who already has an account (of the other type) lands here by
                // clicking "sign up" — the API answers 409 "User type mismatch". Sign
                // them in as what they actually are instead of refusing them.
                if (res.status === 409) {
                    const other = this.role === 'business' ? 'community' : 'business';
                    const retry = await window.kb.api('/auth/google', {
                        method: 'POST', auth: false,
                        body: { id_token: resp.credential, user_type: other },
                    });
                    if (retry.ok) { this.role = other; res = retry; }
                }

                if (res.ok && res.json?.data?.token) {
                    window.kb.setSession(res.json.data);
                    const code = (this.form.referral_code || '').trim().toUpperCase();
                    if (code) localStorage.setItem('kolabing_referral', code);
                    window.nav(this.postAuthPath());
                    return;
                }
                this.error = window.kb.errorText(res, t('register.error'));
            },
            toggleCategory(value) {
                const arr = this.form.categories;
                const i = arr.indexOf(value);
                if (i !== -1) arr.splice(i, 1);
                else if (arr.length < 3) arr.push(value);
            },
            goDetails() {
                this.error = '';
                const f = this.form;
                if (!f.name.trim()) { this.error = t('register.err_name'); return; }
                if (!f.email.trim()) { this.error = t('register.err_email'); return; }
                if ((f.password || '').length < 8) { this.error = t('register.err_password'); return; }
                if (f.password !== f.password_confirmation) { this.error = t('register.err_password_match'); return; }
                this.step = 'details';
            },
            payload() {
                const f = this.form;
                const base = {
                    email: f.email.trim(),
                    password: f.password,
                    password_confirmation: f.password_confirmation,
                    accepted_terms: true,
                    name: f.name.trim(),
                };
                // The API wants E.164 — drop spacing the user typed.
                const phone = (f.phone_number || '').replace(/[\s()-]/g, '');
                if (phone) base.phone_number = phone;

                if (this.role === 'community') {
                    return { ...base, community_type: f.community_type, community_size: f.community_size || null, city_id: f.city_id };
                }
                const b = { ...base, categories: f.categories, has_venue: !!f.has_venue, city_id: f.city_id };
                if (f.has_venue === true) {
                    const city = this.cities.find(c => c.id === f.city_id);
                    b.primary_venue = {
                        name: f.venue.name,
                        venue_type: f.venue.venue_type,
                        capacity: f.venue.capacity,
                        formatted_address: f.venue.formatted_address,
                        city: city?.name || '',
                    };
                }
                return b;
            },
            async submit() {
                this.error = '';
                const f = this.form;
                if (!f.accepted_terms) { this.error = t('register.err_terms'); return; }
                if (!f.city_id) { this.error = t('register.err_city'); return; }
                if (this.role === 'community' && !f.community_type) { this.error = t('register.err_community_type'); return; }
                if (this.role === 'business' && f.categories.length === 0) { this.error = t('register.err_categories'); return; }
                if (this.role === 'business' && f.has_venue === null) { this.error = t('register.err_has_venue'); return; }

                this.busy = true;
                const path = this.role === 'business' ? '/auth/register/business' : '/auth/register/community';
                const res = await window.kb.api(path, { method: 'POST', auth: false, body: this.payload() });
                this.busy = false;

                if (res.ok && res.json?.data?.token) {
                    window.kb.setSession(res.json.data);
                    // The referral code can only be validated once authenticated, and it is
                    // redeemed at checkout — stash it so /subscription can prefill + apply it.
                    const code = (f.referral_code || '').trim().toUpperCase();
                    if (code) localStorage.setItem('kolabing_referral', code);
                    window.nav(this.postAuthPath());
                    return;
                }
                this.error = window.kb.errorText(res, t('register.error'));
            },
        };
    }
</script>
@endpush
@endsection
