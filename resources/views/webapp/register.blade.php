@extends('webapp.layout')
@section('title', __('webapp.meta.register_title'))
@section('description', __('webapp.meta.register_description'))
@section('robots', 'index,follow')

@section('body')
<div class="min-h-screen flex flex-col items-center justify-center px-5 py-12" x-data="registerPage()" x-init="init()">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <h1 class="font-montserrat font-black text-3xl tracking-tight">Kolabing</h1>
            <p class="text-off-black/60 mt-1">{{ __('webapp.register.subtitle') }}</p>
        </div>

        <template x-if="error">
            <div class="mb-4 rounded-xl bg-red-50 text-red-700 text-sm px-4 py-3" x-text="error"></div>
        </template>

        <p class="text-sm font-medium text-off-black/70 mb-2">{{ __('webapp.register.signing_up_as') }}</p>
        <div class="flex gap-2 mb-5 text-sm">
            <button type="button" @click="userType = 'business'"
                    :class="userType === 'business' ? 'bg-off-black text-off-white' : 'bg-off-black/5'"
                    class="flex-1 rounded-lg py-2.5 font-medium">{{ __('webapp.register.business') }}</button>
            <button type="button" @click="userType = 'community'"
                    :class="userType === 'community' ? 'bg-off-black text-off-white' : 'bg-off-black/5'"
                    class="flex-1 rounded-lg py-2.5 font-medium">{{ __('webapp.register.community') }}</button>
        </div>
        <p class="text-xs text-off-black/50 mb-5"
           x-text="userType === 'business' ? t('register.business_hint') : t('register.community_hint')"></p>

        <template x-if="hasGoogle">
            <div id="googleBtn" class="flex justify-center"></div>
        </template>
        <template x-if="!hasGoogle">
            <div class="rounded-xl bg-off-black/5 text-off-black/60 text-sm px-4 py-3 text-center">
                {{ __('webapp.register.google_soon') }}
            </div>
        </template>

        <p class="text-center text-sm text-off-black/60 mt-6">
            {{ __('webapp.register.have_account') }} <a href="{{ $base }}/login" class="font-semibold text-off-black underline">{{ __('webapp.register.login') }}</a>
        </p>
        <p class="text-center text-xs text-off-black/40 mt-4">
            {!! __('webapp.register.terms', [
                'terms' => '<a href="https://kolabing.com/terms" class="underline">'.e(__('webapp.register.terms_word')).'</a>',
                'privacy' => '<a href="https://kolabing.com/privacy" class="underline">'.e(__('webapp.register.privacy_word')).'</a>',
            ]) !!}
        </p>
    </div>
</div>

@push('scripts')
<script>
    function registerPage() {
        return {
            userType: 'business', error: '',
            hasGoogle: !!window.KB_CONFIG.googleClientId,
            init() {
                window.kb.requireGuest();
                if (this.hasGoogle) this.loadGoogle();
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
                        { theme: 'filled_black', size: 'large', text: 'signup_with', shape: 'pill', width: 320 });
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
                    window.nav(this.userType === 'business' ? '/subscription' : '/dashboard');
                } else {
                    this.error = json?.message || t('register.error');
                }
            },
        };
    }
</script>
@endpush
@endsection
