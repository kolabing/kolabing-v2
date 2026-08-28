@extends('webapp.layout')
@section('title', $community->name)
@section('description', $metaDescription)
@section('robots', $isInviteOnly ? 'noindex,nofollow' : 'index,follow')

@section('body')
{{--
    Public invitation landing page. Lives on the APP host, not marketing: Alpine
    compiles every x-* expression with new Function, and Google Identity Services
    needs accounts.google.com — the CSP grants both only to config('webapp.host').
    On the marketing host this page rendered a completely empty CTA (BE-NF-38).

    No sidebar: this is a front door, like login.blade.php.
--}}
<div class="min-h-screen bg-cream-alt"
     x-data="kbMerge(kbThemeState(), communityJoinPage())" x-init="init()">

    <div class="max-w-[560px] mx-auto px-5 py-10 md:py-16">

        <a href="{{ rtrim(config('webapp.url'), '/') }}" class="inline-block mb-8">
            <x-k-mark :size="24" />
        </a>

        <div class="bg-white border border-ink/[.08] rounded-[28px] p-7 md:p-9 shadow-card">

            {{-- ── Community identity (always visible) ─────────────────── --}}
            <div class="flex flex-col items-center text-center">
                @if ($logo)
                    <img src="{{ $logo }}" alt="" class="w-20 h-20 rounded-full object-cover ring-4 ring-primary/30">
                @else
                    <div class="w-20 h-20 rounded-full bg-primary flex items-center justify-center text-2xl font-black uppercase text-ink">
                        {{ mb_substr($community->name, 0, 1) }}
                    </div>
                @endif

                <h1 class="mt-5 font-anton text-[26px] tracking-[.5px] text-ink">{{ $community->name }}</h1>

                <p class="mt-1.5 text-[12px] font-semibold uppercase tracking-[.16em] text-muted">
                    {{ str_replace('_', ' ', (string) $community->type) }}
                    <span aria-hidden="true">·</span>
                    {{ trans_choice('{0}No members yet|{1}:count member|[2,*]:count members', $memberCount, ['count' => $memberCount]) }}
                </p>

                @if ($community->description)
                    <p class="mt-4 text-sm text-body leading-relaxed">{{ $community->description }}</p>
                @endif
            </div>

            {{-- ── Tier ladder ─────────────────────────────────────────── --}}
            @if ($community->tiers->isNotEmpty())
                <div class="mt-7 pt-6 border-t border-ink/[.08]">
                    <p class="text-[11px] font-semibold uppercase tracking-[.16em] text-muted">{{ __('webapp.join.levels') }}</p>
                    <ul class="mt-3 flex flex-wrap gap-2">
                        @foreach ($community->tiers as $tier)
                            <li class="inline-flex items-center gap-2 rounded-pill border border-ink/[.12] px-3.5 py-1.5 text-[12.5px] font-bold">
                                <span class="w-2.5 h-2.5 shrink-0 rounded-full" style="background: {{ $tier->color ?: '#FFE28C' }}"></span>
                                {{ $tier->name }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ── Upcoming public events ──────────────────────────────── --}}
            @if ($events->isNotEmpty())
                <div class="mt-6 pt-6 border-t border-ink/[.08]">
                    <p class="text-[11px] font-semibold uppercase tracking-[.16em] text-muted">{{ __('webapp.join.upcoming') }}</p>
                    <ul class="mt-3 flex flex-col gap-2">
                        @foreach ($events as $event)
                            <li class="flex items-baseline justify-between gap-4 rounded-2xl bg-cream-low px-4 py-3">
                                <span class="font-semibold text-[13.5px] text-ink">{{ $event->name }}</span>
                                <span class="shrink-0 text-[12px] text-muted">{{ $event->event_date->translatedFormat('j M Y') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-7 pt-6 border-t border-ink/[.08]">

                <template x-if="error">
                    <div class="mb-4 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3 whitespace-pre-line" x-text="error"></div>
                </template>

                {{-- ══ PHASE: intro ═════════════════════════════════════ --}}
                <template x-if="phase === 'intro'">
                    <div>
                        {{-- Already signed in: one button, no form. --}}
                        <template x-if="signedIn">
                            <button type="button" @click="joinNow()" :disabled="busy"
                                    class="w-full h-12 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark transition disabled:opacity-50"
                                    x-text="busy ? '{{ __('webapp.common.saving') }}' : ctaLabel"></button>
                        </template>

                        {{-- Signed out: Google, then the profile form. --}}
                        <template x-if="!signedIn">
                            <div>
                                <p class="text-sm text-body text-center" x-text="ctaLead"></p>

                                <div x-ref="googleButton" class="mt-4 flex justify-center min-h-[44px]"></div>

                                {{-- GSI blocked (extension, network, no client id): never a dead button. --}}
                                <template x-if="googleFailed">
                                    <a :href="loginUrl"
                                       class="mt-2 w-full h-12 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn flex items-center justify-center">
                                        {{ __('webapp.join.sign_in') }}
                                    </a>
                                </template>

                                <p class="mt-4 text-[11px] text-muted text-center leading-relaxed">{{ __('webapp.join.terms_notice') }}</p>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- ══ PHASE: profile form ══════════════════════════════ --}}
                <template x-if="phase === 'profile'">
                    <div>
                        <p class="font-anton text-[19px] tracking-[.5px] text-ink">{{ __('webapp.join.about_you') }}</p>
                        <p class="mt-1.5 text-[12.5px] text-muted">{{ __('webapp.join.about_you_help') }}</p>

                        <div class="mt-5 flex items-center gap-4">
                            <template x-if="photoPreview">
                                <img :src="photoPreview" alt="" class="w-16 h-16 rounded-full object-cover shrink-0">
                            </template>
                            <template x-if="!photoPreview">
                                <div class="w-16 h-16 rounded-full bg-cream-low flex items-center justify-center text-lg font-bold text-muted shrink-0"
                                     x-text="window.kbInitial(form.name || email)"></div>
                            </template>
                            <label class="h-10 px-4 rounded-pill bg-white border border-ink/[.12] text-[13px] font-bold flex items-center cursor-pointer hover:border-ink/30 transition">
                                <input type="file" accept="image/*" class="hidden" @change="pickPhoto($event)">
                                {{ __('webapp.join.photo') }}
                            </label>
                        </div>

                        <label class="block mt-5 text-[12px] font-bold text-body">{{ __('webapp.join.name') }}</label>
                        <input type="text" x-model="form.name" maxlength="255" @keydown.enter="submitProfile()"
                               class="mt-1.5 w-full h-12 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">

                        <label class="block mt-4 text-[12px] font-bold text-body">{{ __('webapp.join.phone') }}</label>
                        <input type="tel" x-model="form.phone_number" maxlength="20" @keydown.enter="submitProfile()"
                               class="mt-1.5 w-full h-12 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">

                        <label class="block mt-4 text-[12px] font-bold text-body">{{ __('webapp.join.email') }}</label>
                        {{-- From Google; shown so they can see which account joined. --}}
                        <input type="email" readonly :value="email"
                               class="mt-1.5 w-full h-12 px-4 rounded-2xl bg-cream-low border border-ink/[.12] text-sm text-muted">

                        <button type="button" @click="submitProfile()" :disabled="busy || !form.name.trim()"
                                class="mt-6 w-full h-12 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark transition disabled:opacity-50"
                                x-text="busy ? '{{ __('webapp.common.saving') }}' : ctaLabel"></button>
                    </div>
                </template>

                {{-- ══ PHASE: done ══════════════════════════════════════ --}}
                <template x-if="phase === 'done'">
                    <div class="text-center">
                        <div class="w-14 h-14 rounded-full bg-ok-surface mx-auto flex items-center justify-center">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="text-ok-ink"><path d="M20 6 9 17l-5-5"/></svg>
                        </div>

                        <p class="mt-4 font-anton text-[21px] tracking-[.5px] text-ink"
                           x-text="t('join.done_title', { community: @js($community->name) })"></p>

                        <p x-show="joinedTier" x-cloak class="mt-2 text-sm text-body">
                            <span x-text="t('join.done_tier', { tier: joinedTier })"></span>
                        </p>

                        <p x-show="pendingApproval" x-cloak class="mt-2 text-sm text-body">{{ __('webapp.join.done_pending') }}</p>

                        <p class="mt-4 text-[12.5px] text-muted leading-relaxed">{{ __('webapp.join.done_app') }}</p>
                    </div>
                </template>
            </div>
        </div>

        <p class="mt-6 text-center text-[11px] text-muted">
            <a href="{{ rtrim(config('webapp.url'), '/') }}/login" class="font-semibold hover:text-ink">{{ __('webapp.join.have_account') }}</a>
        </p>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function communityJoinPage() {
        return {
            t: (key, params) => window.t(key, params),

            // intro → profile → done. One string, so two panels can never show at once.
            // An invited member who is already signed in never sees 'intro': init()
            // accepts for them and leaves.
            phase: 'intro',
            busy: false,
            error: '',
            googleFailed: false,

            signedIn: !!window.kb.token,
            email: '',
            photoFile: null,
            photoPreview: '',
            form: { name: '', phone_number: '' },
            joinedTier: '',
            pendingApproval: false,

            communityId: @js($community->id),
            communitySlug: @js($community->slug),
            inviteOnly: @js($isInviteOnly),
            invitationToken: @js($invitationToken),
            inviteToken: @js($inviteToken),

            get loginUrl() {
                return @js(rtrim(config('webapp.url'), '/')) + '/login?next=' + encodeURIComponent(location.pathname + location.search);
            },

            get ctaLabel() {
                if (this.invitationToken) return window.t('join.accept');
                return this.inviteOnly ? window.t('join.request') : window.t('join.join');
            },

            get ctaLead() {
                return this.inviteOnly ? window.t('join.lead_invite_only') : window.t('join.lead');
            },

            async init() {
                // An emailed invitation already carries the authorisation, so for
                // someone who is signed in there is nothing left to ask: accept it and
                // take them where they were invited to. Making them read a landing page
                // and press "Accept" was a step the link had already earned.
                //
                // Safe to do on arrival even though mail clients prefetch links: the
                // accept is a client-side POST carrying the bearer token from this
                // browser's storage, which a scanner does not have. A prefetch renders
                // the page and can do nothing with it.
                if (this.signedIn) {
                    if (this.invitationToken) await this.join();
                    return;
                }
                await this.renderGoogle();
            },

            async renderGoogle() {
                const ok = await window.kbGoogle.render(this.$refs.googleButton, {
                    text: 'continue_with',
                    dark: this.isDark,
                    onCredential: (resp) => this.onGoogle(resp),
                });
                this.googleFailed = !ok;
            },

            /**
             * Google returns → register/sign in as an ATTENDEE. Community members
             * are attendees on the wire (ROLES §8.1 D4); the label differs, the
             * user_type does not.
             */
            async onGoogle(resp) {
                this.busy = true;
                this.error = '';

                const res = await window.kb.api('/auth/google', {
                    method: 'POST',
                    body: { id_token: resp?.credential, user_type: 'attendee' },
                    auth: false,
                });

                this.busy = false;

                if (!res.ok) {
                    this.error = window.kb.errorText(res, window.t('join.google_error'));
                    return;
                }

                const data = res.json?.data || {};
                window.kb.setSession(data);
                this.signedIn = true;
                this.email = data.profile?.email || '';
                this.form.name = data.profile?.name || '';
                this.phase = 'profile';
            },

            pickPhoto(event) {
                const file = (event.target.files || [])[0];
                event.target.value = '';
                if (!file) return;
                this.photoFile = file;
                this.photoPreview = URL.createObjectURL(file);
            },

            /** Save name/phone/photo, then join. One button, two steps. */
            async submitProfile() {
                this.busy = true;
                this.error = '';

                const fd = new FormData();
                // PUT via POST + _method, the same spoof /account uses for uploads.
                fd.append('_method', 'PUT');
                fd.append('name', this.form.name.trim());
                if (this.form.phone_number.trim()) fd.append('phone_number', this.form.phone_number.trim());
                if (this.photoFile) fd.append('profile_photo', this.photoFile);

                const profileRes = await window.kb.upload('/me/profile', fd);

                if (!profileRes.ok) {
                    this.busy = false;
                    this.error = window.kb.errorText(profileRes, window.t('join.profile_error'));
                    return;
                }

                await this.join();
            },

            joinNow() { return this.join(); },

            /**
             * Leave the public landing page for the app, but only for an accepted
             * invitation.
             *
             * A join REQUEST to an invite-only community is not a membership yet, and a
             * self-serve join from a shared link was not a redirect the visitor asked
             * for — both keep the 'done' panel, which explains what happens next. Only
             * `?i=` means "you were invited and you are now in".
             *
             * The 'done' phase is set first and the redirect follows, so a blocked or
             * slow navigation still leaves a page that says what happened.
             */
            leaveForTheApp() {
                if (!this.invitationToken) return;
                window.location.assign(window.kbPath('/dashboard'));
            },

            /** The endpoint depends on how they arrived and the community's policy. */
            get joinPath() {
                if (this.invitationToken) return '/invitations/accept/' + encodeURIComponent(this.invitationToken);
                if (this.inviteToken) return '/communities/join/' + encodeURIComponent(this.inviteToken);
                return this.inviteOnly
                    ? '/communities/' + this.communityId + '/join-requests'
                    : '/communities/' + this.communityId + '/join';
            },

            async join() {
                this.busy = true;
                this.error = '';

                const res = await window.kb.api(this.joinPath, { method: 'POST' });
                this.busy = false;

                if (res.ok) {
                    this.pendingApproval = this.inviteOnly && !this.invitationToken && !this.inviteToken;
                    this.joinedTier = res.json?.data?.tier?.name || '';
                    this.phase = 'done';
                    this.leaveForTheApp();
                    return;
                }

                // Already a member is a success, not an error — show the done state.
                // Following an invitation twice lands here, and it should still end up
                // inside the app rather than on a page saying "you are already in".
                if (res.status === 422 && res.json?.error === 'already_member') {
                    this.phase = 'done';
                    this.leaveForTheApp();
                    return;
                }

                this.error = window.kb.errorText(res, window.t('join.error'));
            },
        };
    }
</script>
@endpush
