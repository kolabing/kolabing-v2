{{-- $loc, $base, $defaultLocale, $localeUrls, $localePaths come from the webapp View composer (AppServiceProvider). --}}
{{-- Design: "Atmospheric Editorial" (kolabing-web-design bundle) — Anton display + Inter body,
     warm cream ground, #FFE28C yellow, pill buttons, 264px sidebar shell. --}}
<!DOCTYPE html>
<html lang="{{ $loc }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Kolabing') | Kolabing</title>
    <meta name="description" content="@yield('description', 'Kolabing — where local businesses and communities collaborate.')">
    <meta name="robots" content="@yield('robots', 'noindex,nofollow')">
    <link rel="canonical" href="{{ $localeUrls[$loc] }}">
    @foreach ($localeUrls as $l => $href)
        <link rel="alternate" hreflang="{{ $l }}" href="{{ $href }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $localeUrls[$defaultLocale] }}">
    <meta name="theme-color" content="#FAF5EA">
    <meta property="og:site_name" content="Kolabing">
    <meta property="og:title" content="@yield('title', 'Kolabing')">
    <meta property="og:locale" content="{{ str_replace('-', '_', $loc) }}">
    <link rel="icon" href="/favicon.ico?v=3" sizes="any">
    <link rel="apple-touch-icon" href="/favicon-512.png?v=3">
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <style>
        /* Self-hosted from the design bundle — no external font round-trip. */
        @font-face { font-family: 'Anton'; src: url('/webapp-assets/fonts/anton-400.woff2') format('woff2'); font-weight: 400; font-display: swap; }
        @font-face { font-family: 'Inter'; src: url('/webapp-assets/fonts/inter-400.woff2') format('woff2'); font-weight: 400; font-display: swap; }
        @font-face { font-family: 'Inter'; src: url('/webapp-assets/fonts/inter-500.woff2') format('woff2'); font-weight: 500; font-display: swap; }
        @font-face { font-family: 'Inter'; src: url('/webapp-assets/fonts/inter-600.woff2') format('woff2'); font-weight: 600; font-display: swap; }
        @font-face { font-family: 'Inter'; src: url('/webapp-assets/fonts/inter-700.woff2') format('woff2'); font-weight: 700; font-display: swap; }

        [x-cloak] { display: none !important; }
        html, body { margin: 0; padding: 0; background: #FAF5EA; }
        ::placeholder { color: #8C8474; }
        input:focus, textarea:focus, select:focus { outline: none; border-color: #19150F !important; box-shadow: none !important; }

        /* Anton display face — the design always sets uppercase + .02em tracking. */
        .font-anton { font-family: Anton, sans-serif; letter-spacing: .02em; text-transform: uppercase; font-weight: 400; }

        @keyframes kbFadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .kb-fade-up { animation: kbFadeUp .5s cubic-bezier(.16,.84,.34,1); }
        .kb-fade-up-fast { animation: kbFadeUp .3s cubic-bezier(.16,.84,.34,1); }

        /* The auth welcome hero's curved yellow cap. */
        .kb-hero-curve { border-radius: 0 0 48% 48% / 0 0 60px 60px; }

        .kb-scroll::-webkit-scrollbar { width: 8px; }
        .kb-scroll::-webkit-scrollbar-thumb { background: rgba(28,28,22,.14); border-radius: 8px; }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: "#FFE28C",          // warm yellow — primary CTA fill
                        "primary-dark": "#F5D070",   // pressed/hover yellow
                        "primary-tint": "#FFF4C2",   // selected fills
                        ink: "#19150F",              // primary text / dark fills
                        body: "#3F3A32",             // body text
                        muted: "#8C8474",            // tertiary text
                        amber: "#9A7C28",            // labels on yellow
                        cream: "#FAF5EA",            // page background
                        "cream-alt": "#F6F1E7",      // auth background
                        "cream-input": "#F5EFE3",    // input fills
                        "cream-low": "#F7F3EA",      // subtle fills / icon tiles
                        "line": "#E4DBCB",           // outline-button border
                        peach: "#F6DDCF",            // category chip bg
                        "peach-ink": "#9A4A20",      // category chip text
                        danger: "#BA1A1A",
                    },
                    fontFamily: {
                        sans: ["Inter", "sans-serif"],
                        anton: ["Anton", "sans-serif"],
                    },
                    boxShadow: {
                        card: "0 1.5px 8px rgba(55,73,87,.10)",
                        btn: "0 1.5px 4px rgba(55,73,87,.11)",
                        cardhover: "0 4px 16px rgba(55,73,87,.12)",
                    },
                    borderRadius: { pill: "999px" },
                },
            },
        };
    </script>
    <script>
        window.KB_CONFIG = {
            apiBase: '/api/v1',
            googleClientId: @json(config('services.google.client_id_web')),
            iosUrl: @json(config('webapp.app_store_url')),
            androidUrl: @json(config('webapp.play_store_url')),
            deepLink: @json(config('webapp.deep_link')),
        };
        window.KB_LOCALE = @json($loc);
        window.KB_BASE = @json($base);
        window.KB_I18N = @json(__('webapp'));

        // Translation lookup (dotted key) with :param interpolation; falls back to the key.
        window.t = function (key, params) {
            let s = key.split('.').reduce((o, k) => (o == null ? o : o[k]), window.KB_I18N);
            if (s == null) return key;
            if (params) { for (const k in params) { s = s.split(':' + k).join(params[k]); } }
            return s;
        };
        /**
         * Translate, or fall back when the key is missing. `t()` returns the key
         * itself for a missing entry (a truthy string), so `t(k) || fallback` can
         * never fire — an unmapped enum would render as "STATUS.PAST_DUE".
         */
        window.tOr = function (key, fallback) {
            const s = window.t(key);
            return s === key ? fallback : s;
        };
        /** Today + `days`, as a local YYYY-MM-DD (never UTC — that shifts the day). */
        window.kbDayOffset = function (days) {
            const d = new Date();
            d.setDate(d.getDate() + days);
            const p = (n) => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
        };
        // Locale-aware navigation (prefixes /es or /ca).
        window.nav = function (path) { location.href = (window.KB_BASE || '') + path; };
        window.kbPath = function (path) { return (window.KB_BASE || '') + path; };

        // Same-origin API client: bearer token in localStorage (same flow as mobile),
        // one transparent refresh on 401. API paths are NOT locale-prefixed.
        window.kb = {
            tokenKey: 'kolabing_token',
            refreshKey: 'kolabing_refresh',
            get token() { return localStorage.getItem(this.tokenKey); },
            get refreshToken() { return localStorage.getItem(this.refreshKey); },
            setSession(data) {
                if (!data) return;
                if (data.token) localStorage.setItem(this.tokenKey, data.token);
                if (data.refresh_token) localStorage.setItem(this.refreshKey, data.refresh_token);
            },
            clear() { localStorage.removeItem(this.tokenKey); localStorage.removeItem(this.refreshKey); },
            requireAuth() { if (!this.token) { window.nav('/login'); return false; } return true; },
            requireGuest() { if (this.token) { window.nav('/dashboard'); return false; } return true; },
            logout() { this.clear(); window.nav('/'); },
            async _fetch(path, method, body, auth) {
                const headers = { 'Accept': 'application/json', 'Content-Type': 'application/json' };
                if (auth && this.token) headers['Authorization'] = 'Bearer ' + this.token;
                return fetch(KB_CONFIG.apiBase + path, { method, headers, body: body ? JSON.stringify(body) : null });
            },
            async api(path, { method = 'GET', body = null, auth = true } = {}) {
                let res;
                try { res = await this._fetch(path, method, body, auth); }
                catch (e) { return { ok: false, status: 0, json: null }; }
                if (res.status === 401 && auth && this.refreshToken) {
                    if (await this.refresh()) res = await this._fetch(path, method, body, auth);
                    else this.clear();
                }
                let json = null;
                try { json = await res.json(); } catch (e) { /* empty body */ }
                return { ok: res.ok, status: res.status, json };
            },
            async refresh() {
                try {
                    const res = await this._fetch('/auth/refresh', 'POST', { refresh_token: this.refreshToken }, false);
                    if (!res.ok) return false;
                    const j = await res.json();
                    this.setSession(j.data || {});
                    return true;
                } catch (e) { return false; }
            },
            _fetchForm(path, formData) {
                const headers = {};
                if (this.token) headers['Authorization'] = 'Bearer ' + this.token;
                return fetch(KB_CONFIG.apiBase + path, { method: 'POST', headers, body: formData });
            },
            /**
             * Multipart POST with the same one-shot token refresh as `api()`. Every
             * file upload goes through here so an expired token retries instead of
             * losing the user's file to a bare 401.
             */
            async upload(path, formData) {
                let res;
                try { res = await this._fetchForm(path, formData); }
                catch (e) { return { ok: false, status: 0, json: null }; }
                if (res.status === 401 && this.refreshToken) {
                    if (await this.refresh()) res = await this._fetchForm(path, formData);
                    else this.clear();
                }
                let json = null;
                try { json = await res.json(); } catch (e) { /* empty */ }
                return { ok: res.ok, status: res.status, json };
            },
            uploadFile(file, folder) {
                const fd = new FormData();
                fd.append('file', file);
                fd.append('folder', folder);
                return this.upload('/uploads', fd);
            },
            /** Flatten a Laravel 422 payload (or fall back to `message`). */
            errorText(res, fallback) {
                if (res?.json?.errors) return Object.values(res.json.errors).flat().join('\n');
                return res?.json?.message || fallback;
            },
            /**
             * The list rows out of a response. Endpoints backed by a ResourceCollection
             * (kolabs, applications, collaborations, discovery) nest the array under
             * data.data, while plain ones (notifications, lookups) put it at data —
             * read both so a caller never silently renders an empty list.
             */
            rows(res) {
                const d = res?.json?.data;
                if (Array.isArray(d)) return d;
                if (Array.isArray(d?.data)) return d.data;
                return [];
            },
            /** Pagination meta, wherever the endpoint puts it. */
            meta(res) { return res?.json?.meta || res?.json?.data?.meta || {}; },
        };

        /**
         * Merge component mixins while KEEPING getters lazy.
         * Object.assign would invoke every getter at merge time and freeze the
         * result, so all the derived state below must go through this instead.
         */
        window.kbMerge = function (...parts) {
            const out = {};
            for (const part of parts) {
                Object.defineProperties(out, Object.getOwnPropertyDescriptors(part));
            }
            return out;
        };

        // ── Shared view-model helpers ───────────────────────────────────────────
        // Status pill colours, straight from the design's `st()` map.
        window.kbStatus = function (status) {
            const m = {
                pending:   ['#FFDDAC', '#D8910B'], scheduled: ['#FFDDAC', '#D8910B'],
                draft:     ['#EDEAE0', '#4C4638'], closed:    ['#EDEAE0', '#4C4638'],
                completed: ['#EDEAE0', '#4C4638'], withdrawn: ['#EDEAE0', '#4C4638'],
                accepted:  ['#D4EDDA', '#155724'], active:    ['#D4EDDA', '#155724'],
                published: ['#D4EDDA', '#155724'],
                declined:  ['#F8D7DA', '#721C24'], cancelled: ['#F8D7DA', '#721C24'],
                past_due:  ['#F8D7DA', '#721C24'],
            }[status] || ['#EDEAE0', '#4C4638'];
            return { bg: m[0], c: m[1], label: window.tOr('status.' + status, status).toUpperCase() };
        };
        window.kbIntentLabel = function (type) {
            const map = {
                community_seeking: 'intent.community_seeking',
                venue_promotion: 'intent.venue_promotion',
                product_promotion: 'intent.product_promotion',
            };
            const key = map[type];
            return key ? window.tOr(key, window.kbHumanize(type)) : window.t('intent.kolab');
        };
        // Turn a snake_case taxonomy slug into a readable label as a last resort.
        window.kbHumanize = function (v) {
            if (!v) return '';
            return String(v).replace(/[_-]+/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
        };
        window.kbInitial = function (name) { return (String(name || '?').trim()[0] || '?').toUpperCase(); };
        window.kbDate = function (iso) {
            if (!iso) return '';
            const d = new Date(iso);
            if (isNaN(d)) return String(iso);
            return d.toLocaleDateString(window.KB_LOCALE || 'en', { day: 'numeric', month: 'short', year: 'numeric' });
        };
        window.kbDateShort = function (iso) {
            if (!iso) return '';
            const d = new Date(iso);
            if (isNaN(d)) return String(iso);
            return d.toLocaleDateString(window.KB_LOCALE || 'en', { day: 'numeric', month: 'short' });
        };

        // Shared shell state: viewer identity + unread notification count.
        function kbShell() {
            return {
                me: null, unread: 0, menuOpen: false, shellReady: false,
                get isBusiness() { return this.me?.user_type === 'business'; },
                get isCommunity() { return this.me?.user_type === 'community'; },
                /** A business without an active plan — paywalled actions should route to /subscription. */
                get needsPlan() { return this.isBusiness && !this.me?.has_active_subscription; },
                /** Stripe could not charge the card: access is degrading and the business must act. */
                get pastDue() { return this.isBusiness && this.me?.subscription_status === 'past_due'; },
                /** Still active, but set to end at the period boundary. */
                get planEnding() { return this.isBusiness && !!this.me?.subscription_cancel_at_period_end; },
                /**
                 * Hand off to the Stripe Billing Portal (update card / cancel / invoices).
                 * Shared by the plan page and the past-due banner.
                 */
                async openBillingPortal() {
                    const res = await window.kb.api('/me/subscription/portal', {
                        method: 'POST',
                        body: { return_url: location.origin + window.kbPath('/subscription') },
                    });
                    if (res.ok && res.json?.data?.portal_url) { location.href = res.json.data.portal_url; return null; }
                    return window.kb.errorText(res, window.t('subscription.portal_error'));
                },
                get profile() {
                    const p = this.me || {};
                    return p.business_profile || p.community_profile || {};
                },
                get displayName() {
                    return this.profile.name || this.me?.handle || this.me?.email || '';
                },
                get initial() { return window.kbInitial(this.displayName); },
                get avatarUrl() { return this.profile.logo_url || this.profile.profile_photo || this.me?.avatar_url || ''; },
                get roleLabel() { return this.isBusiness ? window.t('nav.role_business') : window.t('nav.role_community'); },
                async loadShell() {
                    if (!window.kb.token) return null;
                    const [me, un] = await Promise.all([
                        window.kb.api('/auth/me'),
                        window.kb.api('/me/notifications/unread-count'),
                    ]);
                    if (!me.ok) { window.kb.logout(); return null; }
                    this.me = me.json?.data || null;
                    if (un.ok) this.unread = un.json?.data?.count ?? 0;
                    this.shellReady = true;
                    return this.me;
                },
            };
        }
    </script>
    @stack('head')
</head>
<body class="min-h-screen bg-cream text-ink font-sans antialiased">
    @yield('body')
    {{-- Alpine is self-hosted (like the design bundle's fonts): the whole app is
         Alpine-driven, so it must not depend on a third-party CDN staying up. --}}
    <script src="/webapp-assets/alpine-3.14.1.min.js" defer></script>
    @stack('scripts')
</body>
</html>
