{{-- $loc, $base, $defaultLocale, $localeUrls, $localePaths come from the webapp View composer (AppServiceProvider). --}}
{{-- NEW DESIGN: Atmospheric Editorial (mobile app parity) — Anton display + Inter body, warm cream ground, #FFE28C yellow, pill buttons. --}}
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <style>
        [x-cloak]{display:none!important}
        .font-anton{letter-spacing:.02em;text-transform:uppercase}
        ::placeholder{color:#8C8474}
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        // New product palette (KolabingColors, mobile parity)
                        primary: "#FFE28C",          // warm yellow — primary CTA fill
                        "primary-dark": "#F5D070",   // pressed/hover yellow
                        "primary-tint": "#FFF4C2",   // selected fills
                        ink: "#19150F",              // primary text / dark fills
                        body: "#3F3A32",             // body text
                        muted: "#8C8474",            // tertiary text
                        amber: "#9A7C28",            // labels on yellow
                        cream: "#FAF5EA",            // page background
                        "cream-input": "#F5EFE3",    // input fills
                        "cream-low": "#F7F3EA",      // subtle fills / icon tiles
                        "peach": "#F6DDCF",          // category chip bg
                        "peach-ink": "#9A4A20",      // category chip text
                        // Legacy aliases so untouched partials keep working
                        "off-black": "#19150F",
                        "off-white": "#FAF5EA",
                    },
                    fontFamily: {
                        sans: ["Inter", "sans-serif"],
                        anton: ["Anton", "sans-serif"],
                    },
                    boxShadow: {
                        card: "0 1.5px 8px rgba(55,73,87,.10)",
                        btn: "0 1.5px 4px rgba(55,73,87,.11)",
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
        // Locale-aware navigation (prefixes /es or /ca).
        window.nav = function (path) { location.href = (window.KB_BASE || '') + path; };

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
            requireGuest() { if (this.token) { window.nav('/dashboard'); } },
            logout() { this.clear(); window.nav('/login'); },
            async _fetch(path, method, body, auth) {
                const headers = { 'Accept': 'application/json', 'Content-Type': 'application/json' };
                if (auth && this.token) headers['Authorization'] = 'Bearer ' + this.token;
                return fetch(KB_CONFIG.apiBase + path, { method, headers, body: body ? JSON.stringify(body) : null });
            },
            async api(path, { method = 'GET', body = null, auth = true } = {}) {
                let res = await this._fetch(path, method, body, auth);
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
            async uploadFile(file, folder) {
                const fd = new FormData();
                fd.append('file', file);
                fd.append('folder', folder);
                const headers = {};
                if (this.token) headers['Authorization'] = 'Bearer ' + this.token;
                let res;
                try { res = await fetch(KB_CONFIG.apiBase + '/uploads', { method: 'POST', headers, body: fd }); }
                catch (e) { return { ok: false, status: 0, json: null }; }
                let json = null;
                try { json = await res.json(); } catch (e) { /* empty */ }
                return { ok: res.ok, status: res.status, json };
            },
        };

        // Shared sidebar state: viewer identity + unread notifications.
        // Defined here so every page's sidebar include can use it.
        function kbSidebar() {
            return {
                me: null, unread: 0, open: false,
                get isBusiness() { return this.me?.user_type === 'business'; },
                get displayName() {
                    const p = this.me || {};
                    return p.business_profile?.name || p.community_profile?.name || p.display_name || p.name || p.email || '';
                },
                get initial() { return (this.displayName || '?')[0].toUpperCase(); },
                get avatarUrl() {
                    const p = this.me || {};
                    return p.business_profile?.logo_url || p.community_profile?.logo_url || p.avatar_url || '';
                },
                async init() {
                    if (!window.kb.token) return;
                    const [me, un] = await Promise.all([
                        window.kb.api('/auth/me'),
                        window.kb.api('/me/notifications/unread-count'),
                    ]);
                    if (me.ok) this.me = me.json?.data || null;
                    if (un.ok) this.unread = un.json?.data?.count ?? un.json?.data?.unread_count ?? 0;
                },
            };
        }
    </script>
    @stack('head')
</head>
<body class="min-h-screen bg-cream text-ink font-sans antialiased">
    @yield('body')
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js" defer></script>
    @stack('scripts')
</body>
</html>
