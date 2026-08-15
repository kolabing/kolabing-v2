@extends('webapp.layout')
@section('title', 'Create your account')

@section('body')
<div class="min-h-screen flex flex-col items-center justify-center px-5 py-12" x-data="registerPage()" x-init="init()">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <h1 class="font-montserrat font-black text-3xl tracking-tight">Kolabing</h1>
            <p class="text-off-black/60 mt-1">Create your account</p>
        </div>

        <template x-if="error">
            <div class="mb-4 rounded-xl bg-red-50 text-red-700 text-sm px-4 py-3" x-text="error"></div>
        </template>

        <p class="text-sm font-medium text-off-black/70 mb-2">I'm signing up as a…</p>
        <div class="flex gap-2 mb-5 text-sm">
            <button type="button" @click="userType = 'business'"
                    :class="userType === 'business' ? 'bg-off-black text-off-white' : 'bg-off-black/5'"
                    class="flex-1 rounded-lg py-2.5 font-medium">Business</button>
            <button type="button" @click="userType = 'community'"
                    :class="userType === 'community' ? 'bg-off-black text-off-white' : 'bg-off-black/5'"
                    class="flex-1 rounded-lg py-2.5 font-medium">Community</button>
        </div>
        <p class="text-xs text-off-black/50 mb-5"
           x-text="userType === 'business' ? 'Businesses collaborate with communities and subscribe to publish Kolabs.' : 'Communities join and apply to Kolabs for free.'"></p>

        <template x-if="hasGoogle">
            <div id="googleBtn" class="flex justify-center"></div>
        </template>
        <template x-if="!hasGoogle">
            <div class="rounded-xl bg-off-black/5 text-off-black/60 text-sm px-4 py-3 text-center">
                Google sign-up is being switched on. In the meantime, download the app to create your account.
            </div>
        </template>

        <p class="text-center text-sm text-off-black/60 mt-6">
            Already have an account? <a href="/login" class="font-semibold text-off-black underline">Log in</a>
        </p>
        <p class="text-center text-xs text-off-black/40 mt-4">
            By continuing you agree to our
            <a href="https://kolabing.com/terms" class="underline">Terms</a> and
            <a href="https://kolabing.com/privacy" class="underline">Privacy Policy</a>.
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
                    // New business → straight to the sales/subscription step; else the app.
                    location.href = (this.userType === 'business') ? '/subscription' : '/dashboard';
                } else {
                    this.error = json?.message || 'Sign-up failed. Please try again.';
                }
            },
        };
    }
</script>
@endpush
@endsection
