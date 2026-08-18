{{-- Login overlay. Login is a modal, never its own page load, so arriving from the
     marketing site drops straight onto the app behind a sheet instead of a bare form.
     Sign-up stays a full page (/register) — it is a multi-step flow.
     Behaviour lives in window.kbLoginModal(); merge it into the host component:
       x-data="kbMerge(kbLoginModal(), somePage())" --}}

<div x-show="loginOpen" x-cloak @click="closeLogin()" @keydown.escape.window="closeLogin()"
     class="kb-overlay fixed inset-0 z-[80] flex items-center justify-center p-4 sm:p-8"
     role="dialog" aria-modal="true" aria-label="{{ __('webapp.login.heading') }}">
    <div @click.stop
         class="bg-white rounded-[22px] w-full max-w-[420px] max-h-[92vh] overflow-y-auto kb-scroll px-7 py-8 kb-fade-up-fast">

        <div class="flex items-start justify-between gap-3">
            <img src="/webapp-assets/wordmark-light.png" alt="Kolabing" class="w-[124px]">
            <button type="button" @click="closeLogin()"
                    class="w-9 h-9 rounded-full bg-cream-low hover:bg-cream-low-hover transition flex items-center justify-center shrink-0"
                    aria-label="{{ __('webapp.common.close') }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <h2 class="font-anton text-[28px] text-ink mt-5">{{ __('webapp.login.heading') }}</h2>
        <p class="text-sm text-body mt-1">{{ __('webapp.login.subtitle') }}</p>

        <template x-if="loginError">
            <div class="mt-4 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="loginError"></div>
        </template>

        <form @submit.prevent="submitLogin()" class="flex flex-col gap-3.5 mt-5">
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-body">{{ __('webapp.login.email') }}</label>
                <input x-model="email" type="email" required autocomplete="email" x-ref="loginEmail"
                       placeholder="{{ __('webapp.login.email_placeholder') }}"
                       class="h-[52px] rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-body">{{ __('webapp.login.password') }}</label>
                <input x-model="password" type="password" required autocomplete="current-password"
                       placeholder="{{ __('webapp.login.password_placeholder') }}"
                       class="h-[52px] rounded-2xl border border-transparent bg-cream-input px-4 text-sm text-ink">
            </div>
            <button type="submit" :disabled="loginLoading"
                    class="mt-1 h-14 rounded-pill bg-primary text-ink text-base font-bold shadow-btn hover:bg-primary-dark hover:-translate-y-px transition disabled:opacity-50">
                <span x-text="loginLoading ? t('login.submitting') : t('login.submit')">{{ __('webapp.login.submit') }}</span>
            </button>
        </form>

        {{-- Google sign-in --}}
        <div class="mt-5">
            <div class="flex items-center gap-3 text-muted text-xs mb-3">
                <span class="h-px bg-ink/10 flex-1"></span>{{ __('webapp.login.or') }}<span class="h-px bg-ink/10 flex-1"></span>
            </div>

            <p class="text-[11px] font-semibold tracking-[.4px] uppercase text-muted text-center mb-2">{{ __('webapp.login.google_role_hint') }}</p>
            <div class="flex p-1 bg-white border border-ink/[.12] rounded-pill mb-3">
                <button type="button" @click="userType = 'business'"
                        class="flex-1 h-8 rounded-pill text-xs font-bold tracking-wide transition"
                        :class="userType === 'business' ? 'bg-primary text-ink' : 'text-muted'">{{ __('webapp.login.business') }}</button>
                <button type="button" @click="userType = 'community'"
                        class="flex-1 h-8 rounded-pill text-xs font-bold tracking-wide transition"
                        :class="userType === 'community' ? 'bg-primary text-ink' : 'text-muted'">{{ __('webapp.login.community') }}</button>
            </div>

            <div x-show="hasGoogle" x-cloak class="w-full max-w-[400px] mx-auto">
                {{-- Google renders inside its own card; clipping it to the design's
                     pill and giving the shell the surface colour stops that card
                     showing as a pale frame in dark theme. --}}
                <div id="googleBtn" class="rounded-pill overflow-hidden bg-white flex justify-center min-h-[44px]"></div>
            </div>

            {{-- Without GOOGLE_CLIENT_ID_WEB the widget cannot load — say so instead of
                 silently dropping the option off the sheet. --}}
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
        </div>

        {{-- Sign-up is a multi-step flow, so it gets its own page. --}}
        <p class="text-center text-sm text-muted mt-6">
            {{ __('webapp.login.new_here') }}
            <a href="{{ $base }}/register" class="font-semibold text-ink hover:underline">{{ __('webapp.login.create_account') }}</a>
        </p>
    </div>
</div>

@push('scripts')
<script>
    /** Login overlay behaviour, shared by the hero and the /login URL. */
    window.kbLoginModal = function () {
        return {
            loginOpen: false, email: '', password: '', loginLoading: false, loginError: '',
            userType: 'business',
            hasGoogle: !!window.KB_CONFIG.googleClientId,
            googleLoaded: false,

            openLogin() {
                this.loginOpen = true;
                this.loginError = '';
                // GSI needs its container attached and laid out before it will render.
                this.$nextTick(() => {
                    if (this.hasGoogle) this.loadGoogle();
                    this.$refs.loginEmail?.focus();
                });
            },
            // Both hosts render the hero behind the sheet, so closing simply reveals it.
            closeLogin() { this.loginOpen = false; },
            async submitLogin() {
                this.loginError = ''; this.loginLoading = true;
                const res = await window.kb.api('/auth/login', {
                    method: 'POST', auth: false, body: { email: this.email, password: this.password },
                });
                this.loginLoading = false;
                if (res.ok && res.json?.data?.token) {
                    window.kb.setSession(res.json.data);
                    window.nav('/dashboard');
                    return;
                }
                this.loginError = window.kb.errorText(res, t('login.error'));
            },
            async loadGoogle() {
                if (this.googleLoaded) return;
                this.googleLoaded = true;
                // Bad/blocked client id or a blocked GSI script falls back to the
                // inert button + notice rather than leaving an empty gap.
                this.hasGoogle = await this.renderGoogle();
                if (!this.hasGoogle) return;
                // Re-render on a theme flip or a resize so Google's own card never
                // ends up the wrong colour or the wrong width for its row.
                const again = () => { if (this.loginOpen) this.renderGoogle(); };
                window.addEventListener('kb:theme', again);
                window.addEventListener('resize', again);
            },
            renderGoogle() {
                return window.kbGoogle.render(document.getElementById('googleBtn'), {
                    text: 'continue_with',
                    dark: this.isDark,
                    onCredential: (resp) => this.onGoogle(resp),
                });
            },
            postGoogle(idToken, userType) {
                return window.kb.api('/auth/google', {
                    method: 'POST', auth: false,
                    body: { id_token: idToken, user_type: userType },
                });
            },
            async onGoogle(resp) {
                this.loginError = '';
                let res = await this.postGoogle(resp.credential, this.userType);

                // `user_type` is required by the API, but for an account that already
                // exists the account's own type is what counts — the toggle only
                // decides the type of a *new* account. An existing user of the other
                // type would otherwise be refused with "User type mismatch" (409) and
                // no hint that a pill above the button caused it, so retry once as the
                // other type. It can only succeed for an account that is already theirs.
                if (res.status === 409) {
                    const other = this.userType === 'business' ? 'community' : 'business';
                    const retry = await this.postGoogle(resp.credential, other);
                    if (retry.ok) { this.userType = other; res = retry; }
                }

                if (res.ok && res.json?.data?.token) {
                    window.kb.setSession(res.json.data);
                    // A brand-new business lands on the plan; everyone else on the dashboard.
                    window.nav(res.json.data.is_new_user && this.userType === 'business' ? '/subscription?reason=welcome' : '/dashboard');
                    return;
                }
                this.loginError = window.kb.errorText(res, t('login.google_error'));
            },
        };
    };
</script>
@endpush
