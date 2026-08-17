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
    {{-- Theme must be on <html> before first paint, or the cream ground flashes
         white-hot in front of a dark-theme user on every navigation. --}}
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('kolabing_theme');
                var dark = stored ? stored === 'dark'
                    : window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (dark) { document.documentElement.setAttribute('data-theme', 'dark'); }
            } catch (e) { /* private mode — light is a safe default */ }
        })();
    </script>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <style>
        /* Self-hosted from the design bundle — no external font round-trip. */
        @font-face { font-family: 'Anton'; src: url('/webapp-assets/fonts/anton-400.woff2') format('woff2'); font-weight: 400; font-display: swap; }
        @font-face { font-family: 'Inter'; src: url('/webapp-assets/fonts/inter-400.woff2') format('woff2'); font-weight: 400; font-display: swap; }
        @font-face { font-family: 'Inter'; src: url('/webapp-assets/fonts/inter-500.woff2') format('woff2'); font-weight: 500; font-display: swap; }
        @font-face { font-family: 'Inter'; src: url('/webapp-assets/fonts/inter-600.woff2') format('woff2'); font-weight: 600; font-display: swap; }
        @font-face { font-family: 'Inter'; src: url('/webapp-assets/fonts/inter-700.woff2') format('woff2'); font-weight: 700; font-display: swap; }

        /*
         * The whole palette lives here as RGB channel triplets, and every Tailwind
         * colour token below is `rgb(var(--kb-…) / <alpha-value>)`. Flipping
         * [data-theme] on <html> therefore re-themes every screen — including the
         * ~100 `bg-white` card surfaces — without touching a single view, and
         * opacity modifiers (bg-ink/10, bg-primary/40) keep working.
         */
        :root {
            --kb-primary: 255 226 140;      /* warm yellow — primary CTA fill */
            --kb-primary-dark: 245 208 112; /* pressed/hover yellow */
            --kb-primary-tint: 255 244 194; /* selected fills */
            --kb-ink: 25 21 15;             /* primary text / dark fills */
            --kb-body: 63 58 50;            /* body text */
            --kb-muted: 140 132 116;        /* tertiary text */
            --kb-amber: 154 124 40;         /* labels on yellow */
            --kb-cream: 250 245 234;        /* page background */
            --kb-cream-alt: 246 241 231;    /* auth background */
            --kb-cream-input: 245 239 227;  /* input fills */
            --kb-cream-low: 247 243 234;    /* subtle fills / icon tiles */
            --kb-cream-low-hover: 236 232 223;
            --kb-line: 228 219 203;         /* outline-button border */
            --kb-surface: 255 255 255;      /* cards — Tailwind's `white` maps here */
            --kb-peach: 246 221 207;
            --kb-peach-ink: 154 74 32;
            --kb-danger: 186 26 26;
            --kb-accent: 255 97 20;         /* unread dot */
            --kb-faint: 201 194 180;        /* disabled day cells */

            /* Status pills + inline banners (also read from JS via kbStatus). */
            --kb-ok-surface: 212 237 218;   --kb-ok-ink: 21 87 36;
            --kb-warn-surface: 255 221 172; --kb-warn-ink: 216 145 11;
            --kb-bad-surface: 248 215 218;  --kb-bad-ink: 114 28 36;
            --kb-neutral-surface: 237 234 224; --kb-neutral-ink: 76 70 56;
            --kb-success-solid: 86 98 77;   /* the confirmation sheet's check disc */

            --kb-shadow-card: 0 1.5px 8px rgba(55, 73, 87, .10);
            --kb-shadow-btn: 0 1.5px 4px rgba(55, 73, 87, .11);
            --kb-shadow-cardhover: 0 4px 16px rgba(55, 73, 87, .12);
            --kb-overlay: rgba(13, 17, 20, .62);
            --kb-scrollbar: rgba(28, 28, 22, .14);
            color-scheme: light;
        }

        /*
         * Dark theme. The yellow stays the brand anchor but is dimmed so it does not
         * glare on a dark ground, and `ink` inverts to near-white — which also flips
         * every dark-on-yellow pairing (bg-ink text-primary) into the correct
         * light-on-dark one, because both sides come from the same tokens.
         */
        [data-theme="dark"] {
            --kb-primary: 245 205 106;
            --kb-primary-dark: 232 189 84;
            --kb-primary-tint: 61 52 30;
            --kb-ink: 243 239 231;
            --kb-body: 200 193 180;
            --kb-muted: 150 142 128;
            --kb-amber: 232 199 122;
            --kb-cream: 22 20 17;
            --kb-cream-alt: 27 24 20;
            --kb-cream-input: 45 40 34;
            --kb-cream-low: 42 38 32;
            --kb-cream-low-hover: 55 49 41;
            --kb-line: 74 67 56;
            --kb-surface: 33 30 25;
            --kb-peach: 74 46 32;
            --kb-peach-ink: 240 186 155;
            --kb-danger: 255 138 128;
            --kb-accent: 255 122 61;
            --kb-faint: 96 89 78;

            --kb-ok-surface: 30 62 40;      --kb-ok-ink: 152 214 168;
            --kb-warn-surface: 74 55 22;    --kb-warn-ink: 240 195 116;
            --kb-bad-surface: 74 32 36;     --kb-bad-ink: 246 168 172;
            --kb-neutral-surface: 52 47 40; --kb-neutral-ink: 190 182 168;
            --kb-success-solid: 108 130 96;

            --kb-shadow-card: 0 1.5px 8px rgba(0, 0, 0, .45);
            --kb-shadow-btn: 0 1.5px 4px rgba(0, 0, 0, .5);
            --kb-shadow-cardhover: 0 4px 16px rgba(0, 0, 0, .55);
            --kb-overlay: rgba(0, 0, 0, .7);
            --kb-scrollbar: rgba(255, 255, 255, .16);
            color-scheme: dark;
        }

        [x-cloak] { display: none !important; }
        html, body { margin: 0; padding: 0; background: rgb(var(--kb-cream)); }
        html { transition: background-color .2s ease; }
        ::placeholder { color: rgb(var(--kb-muted)); }
        input:focus, textarea:focus, select:focus { outline: none; border-color: rgb(var(--kb-ink)) !important; box-shadow: none !important; }
        /* Native controls (date/time pickers, selects) follow the theme. */
        input[type="date"], input[type="time"] { color-scheme: inherit; }

        /* Anton display face — the design always sets uppercase + .02em tracking. */
        .font-anton { font-family: Anton, sans-serif; letter-spacing: .02em; text-transform: uppercase; font-weight: 400; }

        @keyframes kbFadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .kb-fade-up { animation: kbFadeUp .5s cubic-bezier(.16,.84,.34,1); }
        .kb-fade-up-fast { animation: kbFadeUp .3s cubic-bezier(.16,.84,.34,1); }

        /* The auth welcome hero's curved yellow cap. */
        .kb-hero-curve { border-radius: 0 0 48% 48% / 0 0 60px 60px; }

        .kb-scroll::-webkit-scrollbar { width: 8px; }
        .kb-scroll::-webkit-scrollbar-thumb { background: var(--kb-scrollbar); border-radius: 8px; }

        /* Modal scrims — themed so a dark overlay does not glow grey on dark. */
        .kb-overlay { background: var(--kb-overlay); backdrop-filter: blur(3px); }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        // Every token resolves through a CSS variable, so the theme
                        // switch is a single attribute on <html>. `<alpha-value>` keeps
                        // Tailwind's opacity modifiers (bg-ink/10) working.
                        primary: "rgb(var(--kb-primary) / <alpha-value>)",
                        "primary-dark": "rgb(var(--kb-primary-dark) / <alpha-value>)",
                        "primary-tint": "rgb(var(--kb-primary-tint) / <alpha-value>)",
                        ink: "rgb(var(--kb-ink) / <alpha-value>)",
                        body: "rgb(var(--kb-body) / <alpha-value>)",
                        muted: "rgb(var(--kb-muted) / <alpha-value>)",
                        amber: "rgb(var(--kb-amber) / <alpha-value>)",
                        cream: "rgb(var(--kb-cream) / <alpha-value>)",
                        "cream-alt": "rgb(var(--kb-cream-alt) / <alpha-value>)",
                        "cream-input": "rgb(var(--kb-cream-input) / <alpha-value>)",
                        "cream-low": "rgb(var(--kb-cream-low) / <alpha-value>)",
                        "cream-low-hover": "rgb(var(--kb-cream-low-hover) / <alpha-value>)",
                        line: "rgb(var(--kb-line) / <alpha-value>)",
                        peach: "rgb(var(--kb-peach) / <alpha-value>)",
                        "peach-ink": "rgb(var(--kb-peach-ink) / <alpha-value>)",
                        danger: "rgb(var(--kb-danger) / <alpha-value>)",
                        accent: "rgb(var(--kb-accent) / <alpha-value>)",
                        faint: "rgb(var(--kb-faint) / <alpha-value>)",
                        // Status / banner pairs, shared with kbStatus() in JS.
                        "ok-surface": "rgb(var(--kb-ok-surface) / <alpha-value>)",
                        "ok-ink": "rgb(var(--kb-ok-ink) / <alpha-value>)",
                        "warn-surface": "rgb(var(--kb-warn-surface) / <alpha-value>)",
                        "warn-ink": "rgb(var(--kb-warn-ink) / <alpha-value>)",
                        "bad-surface": "rgb(var(--kb-bad-surface) / <alpha-value>)",
                        "bad-ink": "rgb(var(--kb-bad-ink) / <alpha-value>)",
                        "success-solid": "rgb(var(--kb-success-solid) / <alpha-value>)",
                        // Card surfaces. Remapping `white` re-themes every existing
                        // `bg-white` without editing ~100 call sites.
                        white: "rgb(var(--kb-surface) / <alpha-value>)",
                    },
                    fontFamily: {
                        sans: ["Inter", "sans-serif"],
                        anton: ["Anton", "sans-serif"],
                    },
                    boxShadow: {
                        card: "var(--kb-shadow-card)",
                        btn: "var(--kb-shadow-btn)",
                        cardhover: "var(--kb-shadow-cardhover)",
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
            marketingUrl: @json(config('webapp.marketing_url')),
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
            // Logging out leaves the product entirely — send people to the public
            // site, not back to the app host's own logged-out hero.
            logout() { this.clear(); location.href = KB_CONFIG.marketingUrl; },
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
            // Resolve to CSS variables, not hex, so pills re-theme with everything else.
            const tone = {
                pending: 'warn', scheduled: 'warn', pending_confirmation: 'warn',
                draft: 'neutral', closed: 'neutral', completed: 'neutral', withdrawn: 'neutral',
                accepted: 'ok', active: 'ok', published: 'ok',
                declined: 'bad', cancelled: 'bad', past_due: 'bad',
            }[status] || 'neutral';
            return {
                bg: `rgb(var(--kb-${tone}-surface))`,
                c: `rgb(var(--kb-${tone}-ink))`,
                label: window.tOr('status.' + status, status).toUpperCase(),
            };
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

        /**
         * Theme control. The boot script at the top of <head> has already applied the
         * stored (or system) preference; this only handles switching it afterwards.
         * Mixed into kbShell(), so every app screen's sidebar can drive it.
         */
        window.kbThemeState = function () {
            const stored = (() => { try { return localStorage.getItem('kolabing_theme'); } catch (e) { return null; } })();
            const systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            return {
                theme: stored || (systemDark ? 'dark' : 'light'),
                get isDark() { return this.theme === 'dark'; },
                setTheme(theme) {
                    this.theme = theme;
                    const root = document.documentElement;
                    if (theme === 'dark') { root.setAttribute('data-theme', 'dark'); }
                    else { root.removeAttribute('data-theme'); }
                    try { localStorage.setItem('kolabing_theme', theme); } catch (e) { /* private mode */ }
                    // Keep the browser chrome (mobile address bar) in step.
                    const meta = document.querySelector('meta[name="theme-color"]');
                    if (meta) {
                        meta.setAttribute('content', getComputedStyle(root).getPropertyValue('background-color') || '');
                    }
                },
                toggleTheme() { this.setTheme(this.isDark ? 'light' : 'dark'); },
            };
        };

        // Shared shell state: viewer identity + unread notification count + theme.
        // kbMerge (never object spread) so kbThemeState's `isDark` getter stays lazy.
        function kbShell() {
            return window.kbMerge(window.kbThemeState(), {
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
            });
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
