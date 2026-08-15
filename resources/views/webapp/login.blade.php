@extends('webapp.layout')
@section('title', __('webapp.meta.login_title'))
@section('description', __('webapp.meta.login_description'))
@section('robots', 'index,follow')

@section('body')
<div class="min-h-screen flex flex-col items-center justify-center px-5 py-12" x-data="loginPage()" x-init="init()">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <h1 class="font-montserrat font-black text-3xl tracking-tight">Kolabing</h1>
            <p class="text-off-black/60 mt-1">{{ __('webapp.login.subtitle') }}</p>
        </div>

        <template x-if="error">
            <div class="mb-4 rounded-xl bg-red-50 text-red-700 text-sm px-4 py-3" x-text="error"></div>
        </template>

        <form @submit.prevent="submit()" class="space-y-3">
            <input x-model="email" type="email" required placeholder="{{ __('webapp.login.email') }}"
                   class="w-full rounded-xl border-off-black/15 px-4 py-3 focus:border-off-black focus:ring-0">
            <input x-model="password" type="password" required placeholder="{{ __('webapp.login.password') }}"
                   class="w-full rounded-xl border-off-black/15 px-4 py-3 focus:border-off-black focus:ring-0">
            <button type="submit" :disabled="loading"
                    class="w-full rounded-xl bg-off-black text-off-white font-semibold py-3 disabled:opacity-50">
                <span x-text="loading ? t('login.submitting') : t('login.submit')"></span>
            </button>
        </form>

        <template x-if="hasGoogle">
            <div class="mt-6">
                <div class="flex items-center gap-3 text-off-black/40 text-xs mb-3">
                    <span class="h-px bg-off-black/10 flex-1"></span> {{ __('webapp.login.or') }} <span class="h-px bg-off-black/10 flex-1"></span>
                </div>
                <div class="flex gap-2 mb-3 text-sm">
                    <button type="button" @click="userType = 'business'"
                            :class="userType === 'business' ? 'bg-off-black text-off-white' : 'bg-off-black/5'"
                            class="flex-1 rounded-lg py-2 font-medium">{{ __('webapp.login.business') }}</button>
                    <button type="button" @click="userType = 'community'"
                            :class="userType === 'community' ? 'bg-off-black text-off-white' : 'bg-off-black/5'"
                            class="flex-1 rounded-lg py-2 font-medium">{{ __('webapp.login.community') }}</button>
                </div>
                <div id="googleBtn" class="flex justify-center"></div>
            </div>
        </template>

        <p class="text-center text-sm text-off-black/60 mt-6">
            {{ __('webapp.login.new_here') }} <a href="{{ $base }}/register" class="font-semibold text-off-black underline">{{ __('webapp.login.create_account') }}</a>
        </p>
    </div>
</div>

@push('scripts')
<script>
    function loginPage() {
        return {
            email: '', password: '', loading: false, error: '',
            userType: 'business',
            hasGoogle: !!window.KB_CONFIG.googleClientId,
            init() {
                window.kb.requireGuest();
                if (this.hasGoogle) this.loadGoogle();
            },
            async submit() {
                this.error = ''; this.loading = true;
                const { ok, json } = await window.kb.api('/auth/login', {
                    method: 'POST', auth: false, body: { email: this.email, password: this.password },
                });
                this.loading = false;
                if (ok && json?.data?.token) {
                    window.kb.setSession(json.data);
                    window.nav('/dashboard');
                } else {
                    this.error = json?.message || t('login.error');
                }
            },
            loadGoogle() {
                const s = document.createElement('script');
                s.src = 'https://accounts.google.com/gsi/client'; s.async = true; s.defer = true;
                s.onload = () => {
                    google.accounts.id.initialize({
                        client_id: window.KB_CONFIG.googleClientId,
                        callback: (resp) => this.onGoogle(resp),
                    });
                    google.accounts.id.renderButton(document.getElementById('googleBtn'),
                        { theme: 'outline', size: 'large', text: 'continue_with', shape: 'pill' });
                };
                document.head.appendChild(s);
            },
            async onGoogle(resp) {
                this.error = '';
                const { ok, json } = await window.kb.api('/auth/google', {
                    method: 'POST', auth: false,
                    body: { id_token: resp.credential, user_type: this.userType },
                });
                if (ok && json?.data?.token) {
                    window.kb.setSession(json.data);
                    window.nav(json.data.is_new_user ? '/subscription' : '/dashboard');
                } else {
                    this.error = json?.message || t('login.google_error');
                }
            },
        };
    }
</script>
@endpush
@endsection
