@php

    /**
     * Absolute web-app URLs. The app lives on another host (app.kolabing.com), so
     * these cannot be route() calls. `?type=` lands straight on the register form
     * with the role already picked — one step fewer than the generic /register.
     */
    $app = rtrim(config('webapp.url'), '/');
    $appRegister = $app.'/register';
    $appLogin = $app.'/login';
    $appRegisterBusiness = $appRegister.'?type=business';
    $appRegisterCommunity = $appRegister.'?type=community';

    /**
     * JSON-LD is built here, NOT inline in the <script> tag. Blade compiles
     * directives inside `{!! !!}` expressions, and Laravel 12 has an `@context`
     * directive — so a literal '@context' key written there is replaced by compiled
     * PHP and the emitted structured data loses its @context entirely. Inside a
     * @php block the compiler leaves it alone. See PublicProfilePageTest /
     * MarketingSeoTest for the guard.
     */
    $homeSchema = json_encode([
      '@context' => 'https://schema.org',
      '@graph' => [
          [
              '@type' => 'Organization',
              'name' => 'Kolabing',
              'url' => route('home'),
              'logo' => url('/brand/kolabing-logo.png'),
              'description' => 'Kolabing helps local businesses and communities plan partnerships that turn events into footfall, member value, and repeat visits.',
              'email' => 'support@kolabing.com',
          ],
          [
              '@type' => 'WebSite',
              'name' => 'Kolabing',
              'url' => route('home'),
          ],
          [
              '@type' => 'FAQPage',
              'mainEntity' => [
                  ['@type' => 'Question', 'name' => 'Is it free for communities?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Yes, Kolabing is always free for community leaders and groups.']],
                  ['@type' => 'Question', 'name' => 'How do businesses get matched?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'You post a Kolab and our system surfaces communities that match your location, audience, and goal.']],
                  ['@type' => 'Question', 'name' => 'What kind of collaborations can I create?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Events, venue partnerships, product trials, UGC sessions, weekly recurring meetups, anything involving real people in real places.']],
                  ['@type' => 'Question', 'name' => 'Where is Kolabing available?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Currently live in Barcelona, expanding to more cities soon.']],
                  ['@type' => 'Question', 'name' => 'Can I cancel anytime?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Yes, no long-term commitment. Paid plans for businesses can be cancelled at any time.']],
              ],
          ],
      ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@endphp
<!DOCTYPE html><html lang="en" class="scroll-smooth"><head>

  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Kolabing — Local Business &amp; Community Collaboration</title>
  <meta name="description" content="Kolabing connects local businesses with nearby communities to plan real-world collaborations that drive footfall, members, and repeat visits. Live in Barcelona.">
  <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
  <link rel="canonical" href="{{ route('home') }}">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Kolabing">
  <meta property="og:title" content="Kolabing — Local Business &amp; Community Collaboration">
  <meta property="og:description" content="Connect with nearby communities to plan real-world collaborations that drive footfall, members, and repeat visits.">
  <meta property="og:url" content="{{ route('home') }}">
  <meta property="og:image" content="{{ url('/social-preview.svg') }}">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Kolabing — Local Business &amp; Community Collaboration">
  <meta name="twitter:description" content="Connect with nearby communities to plan real-world collaborations that drive footfall, members, and repeat visits.">
  <meta name="twitter:image" content="{{ url('/social-preview.svg') }}">
  <script type="application/ld+json">
  {!! $homeSchema !!}
  </script>
  <link rel="icon" href="/favicon.ico?v=3" sizes="any">
  <link rel="icon" type="image/png" href="/favicon-512.png?v=3">
  <link rel="apple-touch-icon" href="/favicon-512.png?v=3">
  <link rel="manifest" href="/site.webmanifest">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
  <link href="https://fonts.googleapis.com/css2?family=Anton&amp;family=Caveat:wght@500;600&amp;family=Inter:wght@400;500;600;700&amp;family=Playfair+Display:ital@1&amp;display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --yellow: #FFE28C;
      --purple: #ff6114;
      --dark: #0D1114;
      --light: #F7F8FA;
      --mid: #E0E0E0;
    }

    html { font-family: 'Inter', sans-serif; }
    body { background: #fff; color: var(--dark); overflow-x: hidden; }

    /* ── NAV ── */
    header {
      position: fixed; top: 0; width: 100%; z-index: 100;
      background: var(--yellow);
      border-bottom: 1px solid rgba(13,17,20,0.12);
      padding: 14px 56px; display: flex; justify-content: space-between; align-items: center;
    }
    .logo { display: flex; align-items: center; }
    .logo-mark {
      display: block;
      height: 38px;
      width: auto;
      transform: rotate(-2deg);
      transform-origin: left center;
    }
    .logo-mark--footer {
      height: 46px;
      transform: rotate(-2deg);
      /* Gold letters read on the dark footer; the black cloud blends into it. */
      filter: drop-shadow(0 6px 16px rgba(0,0,0,0.35));
    }
    nav { display: flex; align-items: center; gap: 32px; }
    nav a { text-decoration: none; color: var(--dark); font-size: 13px; font-weight: 600; opacity: 0.75; transition: opacity .2s, color .2s; }
    /* Plain nav links only. The filled CTA is a dark pill with white text, so
       letting this reach it repainted its label near-black on near-black. */
    nav a:not(.btn-nav):hover { opacity: 1; color: var(--dark); }
    /* In-page section anchors — these drop away on mobile so the two web-app
       CTAs are the only things left in the header. */
    .nav-links { display: flex; align-items: center; gap: 32px; }
    .nav-login { font-weight: 700; opacity: 0.9; }
    .btn-nav {
      /* White, not the brand yellow: the header itself is yellow, so pale-yellow
         text on the dark pill washed out against the surrounding field. */
      background: var(--dark); color: #fff; padding: 12px 28px;
      border-radius: 999px; font-weight: 800; font-size: 14px; text-decoration: none; opacity: 1 !important;
      letter-spacing: 0.01em;
      transition: background .2s, transform .2s;
    }
    /* Restates the colour so no nav-wide rule can wash the label out again. */
    .btn-nav:hover, .btn-nav:focus-visible { background: #1c2025; color: #fff; transform: translateY(-1px); }
    .menu-icon {
      display: none;
      width: 34px; height: 34px;
      cursor: pointer;
      background: none; border: none;
      color: var(--dark);
      padding: 0;
    }
    .menu-icon svg { width: 100%; height: 100%; display: block; }

    /* ── HERO ── */
    .hero {
      position: relative; width: 100%; min-height: 100svh;
      background: var(--dark);
      display: flex; align-items: center; justify-content: center;
      padding: 88px clamp(24px, 5vw, 72px) 64px;
      overflow: hidden; /* contain phone bleed */
    }

    /* Centered, balanced 2-column wrapper */
    .hero-inner {
      width: min(100%, 1360px);
      margin: 0 auto;
      display: grid;
      grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr);
      align-items: center;
      gap: clamp(40px, 6vw, 128px);
    }

    /* ── Left text panel ── */
    .hero-left {
      position: relative; z-index: 4;
      display: flex; flex-direction: column; justify-content: center;
      max-width: 560px;
    }

    .hero-badge {
      display: inline-flex; align-items: center;
      background: rgba(255, 97, 20,0.12);
      border: 1px solid rgba(255, 97, 20,0.4); color: rgba(255,255,255,0.75);
      font-size: 10px; font-weight: 600; letter-spacing: 0.16em; text-transform: uppercase;
      padding: 5px 14px; border-radius: 999px; margin-bottom: 28px; width: fit-content;
    }

    .hero h1 {
      font-family: 'Anton', sans-serif; text-transform: uppercase;
      color: #fff;
      font-size: clamp(2.6rem, 5vw, 78px);
      line-height: 0.92;
      letter-spacing: -0.01em;
      margin-bottom: 0;
      position: relative;
      max-width: 100%;
      text-shadow: 0 2px 28px rgba(0,0,0,0.55), 0 1px 4px rgba(0,0,0,0.4);
    }
    .hero h1 .line { display: block; }
    .hero h1 .line-indent { padding-left: 0.22em; }
    .hero h1 .accent { color: var(--yellow); }

    .hero-rule {
      width: 40px; height: 2px; background: var(--yellow);
      opacity: 0.6; margin: 26px 0 20px;
    }

    /* Hero supporting lead — the one explanatory sentence under the title */
    .hero-lead {
      font-size: 15px; color: rgba(255,255,255,0.72);
      font-weight: 400; line-height: 1.6;
      max-width: 430px; margin-bottom: 14px;
    }
    .hero-sub {
      font-size: 13px; color: rgba(255,255,255,0.46);
      font-weight: 500; letter-spacing: 0.02em; margin-bottom: 4px;
    }
    .hero-sub .accent { color: var(--yellow); font-weight: 600; }
    /* Hero handwritten side-note: yellow Caveat, small, near social proof */
    .hero-note {
      display: inline-flex; align-items: center; gap: 6px;
      font-family: 'Caveat', 'Comic Sans MS', cursive;
      font-size: 18px;
      color: rgba(255,226,140,0.62);
      transform: rotate(-3deg);
      transform-origin: left center;
      margin: 0 0 24px 4px;
      white-space: nowrap;
      pointer-events: none;
    }
    .hero-note svg {
      width: 36px; height: 16px;
      opacity: 0.55;
    }

    .social-proof { color: rgba(255,255,255,0.25); font-size: 11px; margin-bottom: 24px; }

    /* ── HERO WEB-APP CTA ──
       The mobile apps are not published yet, so the browser app is the only live
       acquisition path: one primary pill to /register plus a quiet log-in link. */
    .hero-cta { display: flex; align-items: center; gap: 22px; flex-wrap: wrap; margin-bottom: 10px; }
    .hero-cta-btn {
      display: inline-flex; align-items: center; gap: 10px;
      background: var(--yellow); color: var(--dark);
      padding: 16px 32px; border-radius: 999px;
      font-size: 15px; font-weight: 800; letter-spacing: 0.01em;
      text-decoration: none; white-space: nowrap;
      box-shadow: 0 10px 30px rgba(255,226,140,0.26);
      transition: transform .2s, box-shadow .2s;
    }
    .hero-cta-btn:hover { transform: translateY(-2px); box-shadow: 0 14px 38px rgba(255,226,140,0.42); }
    .hero-cta-btn svg { width: 15px; height: 15px; flex-shrink: 0; }
    .hero-cta-login { color: rgba(255,255,255,0.5); font-size: 12px; font-weight: 500; text-decoration: none; }
    .hero-cta-login span { color: #fff; font-weight: 700; text-decoration: underline; text-underline-offset: 3px; }
    .hero-cta-login:hover span { color: var(--yellow); }

    .hero-fine { color: rgba(255,255,255,0.2); font-size: 10px; }

    /* ── Right: vertical cloud matcher ── */
    .hero-right {
      position: relative;
      display: flex; align-items: center; justify-content: center;
      height: 100%;
      overflow: visible;
      z-index: 1;
    }

    /* Soft glow behind the matcher */
    .hero-right::before {
      content: '';
      position: absolute;
      width: 420px; height: 520px;
      border-radius: 50%;
      background: radial-gradient(ellipse, rgba(255, 97, 20,0.20) 0%, transparent 70%);
      top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      pointer-events: none;
      z-index: 0;
      filter: blur(36px);
    }

    /* ── Vertical matcher: community (top) + business (bottom) ── */
    .matcher {
      position: relative; z-index: 2;
      display: flex; flex-direction: column; align-items: center;
      width: 100%; max-width: 460px;
    }
    .matcher-eyebrow {
      font-family: 'Inter', sans-serif; font-weight: 600;
      font-size: 11px; letter-spacing: 0.3em; text-transform: uppercase;
      color: rgba(255,255,255,0.5);
      margin-bottom: 22px;
    }
    .matcher-stage {
      position: relative; width: 100%;
      display: flex; flex-direction: column; align-items: center;
    }
    .mcloud {
      position: relative;
      width: clamp(280px, 32vw, 400px);
      aspect-ratio: 540 / 360;
    }
    .mcloud-inner { position: absolute; inset: 0; will-change: transform; }
    .mcloud-top .mcloud-inner    { animation: mcBobA 6.5s ease-in-out infinite alternate; }
    .mcloud-bottom .mcloud-inner { animation: mcBobB 7.4s ease-in-out infinite alternate; }
    .mcloud svg {
      position: absolute; inset: 0; width: 100%; height: 100%; display: block;
      filter: drop-shadow(0 26px 40px rgba(0,0,0,0.45));
    }
    .mcloud-top    { margin-bottom: 2px; z-index: 2; }
    .mcloud-bottom { margin-top: 2px; z-index: 2; }
    .mcloud-text {
      position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
      width: 70%; text-align: center;
      font-family: 'Anton', sans-serif; text-transform: uppercase;
      color: var(--dark); line-height: 0.9; letter-spacing: 0.01em;
      font-size: clamp(20px, 2.4vw, 34px);
      user-select: none; pointer-events: none; text-wrap: balance;
    }
    .mcloud-text.settling { animation: mcSettle .42s cubic-bezier(.16,.84,.34,1); }

    /* The match moment — plain plus + the Kolab outcome, between clouds */
    .matcher-bridge {
      position: relative; z-index: 5;
      display: flex; flex-direction: column; align-items: center; gap: 6px;
      padding: 14px 0;
    }
    .matcher-plus {
      font-family: 'Anton', sans-serif; font-size: clamp(26px, 2.6vw, 38px);
      line-height: 1; color: rgba(255,255,255,0.5);
      animation: mcPulse 6s ease-in-out infinite alternate;
    }
    .matcher-outcome {
      font-family: 'Anton', sans-serif; text-transform: uppercase;
      color: var(--yellow); line-height: 0.95; letter-spacing: 0.005em;
      font-size: clamp(26px, 3.1vw, 44px);
      max-width: 360px; text-align: center; text-wrap: balance;
      opacity: 0; transform: translateY(8px);
      transition: opacity .5s ease, transform .5s cubic-bezier(.16,.84,.34,1);
    }
    .matcher-outcome.show { opacity: 1; transform: translateY(0); }
    @keyframes mcBobA { from { transform: translateY(-7px); } to { transform: translateY(7px); } }
    @keyframes mcBobB { from { transform: translateY(7px); } to { transform: translateY(-7px); } }
    @keyframes mcPulse { from { opacity: 0.3; } to { opacity: 0.6; } }
    @keyframes mcSettle { 0% { transform: translate(-50%,-46%); opacity:.5; } 100% { transform: translate(-50%,-50%); opacity:1; } }
    @media (prefers-reduced-motion: reduce) {
      .mcloud-inner, .matcher-plus { animation: none !important; }
    }

    /* ── MATCH BAND — thin yellow strip after hero ── */
    .match-band {
      background: var(--yellow);
      padding: 20px 24px;
      border-top: 1px solid rgba(13,17,20,0.08);
      border-bottom: 1px solid rgba(13,17,20,0.08);
    }
    .match-band-inner {
      max-width: 1200px; margin: 0 auto;
      display: flex; align-items: baseline; justify-content: center;
      gap: 18px; flex-wrap: wrap; text-align: center;
    }
    .match-band-eyebrow {
      font-family: 'Inter', sans-serif; font-weight: 600;
      font-size: clamp(10px, 1vw, 12px); letter-spacing: 0.28em; text-transform: uppercase;
      color: rgba(13,17,20,0.5);
      white-space: nowrap;
    }
    .match-band-text {
      font-family: 'Anton', sans-serif; text-transform: uppercase;
      color: var(--dark); line-height: 1; letter-spacing: 0.01em;
      font-size: clamp(20px, 2.6vw, 36px);
    }

    /* Phone wrapper — tilted, offset from center */
    .phone-wrap {
      position: relative;
      transform: rotate(4deg) translate(-40px, -12px);
      transition: transform 0.6s cubic-bezier(.22,.68,0,1.2);
      z-index: 2;
      will-change: transform;
      transform-style: preserve-3d;
    }
    .phone-wrap:hover {
      transform: rotate(2deg) translate(-40px, -20px);
    }

    /* The phone frame itself */
    .phone-frame {
      position: relative;
      width: 248px;
      height: 532px;
      border-radius: 44px;
      background: #0a0a0c;
      box-shadow:
        0 0 0 1.5px rgba(255,255,255,0.12),
        0 0 0 3px #1a1a1f,
        0 32px 80px rgba(0,0,0,0.7),
        0 8px 24px rgba(0,0,0,0.5),
        inset 0 0 0 1px rgba(255,255,255,0.05);
    }

    /* Screen area inside phone */
    .phone-screen {
      position: absolute;
      top: 12px; left: 8px; right: 8px; bottom: 12px;
      border-radius: 36px;
      overflow: hidden;
      background: #000;
    }

    .phone-screen video {
      width: 100%; height: 100%;
      object-fit: cover;
    }

    /* Dynamic island notch */
    .phone-notch {
      position: absolute;
      top: 18px; left: 50%;
      transform: translateX(-50%);
      width: 88px; height: 26px;
      background: #0a0a0c;
      border-radius: 20px;
      z-index: 10;
    }

    /* Side button accents */
    .phone-btn-right {
      position: absolute;
      right: -3px; top: 120px;
      width: 3px; height: 60px;
      background: #1e1e24;
      border-radius: 2px;
    }
    .phone-btn-left-1 {
      position: absolute;
      left: -3px; top: 100px;
      width: 3px; height: 28px;
      background: #1e1e24;
      border-radius: 2px;
    }
    .phone-btn-left-2 {
      position: absolute;
      left: -3px; top: 140px;
      width: 3px; height: 52px;
      background: #1e1e24;
      border-radius: 2px;
    }

    /* ── Floating UI chips ── */
    .chip {
      position: absolute;
      background: rgba(255,255,255,0.95);
      backdrop-filter: blur(16px);
      border-radius: 14px;
      padding: 10px 14px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.18), 0 2px 8px rgba(0,0,0,0.1);
      display: flex; align-items: center; gap: 9px;
      z-index: 5;
      animation: chip-float 4s ease-in-out infinite;
    }
    .chip-runners {
      bottom: 80px; left: -72px;
      animation-delay: 0s;
    }
    .chip-notification {
      top: 60px; right: -56px;
      animation-delay: 2s;
    }
    @keyframes chip-float {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-6px); }
    }
    .chip-icon {
      width: 28px; height: 28px; border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      font-size: 14px; flex-shrink: 0;
    }
    .chip-icon.yellow { background: var(--yellow); }
    .chip-icon.purple { background: var(--purple); color: #fff; }
    .chip-text { display: flex; flex-direction: column; gap: 1px; }
    .chip-title { font-size: 11px; font-weight: 700; color: var(--dark); line-height: 1; }
    .chip-sub { font-size: 10px; color: rgba(13,17,20,0.45); line-height: 1; }

    /* Purple accent line behind phone */
    .phone-accent-line {
      position: absolute;
      left: -16px; top: 15%; bottom: 15%;
      width: 2px;
      background: linear-gradient(to bottom, transparent, var(--purple), transparent);
      opacity: 0.5;
      border-radius: 2px;
      z-index: 1;
    }

    /* Yellow bottom glow */
    .phone-glow {
      position: absolute;
      bottom: -40px; left: 50%;
      transform: translateX(-50%);
      width: 180px; height: 60px;
      background: var(--yellow);
      filter: blur(40px);
      opacity: 0.18;
      border-radius: 50%;
      z-index: 0;
      pointer-events: none;
    }

    /* ── MANIFESTO — horizontal split, compact editorial ── */
    .section-manifesto {
      background: #fff; padding: 88px 0 80px; overflow: hidden;
      position: relative;
      border-top: 1px solid rgba(13,17,20,0.06);
    }
    .manifesto-track {
      max-width: 1200px; margin: 0 auto; padding: 0 64px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 80px;
      align-items: center;
    }

    /* LEFT column — journey copy */
    .manifesto-copy { max-width: 480px; }

    .manifesto-support p {
      font-size: 13px; color: rgba(13,17,20,0.45);
      font-weight: 400; line-height: 1.7;
      max-width: 380px;
      border-left: 2px solid var(--yellow); padding-left: 16px;
      margin-bottom: 30px;
    }

    .manifesto-friend-label {
      font-size: 10px; font-weight: 600; text-transform: uppercase;
      letter-spacing: 0.18em; color: rgba(13,17,20,0.3);
      margin-bottom: 10px;
      line-height: 1;
    }

    .manifesto-bubble {
      display: inline-block; background: var(--yellow); color: var(--dark);
      font-weight: 500; font-size: 15px;
      padding: 11px 20px; border-radius: 18px 18px 18px 4px;
      margin-bottom: 36px;
      line-height: 1.4;
      max-width: fit-content;
      box-shadow: 0 2px 12px rgba(255,226,140,0.45), 0 1px 3px rgba(0,0,0,0.08);
      position: relative;
      left: 2px;
    }

    .manifesto-actions {
      display: flex; flex-direction: column; gap: 0;
    }
    .manifesto-action {
      font-family: 'Anton', sans-serif; text-transform: uppercase;
      font-size: clamp(2rem, 4vw, 56px); color: var(--dark); line-height: 0.92;
      letter-spacing: -0.01em;
    }
    .manifesto-action:last-child { color: var(--yellow); }
    .manifesto-action-rule {
      width: 24px; height: 1.5px; background: rgba(13,17,20,0.12);
      margin: 12px 0;
    }

    /* RIGHT column — the two surfaces Kolabing ships on: the web panel
       (browser frame, the larger of the two) with the mobile app overlapping it.
       The panel is drawn in CSS rather than screenshotted so it never goes stale
       against the real app. */
    .manifesto-phone {
      position: relative;
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      min-height: 480px;
    }
    .manifesto-phone .phone-wrap { transform: rotate(-4deg); }

    .surface-stack { position: relative; width: 100%; max-width: 500px; }

    .browser-frame {
      position: relative; z-index: 1;
      border-radius: 14px; overflow: hidden; background: #fff;
      transform: rotate(-1.5deg);
      box-shadow: 0 26px 64px rgba(13,17,20,0.20), 0 2px 8px rgba(13,17,20,0.08);
    }
    .browser-bar {
      display: flex; align-items: center; gap: 6px;
      padding: 9px 12px; background: #ECE9E2; border-bottom: 1px solid rgba(13,17,20,0.07);
    }
    .browser-dot { width: 9px; height: 9px; border-radius: 50%; background: #C6C2BA; flex-shrink: 0; }
    .browser-url {
      margin-left: 8px; flex: 1; background: #fff; border-radius: 999px;
      padding: 4px 12px; font-size: 10px; font-weight: 600; color: #7A7770; letter-spacing: 0.02em;
    }
    .browser-body { display: grid; grid-template-columns: 78px 1fr; background: #FBF7EF; height: 252px; }
    .wp-side {
      background: #fff; border-right: 1px solid rgba(13,17,20,0.07);
      padding: 12px 10px; display: flex; flex-direction: column; gap: 8px;
    }
    .wp-logo { width: 24px; height: 24px; border-radius: 8px; background: var(--yellow); margin-bottom: 4px; }
    .wp-nav { height: 9px; border-radius: 999px; background: rgba(13,17,20,0.09); }
    .wp-nav.is-on { background: var(--yellow); }
    .wp-main { padding: 12px; display: flex; flex-direction: column; gap: 10px; }
    .wp-search { height: 22px; border-radius: 999px; background: #fff; border: 1px solid rgba(13,17,20,0.08); }
    .wp-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 9px; }
    .wp-card { background: #fff; border: 1px solid rgba(13,17,20,0.07); border-radius: 10px; overflow: hidden; }
    .wp-card-img { height: 44px; background: linear-gradient(135deg, rgba(255,226,140,0.95), rgba(255,97,20,0.35)); }
    .wp-card-img.alt { background: linear-gradient(135deg, rgba(13,17,20,0.82), rgba(13,17,20,0.42)); }
    .wp-card-body { padding: 7px 8px; display: flex; flex-direction: column; gap: 5px; }
    .wp-line { height: 6px; border-radius: 999px; background: rgba(13,17,20,0.14); }
    .wp-line.short { width: 55%; background: rgba(13,17,20,0.09); }
    .wp-tag { width: 34px; height: 8px; border-radius: 999px; background: var(--yellow); }

    /* The phone rides on top of the panel. Scaling lives on this wrapper because
       the tilt script writes directly to #phoneWrap's transform. */
    .phone-mini {
      position: absolute; right: -10px; bottom: -54px; z-index: 3;
      transform: scale(0.56); transform-origin: bottom right;
    }

    .surface-caption {
      margin-top: 84px; text-align: center;
      font-size: 11px; font-weight: 600; letter-spacing: 0.14em; text-transform: uppercase;
      color: rgba(13,17,20,0.45);
    }
    .surface-caption strong { color: var(--dark); font-weight: 800; }
    .manifesto-phone .phone-glow {
      position: absolute; width: 300px; height: 300px; border-radius: 50%;
      background: radial-gradient(circle, rgba(255,226,140,0.5) 0%, transparent 70%);
      bottom: -40px; left: 50%; transform: translateX(-50%);
      filter: blur(40px); z-index: 0; pointer-events: none;
    }

    /* ── REVEAL — poster-style, typography-driven, no imagery ── */
    .section-reveal {
      background: #050505;
      padding: 0; overflow: hidden; position: relative;
      min-height: 320px;
      isolation: isolate;
    }
    /* Soft vignette glow behind the headline */
    .section-reveal::before {
      content: "";
      position: absolute; inset: 0;
      background:
        radial-gradient(60% 70% at 50% 60%, rgba(255,210,90,0.06), rgba(0,0,0,0) 65%),
        radial-gradient(80% 80% at 50% 100%, rgba(255, 97, 20,0.05), rgba(0,0,0,0) 70%);
      pointer-events: none;
      z-index: 0;
    }
    /* Fine grain — SVG noise as data-uri, very low opacity */
    .section-reveal::after {
      content: "";
      position: absolute; inset: 0;
      background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='240' height='240'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/><feColorMatrix values='0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 0.5 0'/></filter><rect width='100%25' height='100%25' filter='url(%23n)' opacity='0.55'/></svg>");
      opacity: 0.08;
      mix-blend-mode: overlay;
      pointer-events: none;
      z-index: 1;
    }
    .reveal-inner {
      position: relative; z-index: 2;
      max-width: 1200px; margin: 0 auto;
      padding: 96px 48px 100px;
      display: flex; flex-direction: column; align-items: center;
      text-align: center;
      gap: 28px;
    }
    .reveal-kicker {
      font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.32em;
      color: rgba(255,255,255,0.32);
      display: inline-flex; align-items: center; gap: 16px;
      white-space: nowrap;
    }
    .reveal-kicker::before,
    .reveal-kicker::after {
      content: ""; display: inline-block;
      width: 44px; height: 1px;
      background: rgba(255,255,255,0.18);
    }
    .reveal-text-block {
      position: relative;
    }
    .reveal-line1 {
      font-family: 'Anton', sans-serif; text-transform: uppercase;
      font-size: clamp(1.8rem, 4.5vw, 64px);
      color: rgba(255,255,255,0.16);
      line-height: 0.92;
      letter-spacing: 0.005em;
    }
    .reveal-line2 {
      font-family: 'Anton', sans-serif; text-transform: uppercase;
      font-size: clamp(2.6rem, 7vw, 104px);
      color: #fff;
      line-height: 0.9;
      letter-spacing: -0.012em;
      margin-top: 0.08em;
    }
    .reveal-line2 .kol { color: var(--yellow); }

    /* ── HOW IT WORKS — warm parchment, playful editorial ── */
    .section-how {
      background: #F5F0E8; padding: 144px 0 128px; overflow: hidden;
      border-top: 3px solid var(--yellow);
      position: relative;
    }
    /* Section-level decorative graphics */
    .how-deco {
      display: none; /* sketches removed */
      position: absolute;
      pointer-events: none;
      z-index: 1;
    }
    .how-deco-arrow {
      left: 4%; right: 4%;
      bottom: 92px;
      height: 110px;
      opacity: 0.18;
    }
    .how-deco-arrow svg { width: 100%; height: 100%; display: block; }
    .how-deco-sparkle-1 { top: 96px; right: 7%;  width: 30px; transform: rotate(12deg);  opacity: 0.5; }
    .how-deco-sparkle-2 { display: none; }
    .how-deco-sparkle-3 { bottom: 56px; right: 12%; width: 24px; transform: rotate(8deg); opacity: 0.4; }
    /* Purple marker circle now wraps the word HOW — see .how-word-circle below */
    .how-deco-squiggle { display: none; }
    .how-deco svg { width: 100%; height: 100%; display: block; }

    .how-header {
      max-width: 1280px; margin: 0 auto; padding: 0 48px;
      display: grid; grid-template-columns: 1fr 2fr; gap: 48px; align-items: end;
      margin-bottom: 72px;
      position: relative; z-index: 2;
    }
    .how-heading-block { position: relative; }

    /* The word "HOW" — wrapped in a loose hand-drawn marker circle */
    .how-word {
      position: relative;
      display: inline-block;
      padding: 0 0.14em 0.04em 0.1em;
      isolation: isolate;
    }
    .how-word-circle {
      display: none; /* sketch removed */
      position: absolute;
      left: -10%; top: -12%;
      width: 120%; height: 130%;
      pointer-events: none;
      z-index: -1;
      overflow: visible;
    }
    .how-word-circle path {
      fill: none;
      stroke: #ff6114;
      stroke-width: 3;
      stroke-linecap: round;
      stroke-linejoin: round;
      opacity: 0.75;
    }

    .section-label {
      font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.2em;
      color: var(--purple); opacity: 0.55; margin-bottom: 10px;
    }
    .section-title {
      font-family: 'Anton', sans-serif; text-transform: uppercase;
      font-size: clamp(2.2rem, 4vw, 56px); color: var(--dark); line-height: 0.95;
      letter-spacing: 0.01em;
    }
    .section-sub {
      color: rgba(13,17,20,0.45); font-size: 14px; font-weight: 400;
      padding-bottom: 4px;
    }

    .how-steps {
      max-width: 1280px; margin: 0 auto; padding: 0 48px;
      display: grid; grid-template-columns: repeat(4, 1fr); gap: 0;
      position: relative; z-index: 2;
    }
    .how-step {
      padding: 56px 32px 32px 0; border-top: 1px solid var(--mid);
      position: relative;
      transition: border-top-color 0.25s ease;
    }
    .how-step:not(:last-child)::after {
      content: ''; position: absolute; top: -1px; right: 0;
      width: 1px; height: 100%; background: var(--mid);
    }
    .how-step:hover { border-top-color: var(--purple); }
    .how-step:hover .how-num { color: rgba(255,97,20,0.18); }
    .how-step:hover .how-step-illo { transform: rotate(0deg) translateY(-3px); }
    .how-step:not(:first-child) { padding-left: 32px; padding-right: 24px; }

    /* Per-step illustration — pinned-sticker feel */
    .how-step-illo {
      display: none; /* sketches removed */
      position: absolute;
      top: -28px;
      right: 12px;
      width: 60px; height: 60px;
      z-index: 3;
      transition: transform 0.3s cubic-bezier(.2,.7,.2,1);
    }
    .how-step:nth-child(1) .how-step-illo { transform: rotate(-6deg); }
    .how-step:nth-child(2) .how-step-illo { transform: rotate(4deg);  }
    .how-step:nth-child(3) .how-step-illo { transform: rotate(-3deg); }
    .how-step:nth-child(4) .how-step-illo { transform: rotate(7deg);  }
    .how-step-illo svg { width: 100%; height: 100%; display: block; overflow: visible; }

    .how-num {
      font-family: 'Anton', sans-serif; font-size: 64px; line-height: 1;
      color: rgba(13,17,20,0.07); letter-spacing: -0.02em; margin-bottom: 12px;
      display: block;
      transition: color 0.25s ease;
    }
    .how-step h4 {
      font-family: 'Anton', sans-serif; text-transform: uppercase;
      font-size: 18px; margin-bottom: 10px; letter-spacing: 0.01em; line-height: 1.0;
      color: var(--dark);
    }
    .how-step p {
      font-size: 13px; color: rgba(13,17,20,0.5); line-height: 1.65;
    }

    /* ── KOLAB IDEAS — business-goal matrix, dark editorial ── */
    .section-examples { background: #111317; padding: 120px 0 124px; overflow: hidden; position: relative; }
    .section-examples .section-label { color: var(--yellow); opacity: 0.7; }
    .section-examples .section-title { color: #fff; }

    /* Centered header */
    .ideas-head {
      max-width: 780px; margin: 0 auto 60px; padding: 0 32px;
      text-align: center;
      display: flex; flex-direction: column; align-items: center;
    }
    .ideas-head .section-label { margin-bottom: 14px; }
    .ideas-head .section-title { margin-bottom: 20px; }
    .ideas-sub {
      font-size: clamp(14px, 1.15vw, 16px); line-height: 1.6;
      color: rgba(255,255,255,0.5); max-width: 560px;
      text-wrap: balance; margin-bottom: 28px;
    }
    .ideas-formula {
      display: inline-flex; align-items: center; gap: clamp(8px, 0.9vw, 13px);
      flex-wrap: wrap; justify-content: center;
      font-family: 'Anton', sans-serif; text-transform: uppercase;
      letter-spacing: 0.085em; font-size: clamp(12px, 1.05vw, 14px);
    }
    .ideas-formula .cap {
      background: var(--yellow); color: #15181c;
      padding: 9px 18px; border-radius: 999px;
      box-shadow: 0 10px 26px -15px color-mix(in srgb, var(--yellow) 80%, transparent);
    }
    .ideas-formula i { font-style: normal; color: rgba(255,255,255,0.5); font-size: 1.35em; line-height: 1; }
    .ideas-formula .hl {
      background: #15181c; color: var(--yellow);
      border: 1.5px solid var(--yellow);
      padding: 9px 18px; border-radius: 999px;
      letter-spacing: 0.08em;
    }

    /* Matrix: each column = goal badge → arrow → polaroid */
    .ideas-matrix {
      position: relative;
      max-width: 1320px; margin: 64px auto 0; padding: 0 clamp(24px, 4vw, 52px);
      display: grid; grid-template-columns: repeat(6, 1fr);
      gap: clamp(12px, 1.6vw, 28px); align-items: start;
    }

    /* Handwritten side note pointing at the Test-a-product icon */
    .goal-note {
      position: absolute; top: -60px; left: clamp(8px, 2vw, 24px);
      display: flex; align-items: flex-start; gap: 2px;
      font-family: 'Caveat','Comic Sans MS',cursive;
      font-size: 24px; line-height: 1; color: var(--yellow);
      transform: rotate(-8deg); transform-origin: left center;
      pointer-events: none; white-space: nowrap; z-index: 4;
    }
    .goal-note .gn-arrow { width: 35px; height: 50px; color: var(--yellow); flex: none; overflow: visible; margin-top: 4px; }
    /* keep the tilt through the fade-in (overrides .fade / .fade.in transforms) */
    .goal-note.fade { transform: translateY(16px) rotate(-8deg); }
    .goal-note.fade.in { transform: translateY(0) rotate(-8deg); }
    @media (max-width: 880px) { .goal-note { display: none; } }
    .ideas-col {
      display: flex; flex-direction: column; align-items: center;
      --accent: var(--yellow);
    }

    /* Goal badge + label */
    .goal { display: flex; flex-direction: column; align-items: center; gap: 10px; min-height: 96px; }
    .goal-badge {
      width: 54px; height: 54px; border-radius: 50%;
      background: var(--accent);
      display: grid; place-items: center;
      box-shadow: 0 8px 22px -8px color-mix(in srgb, var(--accent) 60%, transparent);
      transition: transform .3s cubic-bezier(.2,.7,.2,1);
    }
    .ideas-col:hover .goal-badge { transform: translateY(-3px) rotate(-5deg); }
    .goal-badge svg { width: 26px; height: 26px; stroke: #15181c; }
    .goal-label {
      font-family: 'Anton', sans-serif; text-transform: uppercase;
      font-size: clamp(10px, 0.85vw, 11.5px); letter-spacing: 0.06em;
      color: rgba(255,255,255,0.82); text-align: center; line-height: 1.05;
    }

    /* Straight downward connector (accent-colored) */
    .goal-arrow {
      width: 18px; height: 30px; margin: 8px 0 10px;
      color: var(--accent); opacity: 0.95; overflow: visible;
    }
    .ideas-col:hover .goal-arrow { opacity: 1; transform: translateY(2px); }
    .goal-arrow { transition: transform .3s cubic-bezier(.2,.7,.2,1); }

    /* Floating polaroid (clean white frame, soft shadow, no pins) */
    .polaroid {
      background: #fff; padding: 7px 7px 0; border-radius: 3px;
      box-shadow: 0 16px 32px -14px rgba(0,0,0,0.62), 0 4px 10px -5px rgba(0,0,0,0.4);
      width: 100%;
      transition: transform .4s cubic-bezier(.2,.7,.2,1), box-shadow .3s ease;
    }
    .ideas-col:nth-child(odd)  .polaroid { transform: rotate(-1.8deg); }
    .ideas-col:nth-child(even) .polaroid { transform: rotate(1.8deg); }
    .ideas-col:hover .polaroid {
      transform: rotate(0deg) translateY(-4px);
      box-shadow: 0 26px 50px -16px rgba(0,0,0,0.7); z-index: 3;
    }
    .polaroid-img { width: 100%; aspect-ratio: 1/1; overflow: hidden; border-radius: 1px; background: #e9e6df; }
    .polaroid-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
    /* Placeholder polaroid image — drop a real photo here */
    .polaroid-img.ph {
      display: grid; place-items: center;
      background:
        repeating-linear-gradient(135deg, #ece5d8 0 11px, #e3dccd 11px 22px);
    }
    .polaroid-img.ph span {
      font-family: ui-monospace, 'SF Mono', Menlo, Consolas, monospace;
      font-size: 9px; letter-spacing: 0.06em; text-transform: uppercase;
      color: #9a8f79; text-align: center; line-height: 1.5; padding: 10px;
    }
    .polaroid figcaption { padding: 9px 4px 11px; text-align: center; }
    .polaroid figcaption strong {
      display: block; font-size: 11px; font-weight: 700;
      color: #15181c; line-height: 1.25; letter-spacing: 0.005em;
    }
    .polaroid figcaption span {
      display: block; margin-top: 2px;
      font-size: 10px; color: rgba(13,17,20,0.5); line-height: 1.3;
    }

    /* Bottom handwritten line */
    .ideas-foot {
      text-align: center; margin: 52px auto 0; max-width: 820px; padding: 0 32px;
      font-family: 'Caveat','Comic Sans MS',cursive;
      font-size: 19px; color: rgba(255,255,255,0.5); line-height: 1.5;
    }
    .ideas-foot b { color: var(--yellow); font-weight: 700; }

    /* ── FAQ — white, tight, clean contrast after dark examples ── */
    /* ── LIVE KOLABS ── */
    .section-live { background: #fff; padding: 112px 0 104px; border-top: 1px solid rgba(13,17,20,0.07); }
    .live-track { max-width: 1200px; margin: 0 auto; padding: 0 56px; }
    .live-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; flex-wrap: wrap; }
    .live-lead { max-width: 520px; margin-top: 14px; color: rgba(13,17,20,0.62); font-size: 16px; line-height: 1.55; }
    .live-all {
      display: inline-flex; align-items: center; gap: 7px; height: 46px; padding: 0 22px;
      border-radius: 999px; border: 2px solid var(--dark); background: #fff;
      font-size: 14px; font-weight: 700; color: var(--dark); text-decoration: none;
      transition: background .18s ease, color .18s ease; white-space: nowrap;
    }
    .live-all:hover { background: var(--dark); color: var(--yellow); }
    .live-grid { margin-top: 36px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
    .live-card {
      display: flex; flex-direction: column; padding: 24px 22px 20px;
      border: 1px solid rgba(13,17,20,0.12); border-radius: 20px; background: #FDFBF7;
      text-decoration: none; color: var(--dark);
      transition: border-color .18s ease, transform .18s ease, box-shadow .18s ease;
    }
    .live-card:hover { border-color: var(--dark); transform: translateY(-2px); box-shadow: 0 14px 34px rgba(13,17,20,0.08); }
    .live-kind { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.14em; color: var(--purple); }
    .live-title { font-family: 'Anton', sans-serif; text-transform: uppercase; font-size: 21px; line-height: 1.05; margin-top: 9px; }
    .live-poster { font-size: 13px; color: rgba(13,17,20,0.5); margin-top: 7px; }
    .live-rows { margin-top: 16px; display: flex; flex-direction: column; gap: 5px; }
    .live-row { font-size: 13.5px; line-height: 1.4; color: rgba(13,17,20,0.78); }
    .live-row b { font-weight: 700; color: var(--dark); }
    .live-meta { margin-top: 16px; font-size: 11.5px; font-weight: 600; color: rgba(13,17,20,0.42); }
    .live-go { margin-top: auto; padding-top: 16px; font-size: 13px; font-weight: 700; }
    .live-card:hover .live-go { text-decoration: underline; }

    .section-faq { background: #fff; padding: 112px 0; border-top: 1px solid rgba(13,17,20,0.07); }
    .faq-track {
      max-width: 1280px; margin: 0 auto; padding: 0 48px;
      display: grid; grid-template-columns: 1fr 1.6fr; gap: 80px; align-items: start;
    }
    .faq-heading-col {
      position: sticky; top: 120px;
    }
    .faq-heading-col .section-title { margin-bottom: 16px; }
    .faq-heading-col p {
      font-size: 14px; color: rgba(13,17,20,0.45); line-height: 1.7;
    }
    .faq-list { display: flex; flex-direction: column; gap: 2px; }
    .faq-item { border-bottom: 1px solid var(--mid); transition: border-color .2s; }
    .faq-item.open { border-bottom-color: var(--purple); }
    .faq-q {
      width: 100%; background: none; border: none; cursor: pointer;
      display: flex; justify-content: space-between; align-items: center;
      padding: 20px 4px; text-align: left;
      font-size: 15px; font-weight: 500; color: var(--dark);
    }
    .faq-icon { font-size: 20px; color: var(--purple); opacity: 0.5; transition: transform .25s, opacity .2s; flex-shrink: 0; }
    .faq-item.open .faq-icon { transform: rotate(45deg); opacity: 1; }
    .faq-q:hover .faq-icon { opacity: 1; }
    .faq-a {
      font-size: 14px; color: rgba(13,17,20,0.55); line-height: 1.7;
      max-height: 0; overflow: hidden; padding: 0 4px;
      transition: max-height .3s ease, padding .3s ease;
      border-left: 2px solid transparent;
    }
    .faq-item.open .faq-a { max-height: 140px; padding: 0 12px 22px; border-left-color: var(--purple); }

    /* FAQ hand-drawn underline beneath "QUESTIONS" */
    .faq-underline {
      display: block;
      width: 180px; height: 14px;
      margin: 4px 0 0 -2px;
      color: var(--purple);
      opacity: 0.55;
      pointer-events: none;
    }
    .faq-underline svg { width: 100%; height: 100%; display: block; }

    /* ── CTA — yellow, full-bleed, with large image —— */
    .section-cta {
      background: var(--yellow); padding: 120px 0 104px; overflow: hidden;
      position: relative;
    }
    /* Full-bleed image strip at bottom of CTA */
    .cta-track {
      max-width: 1280px; margin: 0 auto; padding: 0 48px;
      display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: center;
    }
    .cta-left {}
    .cta-title {
      font-family: 'Anton', sans-serif; text-transform: uppercase;
      font-size: clamp(2.5rem, 5vw, 72px); color: var(--dark); line-height: 0.93;
      letter-spacing: -0.01em; margin-bottom: 0; margin-left: -3px;
    }
    .cta-right {
      padding-top: 8px;
    }
    .cta-sub {
      color: rgba(13,17,20,0.5); font-size: 15px; line-height: 1.7; margin-bottom: 36px;
      max-width: 340px;
    }
    .cta-btns { display: flex; flex-direction: column; gap: 10px; align-items: flex-start; }
    .cta-btn {
      display: inline-flex; align-items: center; height: 50px; padding: 0 32px;
      border-radius: 10px; font-weight: 600; font-size: 14px;
      text-decoration: none; transition: opacity .2s; min-width: 220px; justify-content: center;
    }
    .cta-btn:hover { opacity: 0.85; }
    .cta-btn.dark { background: var(--dark); color: #fff; }
    .cta-btn.white { background: #fff; color: var(--dark); }
    .cta-fine {
      margin-top: 20px; font-size: 11px; color: rgba(13,17,20,0.35); font-weight: 400;
    }

    /* CTA tiny handwritten note pointing at the primary button */
    .cta-handnote {
      position: relative;
      display: inline-flex; align-items: center; gap: 8px;
      font-family: 'Caveat', 'Comic Sans MS', cursive;
      font-size: 19px;
      color: rgba(13,17,20,0.55);
      transform: rotate(-4deg);
      transform-origin: left center;
      margin: -6px 0 14px 6px;
      white-space: nowrap;
      pointer-events: none;
    }
    .cta-handnote svg {
      width: 38px; height: 22px;
      opacity: 0.7;
    }

    /* ── FOOTER ── */
    footer { background: var(--dark); color: #fff; padding: 64px 48px 48px; }
    .footer-inner {
      max-width: 1280px; margin: 0 auto;
      display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;
      padding-bottom: 36px; margin-bottom: 32px; border-bottom: 1px solid rgba(255,255,255,0.07);
    }
    .footer-links { display: flex; gap: 24px; flex-wrap: wrap; }
    .footer-links a { color: rgba(255,255,255,0.35); text-decoration: none; font-size: 13px; font-weight: 500; transition: color .2s; }
    .footer-links a:hover { color: #ff8c52; }
    .footer-copy { text-align: center; color: rgba(255,255,255,0.18); font-size: 12px; line-height: 1.8; max-width: 1280px; margin: 0 auto; }

    /* ── IMAGES ── */

    /* Shared film-grain texture overlay */
    .img-film {
      position: relative; border-radius: 18px; overflow: hidden;
      display: block;
    }
    .img-film::after {
      content: '';
      position: absolute; inset: 0;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.08'/%3E%3C/svg%3E");
      mix-blend-mode: overlay;
      pointer-events: none;
      border-radius: inherit;
    }
    .img-film img {
      display: block; width: 100%; height: 100%;
      object-fit: cover;
      filter: sepia(0.25) contrast(1.1) saturate(0.78) brightness(0.92);
    }

    /* Reveal section — typography-only, no image strip */
    .reveal-image-strip { display: none; }
    .reveal-image-strip img {
      display: none;
    }

    /* CTA polaroid */
    .cta-polaroid {
      position: absolute;
      bottom: -32px; right: -24px;
      width: 180px;
      background: #fff;
      padding: 10px 10px 32px;
      border-radius: 4px;
      box-shadow: 0 16px 48px rgba(0,0,0,0.14), 0 4px 12px rgba(0,0,0,0.08);
      transform: rotate(3.5deg);
      z-index: 2;
    }
    .cta-polaroid img {
      display: block; width: 100%; aspect-ratio: 1;
      object-fit: cover;
      filter: sepia(0.28) contrast(1.1) saturate(0.75) brightness(0.9);
    }
    .cta-polaroid-caption {
      text-align: center; margin-top: 8px;
      font-size: 10px; color: rgba(13,17,20,0.4);
      font-family: 'Inter', sans-serif; font-weight: 500;
      letter-spacing: 0.03em;
    }
    .cta-left { position: relative; }

    /* ── FADE IN ── */
    .fade { opacity: 0; transform: translateY(16px); transition: opacity .5s ease-out, transform .5s ease-out; }
    .fade.in { opacity: 1; transform: translateY(0); }

    /* ── MOBILE ── */
    /* ── STICKY MOBILE CTA ──
       The header nav collapses on mobile, so this is the persistent way back to
       the web app. It slides in only once the hero CTA has scrolled out of view
       (see the kbSticky script) so the two never compete. */
    .kb-sticky { display: none; }

    @media (max-width: 900px) {
      header { padding: 12px 20px; }
      /* Keep the header's log-in + get-started CTAs on mobile; only the in-page
         section anchors collapse. The old hamburger opened nothing, so it goes. */
      nav { display: flex; gap: 16px; }
      .nav-links { display: none; }
      .btn-nav { padding: 10px 18px; font-size: 12px; }
      .menu-icon { display: none; }
      .logo-mark { height: 32px; }

      .kb-sticky {
        display: flex; align-items: center; justify-content: space-between; gap: 14px;
        position: fixed; left: 0; right: 0; bottom: 0; z-index: 120;
        padding: 12px 18px; padding-bottom: calc(12px + env(safe-area-inset-bottom));
        background: rgba(13,17,20,0.96);
        backdrop-filter: blur(10px);
        border-top: 1px solid rgba(255,226,140,0.22);
        transform: translateY(120%); opacity: 0; visibility: hidden;
        transition: transform .3s ease, opacity .3s ease, visibility .3s;
      }
      .kb-sticky.is-visible { transform: translateY(0); opacity: 1; visibility: visible; }
      .kb-sticky__login { color: rgba(255,255,255,0.7); font-size: 13px; font-weight: 600; text-decoration: none; white-space: nowrap; }
      .kb-sticky__cta {
        flex: 1; text-align: center; background: var(--yellow); color: var(--dark);
        padding: 13px 18px; border-radius: 999px; font-size: 14px; font-weight: 800; text-decoration: none;
      }
      .hero-cta { gap: 16px; }
      .hero-cta-btn { width: 100%; justify-content: center; }

      .hero { min-height: 100svh; padding: 96px 8px 40px; overflow: hidden; }
      .hero-inner { grid-template-columns: 1fr; gap: 32px; }
      .hero-left { padding: 0 16px; max-width: none; }
      .hero-right { justify-content: center; padding: 0 16px 24px; height: auto; min-height: 440px; }
      .hero h1 { font-size: clamp(2.2rem, 10vw, 56px); width: auto; max-width: 100%; }
      .phone-wrap { transform: rotate(2deg) translateY(0); }
      .chip-runners { left: 8px; bottom: 4px; }
      .chip-notification { right: 8px; top: 16px; }
      .manifesto-phone { min-height: 380px; }
      .surface-stack { max-width: 400px; }
      .browser-body { height: 208px; grid-template-columns: 64px 1fr; }
      .phone-mini { transform: scale(0.46); right: -18px; bottom: -48px; }
      .surface-caption { margin-top: 58px; }

      .manifesto-track { padding: 0 24px; grid-template-columns: 1fr; gap: 40px; }
      .manifesto-headline { font-size: clamp(2.4rem, 10vw, 56px); }
      .manifesto-action { font-size: clamp(2rem, 9vw, 48px); }
      .manifesto-footnote { margin-top: 0; }
      .manifesto-bubble { font-size: 13px; }

      .reveal-inner { padding: 96px 24px 96px; gap: 28px; }
      .reveal-kicker::before, .reveal-kicker::after { width: 24px; }
      .reveal-kicker { letter-spacing: 0.24em; gap: 12px; }

      .how-header { grid-template-columns: 1fr; padding: 0 24px; gap: 12px; }
      .live-track { padding: 0 24px; }
      .live-grid { grid-template-columns: 1fr; gap: 14px; }
      .how-steps { grid-template-columns: 1fr 1fr; padding: 0 24px; }
      .how-step::after { display: none; }
      .how-deco-arrow, .how-deco-squiggle { display: none; }
      .how-deco-sparkle-2 { left: 4%; top: 180px; }
      .how-step-illo { width: 48px; height: 48px; top: -22px; right: 8px; }

      .ideas-head { padding: 0 24px; margin-bottom: 48px; }
      .ideas-matrix { grid-template-columns: repeat(4, 1fr); gap: 34px 18px; padding: 0 24px; }
      .ideas-foot { margin-top: 40px; }

      .faq-track { grid-template-columns: 1fr; padding: 0 24px; gap: 32px; }
      .faq-heading-col { position: static; }

      .cta-track { grid-template-columns: 1fr; padding: 0 24px; gap: 40px; }
      .cta-btns { flex-direction: row; flex-wrap: wrap; }
      .cta-polaroid { display: none; }

      /* Extra bottom padding clears the sticky CTA bar. */
      footer { padding: 48px 24px 104px; }
    }
    @media (max-width: 540px) {
      .how-steps { grid-template-columns: 1fr; }
      .ideas-matrix { grid-template-columns: repeat(2, 1fr); gap: 30px 16px; }
    }
  </style>
</head>
<body style="font-size: 12px;">

<!-- NAV -->
<header>
  <div class="logo">
    <img class="logo-mark" src="/brand/kolabing-logo.webp" alt="Kolabing" width="560" height="250" fetchpriority="high"/>
  </div>
  <nav>
    <span class="nav-links">
      <a href="{{ route('for-businesses') }}">businesses</a>
      <a href="{{ route('for-communities') }}">communities</a>
      <a href="{{ route('public-events') }}">what's on</a>
      <a href="{{ route('public-kolabs') }}">kolabs</a>
      <a href="{{ route('pricing') }}">pricing</a>
      <a href="#how-it-works">how it works</a>
    </span>
    <a class="nav-login" href="{{ $appLogin }}">log in</a>
    <a class="btn-nav" href="{{ $appRegister }}">get started free</a>
  </nav>
</header>

<!-- HERO -->
<section class="hero">
  <div class="hero-inner">

  <!-- Left: text panel -->
  <div class="hero-left">
    <div class="hero-badge">free · works in your browser</div>
    <h1>
      <span class="line">MAKE <span class="accent">KOLABS.</span></span>
      <span class="line">FILL YOUR BUSINESS.</span>
      <span class="line">REWARD YOUR <span class="accent">COMMUNITY.</span></span>
    </h1>
    <div class="hero-rule" aria-hidden="true"></div>
    <p class="hero-lead">Kolabing matches local businesses with real communities to create collaborations people actually want to join.</p>
    <p class="hero-sub">real kolabs · real communities · <span class="accent">real growth</span></p>
    <div class="hero-note" aria-hidden="true">
      <svg viewBox="0 0 40 18" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round">
        <path d="M2 14 C 12 4, 22 6, 36 4" />
        <path d="M30 2 L 36 4 L 32 9" />
      </svg>
      made with ☀ in barcelona
    </div>
    <div class="hero-cta">
      <a href="{{ $appRegister }}" class="hero-cta-btn">
        start free
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h13"/><path d="M13 6l6 6-6 6"/></svg>
      </a>
      <a href="{{ $appLogin }}" class="hero-cta-login">already on kolabing? <span>log in</span></a>
    </div>
    <p class="hero-fine">free for communities · no app needed · cancel anytime</p>
  </div>

  <!-- Right: vertical cloud matcher (community + business) -->
  <div class="hero-right">
    <div class="matcher" data-screen-label="hero-matcher">
      <div class="matcher-stage">

        <!-- TOP CLOUD — community -->
        <div class="mcloud mcloud-top">
          <div class="mcloud-inner">
            <svg viewBox="0 0 540 360" aria-hidden="true">
              <defs>
                <filter id="goo-top" x="-12%" y="-12%" width="124%" height="124%">
                  <feGaussianBlur in="SourceGraphic" stdDeviation="11" result="b"></feGaussianBlur>
                  <feColorMatrix in="b" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 22 -10" result="g"></feColorMatrix>
                  <feComposite in="SourceGraphic" in2="g" operator="atop"></feComposite>
                </filter>
              </defs>
              <g filter="url(#goo-top)" fill="#FFE28C">
                <circle cx="185" cy="158" r="98"></circle>
                <circle cx="312" cy="132" r="110"></circle>
                <circle cx="410" cy="176" r="90"></circle>
                <circle cx="122" cy="214" r="78"></circle>
                <circle cx="255" cy="236" r="100"></circle>
                <circle cx="372" cy="236" r="86"></circle>
                <circle cx="452" cy="202" r="64"></circle>
                <circle cx="300" cy="206" r="104"></circle>
              </g>
            </svg>
          </div>
          <div class="mcloud-text" id="textLeft">RUN CLUB</div>
        </div>

        <!-- MATCH MOMENT — plus + rotating outcome -->
        <div class="matcher-bridge">
          <span class="matcher-plus" aria-hidden="true">+</span>
          <span class="matcher-outcome" id="collabOutcome">MORNING RUN + COFFEE</span>
        </div>

        <!-- BOTTOM CLOUD — business -->
        <div class="mcloud mcloud-bottom">
          <div class="mcloud-inner">
            <svg viewBox="0 0 540 360" aria-hidden="true">
              <defs>
                <filter id="goo-bot" x="-12%" y="-12%" width="124%" height="124%">
                  <feGaussianBlur in="SourceGraphic" stdDeviation="11" result="b"></feGaussianBlur>
                  <feColorMatrix in="b" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 22 -10" result="g"></feColorMatrix>
                  <feComposite in="SourceGraphic" in2="g" operator="atop"></feComposite>
                </filter>
              </defs>
              <g filter="url(#goo-bot)" fill="#FFE28C">
                <circle cx="185" cy="158" r="98"></circle>
                <circle cx="312" cy="132" r="110"></circle>
                <circle cx="410" cy="176" r="90"></circle>
                <circle cx="122" cy="214" r="78"></circle>
                <circle cx="255" cy="236" r="100"></circle>
                <circle cx="372" cy="236" r="86"></circle>
                <circle cx="452" cy="202" r="64"></circle>
                <circle cx="300" cy="206" r="104"></circle>
              </g>
            </svg>
          </div>
          <div class="mcloud-text" id="textRight">COFFEE SHOP</div>
        </div>

      </div>
    </div>
  </div><!-- /hero-right -->
  </div><!-- /hero-inner -->
</section>

<!-- MATCH BAND — thin yellow strip -->
<section class="match-band" data-screen-label="match-band">
  <div class="match-band-inner">
    <span class="match-band-text">We match businesses with communities.</span>
  </div>
</section>

<!-- rotating-pairs script for the hero matcher -->
<script>
  (function(){
    const PAIRS = [
      {c:"RUN CLUB",          b:"COFFEE SHOP",     o:"MORNING RUN + COFFEE"},
      {c:"YOGA GROUP",        b:"WELLNESS STUDIO", o:"SUNRISE FLOW SESSION"},
      {c:"SOCIAL CLUB",       b:"RESTAURANT",      o:"SUPPER CLUB NIGHT"},
      {c:"CYCLING CREW",      b:"LOCAL BRAND",     o:"WEEKEND GROUP RIDE"},
      {c:"YOGA GROUP",        b:"ROOFTOP",         o:"ROOFTOP SUNSET FLOW"},
      {c:"COWORKING CIRCLE",  b:"STUDIO",          o:"COMMUNITY WORKDAY"},
      {c:"RUN CLUB",          b:"ROOFTOP",         o:"SUNSET RUN + DRINKS"},
      {c:"SOCIAL CLUB",       b:"LOCAL BRAND",     o:"LAUNCH MEETUP"},
      {c:"CYCLING CREW",      b:"COFFEE SHOP",     o:"POST-RIDE COFFEE"},
      {c:"COWORKING CIRCLE",  b:"WELLNESS STUDIO", o:"MIDDAY RESET"}
    ];
    const COMMS = [...new Set(PAIRS.map(p=>p.c))];
    const BIZ   = [...new Set(PAIRS.map(p=>p.b))];

    const top = document.getElementById('textLeft');
    const bot = document.getElementById('textRight');
    const out = document.getElementById('collabOutcome');
    if(!top || !bot || !out) return;
    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const rnd = n => Math.floor(Math.random()*n);

    let idx = 0;
    function settle(el){ el.classList.remove('settling'); void el.offsetWidth; el.classList.add('settling'); }

    function cycle(){
      out.classList.remove('show');
      const dur = 2000, step = 85, start = performance.now();
      function tick(now){
        if(now - start < dur){
          top.textContent = COMMS[rnd(COMMS.length)];
          bot.textContent = BIZ[rnd(BIZ.length)];
          setTimeout(()=>requestAnimationFrame(tick), step);
        } else { stop(); }
      }
      requestAnimationFrame(tick);
    }

    function stop(){
      const p = PAIRS[idx % PAIRS.length]; idx++;
      top.textContent = p.c;
      bot.textContent = p.b;
      out.textContent = p.o;
      settle(top); settle(bot);
      setTimeout(()=>out.classList.add('show'), 260);
      setTimeout(cycle, 2200);
    }

    if(reduce){
      const p = PAIRS[0];
      top.textContent=p.c; bot.textContent=p.b; out.textContent=p.o; out.classList.add('show');
      return;
    }
    out.classList.remove('show');
    setTimeout(cycle, 600);
  })();
</script>

<!-- JOURNEY + PHONE SECTION -->
<section class="section-manifesto" data-screen-label="journey">
  <div class="manifesto-track">

    <!-- LEFT: journey copy -->
    <div class="manifesto-copy fade in">
      <div class="manifesto-support">
        <p>The best marketing doesn&rsquo;t feel like marketing. It feels like a plan with people you trust.</p>
      </div>
      <div class="manifesto-friend-label">THE COMMUNITY POSTS</div>
      <div class="manifesto-bubble">&ldquo;morning run + coffee tomorrow? ☀️&rdquo;</div>
      <div class="manifesto-actions">
        <div class="manifesto-action fade in" style="transition-delay:.18s">THEY VISIT.</div>
        <div class="manifesto-action-rule" aria-hidden="true"></div>
        <div class="manifesto-action fade in" style="transition-delay:.26s">THEY BUY.</div>
        <div class="manifesto-action-rule" aria-hidden="true"></div>
        <div class="manifesto-action fade in" style="transition-delay:.34s">THEY SHARE IT.</div>
        <div class="manifesto-action-rule" aria-hidden="true"></div>
        <div class="manifesto-action fade in" style="transition-delay:.42s">THEY COME BACK.</div>
      </div>
    </div>

    <!-- RIGHT: both surfaces — web panel + mobile app -->
    <div class="manifesto-phone fade in" style="transition-delay:.15s">
      <div class="surface-stack">

        <!-- Floating chip: runners -->
        <div class="chip chip-runners">
          <div class="chip-icon yellow">🏃</div>
          <div class="chip-text">
            <div class="chip-title">+30 runners joined</div>
            <div class="chip-sub">Tuesday morning run · 08:00</div>
          </div>
        </div>

        <!-- Floating chip: notification -->
        <div class="chip chip-notification">
          <div class="chip-icon purple">☕</div>
          <div class="chip-text">
            <div class="chip-title">New Kolab match</div>
            <div class="chip-sub">Café Mira wants to collab</div>
          </div>
        </div>

        <!-- Web panel -->
        <div class="browser-frame" role="img" aria-label="The Kolabing web panel at app.kolabing.com">
          <div class="browser-bar" aria-hidden="true">
            <span class="browser-dot"></span>
            <span class="browser-dot"></span>
            <span class="browser-dot"></span>
            <div class="browser-url">app.kolabing.com</div>
          </div>
          <div class="browser-body" aria-hidden="true">
            <aside class="wp-side">
              <div class="wp-logo"></div>
              <div class="wp-nav is-on"></div>
              <div class="wp-nav"></div>
              <div class="wp-nav"></div>
              <div class="wp-nav"></div>
            </aside>
            <div class="wp-main">
              <div class="wp-search"></div>
              <div class="wp-cards">
                <div class="wp-card">
                  <div class="wp-card-img"></div>
                  <div class="wp-card-body"><div class="wp-line"></div><div class="wp-line short"></div></div>
                </div>
                <div class="wp-card">
                  <div class="wp-card-img alt"></div>
                  <div class="wp-card-body"><div class="wp-line"></div><div class="wp-tag"></div></div>
                </div>
                <div class="wp-card">
                  <div class="wp-card-img alt"></div>
                  <div class="wp-card-body"><div class="wp-line"></div><div class="wp-line short"></div></div>
                </div>
                <div class="wp-card">
                  <div class="wp-card-img"></div>
                  <div class="wp-card-body"><div class="wp-line"></div><div class="wp-tag"></div></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Mobile app, riding on top of the panel -->
        <div class="phone-mini">
          <div class="phone-wrap" id="phoneWrap">
            <div class="phone-frame">
              <div class="phone-screen">
                <video autoplay muted loop playsinline onerror="this.parentElement.style.background='#1a1a2e'">
                  <source src="assets/hero2.mp4" type="video/mp4"/>
                  <source src="assets/hero.mp4" type="video/mp4"/>
                </video>
              </div>
              <div class="phone-notch" aria-hidden="true"></div>
              <div class="phone-btn-right" aria-hidden="true"></div>
              <div class="phone-btn-left-1" aria-hidden="true"></div>
              <div class="phone-btn-left-2" aria-hidden="true"></div>
            </div>
            <div class="phone-glow" aria-hidden="true"></div>
          </div>
        </div>

      </div>

      <p class="surface-caption">one account · <strong>web panel</strong> + <strong>mobile app</strong></p>
    </div>

  </div>
</section>

<!-- REVEAL -->
<section class="section-reveal">
  <div class="reveal-inner">
    <div class="reveal-kicker fade">The idea in three words</div>
    <div class="reveal-text-block fade" style="transition-delay:.08s">
      <div class="reveal-line2">MATCH. <span class="kol">KOLAB.</span> GROW.</div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="section-how" id="how-it-works">

  <!-- Decorative graphics across the section -->

  <div class="how-deco how-deco-sparkle-1" aria-hidden="true">
    <svg viewBox="0 0 40 40" fill="none" stroke="#0D1114" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
      <path d="M20 4 L 22 18 L 36 20 L 22 22 L 20 36 L 18 22 L 4 20 L 18 18 Z" fill="#FFE28C"/>
    </svg>
  </div>

  <div class="how-deco how-deco-sparkle-2" aria-hidden="true">
    <svg viewBox="0 0 40 40" fill="none" stroke="#0D1114" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
      <path d="M20 6 L 22 18 L 34 20 L 22 22 L 20 34 L 18 22 L 6 20 L 18 18 Z" fill="none"/>
    </svg>
  </div>

  <div class="how-deco how-deco-sparkle-3" aria-hidden="true">
    <svg viewBox="0 0 40 40" fill="none" stroke="#ff6114" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
      <path d="M20 5 L 22 18 L 35 20 L 22 22 L 20 35 L 18 22 L 5 20 L 18 18 Z" fill="#FFE28C"/>
    </svg>
  </div>

  <div class="how-deco how-deco-squiggle" aria-hidden="true">
    <svg viewBox="0 0 60 24" fill="none" stroke="#0D1114" stroke-width="2" stroke-linecap="round">
      <path d="M3 12 Q 10 2, 17 12 T 31 12 T 45 12 T 57 12"/>
    </svg>
  </div>

  <!-- Flowing wavy arrow across steps -->
  <div class="how-deco how-deco-arrow" aria-hidden="true">
    <svg viewBox="0 0 1200 110" preserveAspectRatio="none" fill="none" stroke="#0D1114" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M20 78 Q 140 30 280 60 Q 420 90 560 50 Q 700 18 840 56 Q 980 94 1130 40"/>
      <!-- arrowhead -->
      <path d="M1112 28 L 1134 38 L 1118 56" />
      <!-- a few tick marks along the way -->
      <path d="M280 60 l 2 -8"  />
      <path d="M560 50 l 0 -8"  />
      <path d="M840 56 l -1 -8" />
    </svg>
  </div>

  <div class="how-header">
    <div class="how-heading-block">
      <div class="section-label fade">process</div>
      <div class="section-title fade"><span class="how-word"><svg class="how-word-circle" viewBox="0 0 200 120" preserveAspectRatio="none" aria-hidden="true"><path d="M28 18 C 8 28, 4 64, 22 88 C 44 112, 110 116, 158 104 C 196 94, 200 64, 188 38 C 174 12, 110 4, 64 12 C 48 14, 36 18, 26 24"/></svg>HOW</span><br>IT<br>WORKS</div>
    </div>
    <div class="section-sub fade" style="padding-bottom: 0; align-self: end;">
      Post what you&rsquo;re looking for. Find the right community or business.<br>
      Chat, agree, and make it happen in real life.
    </div>
  </div>

  <div class="how-steps">

    <!-- 01: POST A KOLAB — pinned note -->
    <div class="how-step fade">
      <div class="how-step-illo" aria-hidden="true">
        <svg viewBox="0 0 64 64" fill="none" stroke="#0D1114" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
          <rect x="12" y="16" width="40" height="38" rx="2" fill="#FFE28C" transform="rotate(-5 32 35)"/>
          <line x1="20" y1="28" x2="42" y2="26" transform="rotate(-5 32 35)"/>
          <line x1="20" y1="36" x2="38" y2="34" transform="rotate(-5 32 35)"/>
          <line x1="20" y1="44" x2="34" y2="42" transform="rotate(-5 32 35)"/>
          <circle cx="32" cy="14" r="4" fill="#ff6114"/>
          <line x1="32" y1="18" x2="32" y2="22"/>
        </svg>
      </div>
      <span class="how-num">01</span>
      <h4>Post a Kolab</h4>
      <p>Share what you're looking for: an event, venue, product, audience or experience.</p>
    </div>

    <!-- 02: GET MATCHES — two arrows converging to a star -->
    <div class="how-step fade" style="transition-delay:.08s">
      <div class="how-step-illo" aria-hidden="true">
        <svg viewBox="0 0 64 64" fill="none" stroke="#0D1114" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
          <!-- left curving arrow heading toward center-bottom -->
          <path d="M6 12 C 14 22, 20 30, 26 38"/>
          <polyline points="22,32 26,38 20,38"/>
          <!-- right curving arrow heading toward center-bottom -->
          <path d="M58 12 C 50 22, 44 30, 38 38"/>
          <polyline points="42,32 38,38 44,38"/>
          <!-- small four-point sparkle/star at meeting point -->
          <path d="M32 44 L 33.4 50 L 39 51.5 L 33.4 53 L 32 59 L 30.6 53 L 25 51.5 L 30.6 50 Z" fill="#FFE28C"/>
        </svg>
      </div>
      <span class="how-num">02</span>
      <h4>Find the right match</h4>
      <p>Relevant communities or businesses find you instantly.</p>
    </div>

    <!-- 03: CONNECT — overlapping chat bubbles -->
    <div class="how-step fade" style="transition-delay:.16s">
      <div class="how-step-illo" aria-hidden="true">
        <svg viewBox="0 0 64 64" fill="none" stroke="#0D1114" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
          <!-- back bubble, yellow -->
          <path d="M10 12 h28 a4 4 0 0 1 4 4 v14 a4 4 0 0 1 -4 4 h-16 l-7 6 v-6 h-5 a4 4 0 0 1 -4 -4 v-14 a4 4 0 0 1 4 -4 z" fill="#FFE28C"/>
          <!-- front bubble, white -->
          <path d="M26 30 h24 a4 4 0 0 1 4 4 v12 a4 4 0 0 1 -4 4 h-14 l-7 6 v-6 h-3 a4 4 0 0 1 -4 -4 v-12 a4 4 0 0 1 4 -4 z" fill="#ffffff"/>
          <!-- typing dots -->
          <circle cx="34" cy="40" r="1.6" fill="#0D1114" stroke="none"/>
          <circle cx="40" cy="40" r="1.6" fill="#0D1114" stroke="none"/>
          <circle cx="46" cy="40" r="1.6" fill="#0D1114" stroke="none"/>
        </svg>
      </div>
      <span class="how-num">03</span>
      <h4>Agree in chat</h4>
      <p>Chat in-app, agree on the details, and confirm the plan.</p>
    </div>

    <!-- 04: MAKE IT HAPPEN — sparkle burst -->
    <div class="how-step fade" style="transition-delay:.24s">
      <div class="how-step-illo" aria-hidden="true">
        <svg viewBox="0 0 64 64" fill="none" stroke="#0D1114" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
          <!-- big sparkle -->
          <path d="M32 8 L 36 28 L 56 32 L 36 36 L 32 56 L 28 36 L 8 32 L 28 28 Z" fill="#FFE28C"/>
          <!-- small sparkle top right -->
          <path d="M52 12 L 53 16 L 57 17 L 53 18 L 52 22 L 51 18 L 47 17 L 51 16 Z" fill="#ff6114" stroke="none"/>
          <!-- small dot bottom left -->
          <circle cx="12" cy="52" r="2" fill="#0D1114" stroke="none"/>
          <!-- movement lines -->
          <line x1="6" y1="14" x2="11" y2="12"/>
          <line x1="58" y1="52" x2="53" y2="50"/>
        </svg>
      </div>
      <span class="how-num">04</span>
      <h4>Host it IRL</h4>
      <p>Bring people together and turn the kolab into real visits, content or community value.</p>
    </div>
  </div>
</section>

<!-- EXAMPLES -->
<section class="section-examples">

  <div class="ideas-head">
    <div class="section-label fade">kolab ideas</div>
    <h2 class="section-title fade">BUILD ANY KIND OF KOLAB</h2>
    <p class="ideas-sub fade">One community. One business goal. Endless ways to make people show up, try, share, review, buy or come back.</p>
    <div class="ideas-formula fade">
      <span class="cap">Community</span><i>+</i><span class="cap">Business goal</span><i>=</i><span class="hl">Kolab</span>
    </div>
  </div>

  <div class="ideas-matrix">
    <span class="goal-note fade" aria-hidden="true">what&rsquo;s your business goal?<svg class="gn-arrow" viewBox="0 0 40 64" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4 C 1 28, 28 30, 31 54"/><path d="M23 48 L 32 57 L 37 45"/></svg></span>
    <div class="ideas-col fade" style="--accent:#FFD24D;transition-delay:0s">
      <div class="goal">
        <span class="goal-badge"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M16 6.4a3 3 0 0 1 0 5.8"/><path d="M21 20c0-2.5-1.4-4.7-3.6-5.7"/></svg></span>
        <span class="goal-label">Fill a place</span>
      </div>
      <svg class="goal-arrow" viewBox="0 0 18 30" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 2 L 9 22"/><path d="M3.5 16.5 L 9 23 L 14.5 16.5"/></svg>
      <figure class="polaroid">
        <div class="polaroid-img"><img src="uploads/kolab-app-preview.webp" alt="Run club cheers-ing coffee cups after a morning run" width="1600" height="862" loading="lazy" decoding="async"/></div>
        <figcaption><strong>Run club + café</strong><span>Morning run + coffee</span></figcaption>
      </figure>
    </div>
    <div class="ideas-col fade" style="--accent:#5BD0C0;transition-delay:.05s">
      <div class="goal">
        <span class="goal-badge"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3h6"/><path d="M10 3v6l-5 9a2 2 0 0 0 1.8 3h10.4a2 2 0 0 0 1.8-3l-5-9V3"/><path d="M7.4 15.5h9.2"/></svg></span>
        <span class="goal-label">Test a product</span>
      </div>
      <svg class="goal-arrow" viewBox="0 0 18 30" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 2 L 9 22"/><path d="M3.5 16.5 L 9 23 L 14.5 16.5"/></svg>
      <figure class="polaroid">
        <div class="polaroid-img"><img src="uploads/kolab-run-club-cafe.webp" alt="Cycling crew on the road testing gear" width="1600" height="872" loading="lazy" decoding="async"/></div>
        <figcaption><strong>Cycling crew + hydration brand</strong><span>Ride test</span></figcaption>
      </figure>
    </div>
    <div class="ideas-col fade" style="--accent:#9B7BFF;transition-delay:.1s">
      <div class="goal">
        <span class="goal-badge"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 14.5c-1.6 1.3-2 5.5-2 5.5s4.2-.4 5.5-2"/><path d="M12 2.5c4 2.2 6 6.3 6 11l-3.5 3.5h-5L6 13.5c0-4.7 2-8.8 6-11z"/><circle cx="12" cy="9" r="1.6"/></svg></span>
        <span class="goal-label">Launch</span>
      </div>
      <svg class="goal-arrow" viewBox="0 0 18 30" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 2 L 9 22"/><path d="M3.5 16.5 L 9 23 L 14.5 16.5"/></svg>
      <figure class="polaroid">
        <div class="polaroid-img"><img src="uploads/kolab-yoga-studio-brunch.webp" alt="Yoga class at sunset on a rooftop" width="1600" height="872" loading="lazy" decoding="async"/></div>
        <figcaption><strong>Yoga club + activewear brand</strong><span>Try-on flow</span></figcaption>
      </figure>
    </div>
    <div class="ideas-col fade" style="--accent:#FF8FA8;transition-delay:.15s">
      <div class="goal">
        <span class="goal-badge"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 4H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3v4l4.5-4H20a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1z"/><circle cx="8.5" cy="10" r="1.1" fill="#15181c" stroke="none"/><circle cx="12" cy="10" r="1.1" fill="#15181c" stroke="none"/><circle cx="15.5" cy="10" r="1.1" fill="#15181c" stroke="none"/></svg></span>
        <span class="goal-label">Feedback</span>
      </div>
      <svg class="goal-arrow" viewBox="0 0 18 30" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 2 L 9 22"/><path d="M3.5 16.5 L 9 23 L 14.5 16.5"/></svg>
      <figure class="polaroid">
        <div class="polaroid-img"><img src="uploads/kolab-idea-skincare-feedback.webp" alt="Women's group testing skincare products together" width="1600" height="1200" loading="lazy" decoding="async"/></div>
        <figcaption><strong>Women&rsquo;s group + skincare brand</strong><span>Product testing circle</span></figcaption>
      </figure>
    </div>
    <div class="ideas-col fade" style="--accent:#6FB1FF;transition-delay:.2s">
      <div class="goal">
        <span class="goal-badge"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7.5h3L8.4 5h7.2L17 7.5h3a1 1 0 0 1 1 1V18a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8.5a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="3.1"/></svg></span>
        <span class="goal-label">Content</span>
      </div>
      <svg class="goal-arrow" viewBox="0 0 18 30" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 2 L 9 22"/><path d="M3.5 16.5 L 9 23 L 14.5 16.5"/></svg>
      <figure class="polaroid">
        <div class="polaroid-img"><img src="uploads/kolab-idea-dog-walk-content.webp" alt="Dog community on a city photo walk with their dogs" width="1600" height="1200" loading="lazy" decoding="async"/></div>
        <figcaption><strong>Dog community + pet brand</strong><span>Dog photo walk</span></figcaption>
      </figure>
    </div>
    <div class="ideas-col fade" style="--accent:#FF9D5C;transition-delay:.25s">
      <div class="goal">
        <span class="goal-badge"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20.3l-1.5-1.4C5.4 14.3 2.5 11.7 2.5 8.5 2.5 6 4.5 4 7 4c1.6 0 3.1.8 3.9 2C11.9 4.8 13.4 4 15 4c2.5 0 4.5 2 4.5 4.5 0 3.2-2.9 5.8-8 10.4z"/></svg></span>
        <span class="goal-label">Loyalty</span>
      </div>
      <svg class="goal-arrow" viewBox="0 0 18 30" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 2 L 9 22"/><path d="M3.5 16.5 L 9 23 L 14.5 16.5"/></svg>
      <figure class="polaroid">
        <div class="polaroid-img"><img src="uploads/kolab-idea-wine-book-loyalty.webp" alt="Book club gathered around a table reading with wine in a cellar" width="1600" height="1200" loading="lazy" decoding="async"/></div>
        <figcaption><strong>Book club + wine bar</strong><span>Monthly tasting night</span></figcaption>
      </figure>
    </div>
  </div>

  <p class="ideas-foot fade"><b>And more:</b> discounts &middot; community perks &middot; reviews &middot; co-created merch &middot; challenges</p>
</section>

<!-- FAQ -->
{{--
  Live Kolabs. Real rows from the marketplace, not illustrations — the strongest
  argument that this is a working market is a working market.

  Rendered only when there is something to show: a homepage section announcing
  "nothing open" is worse than no section. The gate and the card contents come from
  the same service and the same rules as /kolabs (PublicKolabFeedService,
  PublicKolabPoster), so the shop window cannot promise what the listing hides. The
  card markup is written out here rather than reusing <x-kolab-card>, because this
  page is hand-rolled CSS and that component is Tailwind — sharing it would render
  unstyled.
--}}
@if (($activeKolabs ?? collect())->isNotEmpty())
<section class="section-live" id="live-kolabs">
  <div class="live-track">
    <div class="live-head">
      <div>
        <div class="section-label fade">live right now</div>
        <div class="section-title fade">OPEN KOLABS</div>
        <p class="live-lead fade" style="transition-delay:.1s">
          Real collaborations waiting for a partner. Both sides post: a community looking for a venue,
          a venue looking for a crowd. Browsing is free.
        </p>
      </div>
      <a class="live-all fade" style="transition-delay:.15s" href="{{ route('public-kolabs') }}">
        see all kolabs
        <svg width="14" height="10" viewBox="0 0 14 10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M1 5h11M8.5 1.5 12 5l-3.5 3.5"/>
        </svg>
      </a>
    </div>

    <div class="live-grid">
      @foreach ($activeKolabs as $liveKolab)
        @php
            $livePoster = \App\Support\PublicKolabPoster::describe($liveKolab);
            $liveIsAsk = $liveKolab->intent_type === \App\Enums\IntentType::CommunitySeeking;
            $liveGives = $liveIsAsk
                ? \App\Support\OfferOptionLabels::many('deliverable', $liveKolab->offers_in_return)
                : \App\Support\OfferOptionLabels::many('offering', $liveKolab->offering);
            $liveWants = $liveIsAsk
                ? \App\Support\OfferOptionLabels::many('need', $liveKolab->needs)
                : \App\Support\OfferOptionLabels::many('deliverable', $liveKolab->expects);
            $liveKind = match ($liveKolab->intent_type) {
                \App\Enums\IntentType::CommunitySeeking => 'community looking',
                \App\Enums\IntentType::VenuePromotion => 'venue offering',
                \App\Enums\IntentType::ProductPromotion => 'product offering',
            };
            $liveReach = $liveKolab->typical_attendance ?? $liveKolab->community_size;
        @endphp
        <a class="live-card fade" href="{{ \App\Support\PublicKolabLink::urlFor($liveKolab) }}">
          <span class="live-kind">{{ $liveKind }}</span>
          <span class="live-title">{{ $liveKolab->title }}</span>
          <span class="live-poster">{{ $livePoster['description'] }}</span>
          <span class="live-rows">
            @if ($liveGives !== [])
              <span class="live-row"><b>offers</b> {{ implode(' · ', array_slice($liveGives, 0, 2)) }}</span>
            @endif
            @if ($liveWants !== [])
              <span class="live-row"><b>wants</b> {{ implode(' · ', array_slice($liveWants, 0, 2)) }}</span>
            @endif
          </span>
          @if ($liveReach)
            <span class="live-meta">{{ $liveReach }} people</span>
          @endif
          <span class="live-go">see the kolab →</span>
        </a>
      @endforeach
    </div>
  </div>
</section>
@endif

<section class="section-faq" id="faq">
  <div class="faq-track">
    <div class="faq-heading-col">
      <div class="section-label fade">support</div>
      <div class="section-title fade" style="margin-bottom:6px;">QUESTIONS</div>
      <p class="fade" style="transition-delay:.1s; margin-top:14px;">Everything you need to know about getting started with kolabing.</p>
    </div>
    <div class="faq-list fade" style="transition-delay:.15s">
      <div class="faq-item">
        <button class="faq-q">is it free for communities? <span class="faq-icon">+</span></button>
        <div class="faq-a">yes — kolabing is always free for community leaders and groups.</div>
      </div>
      <div class="faq-item">
        <button class="faq-q">how do businesses get matched? <span class="faq-icon">+</span></button>
        <div class="faq-a">you post a Kolab and our system surfaces communities that match your location, audience, and goal.</div>
      </div>
      <div class="faq-item">
        <button class="faq-q">what kind of collaborations can I create? <span class="faq-icon">+</span></button>
        <div class="faq-a">events, venue partnerships, product trials, UGC sessions, weekly recurring meetups — anything involving real people in real places.</div>
      </div>
      <div class="faq-item">
        <button class="faq-q">where is kolabing available? <span class="faq-icon">+</span></button>
        <div class="faq-a">currently live in Barcelona, expanding to more cities soon.</div>
      </div>
      <div class="faq-item">
        <button class="faq-q">can I cancel anytime? <span class="faq-icon">+</span></button>
        <div class="faq-a">yes, no long-term commitment. paid plans for businesses can be cancelled at any time.</div>
      </div>
    </div>
  </div>
</section>

<!-- FINAL CTA -->
<section class="section-cta" id="cta">
  <div class="cta-track">
    <div class="cta-left fade">
      <div class="cta-title">BUSINESSES GET CUSTOMERS.
COMMUNITIES GET PERKS.</div>
    </div>
    <div class="cta-right fade" style="transition-delay:.12s">
      <p class="cta-sub">Kolabing connects both sides so local plans become real experiences, content and reasons to come back.</p>
      <div class="cta-btns">
        <div class="cta-handnote" aria-hidden="true">
          start with one kolab ✨
          <svg viewBox="0 0 40 22" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round">
            <path d="M4 4 C 10 10, 16 14, 22 18" />
            <path d="M16 18 L 22 18 L 22 12" />
          </svg>
        </div>
        <a href="{{ $appRegisterBusiness }}" class="cta-btn dark">join as a business</a>
        <a href="{{ $appRegisterCommunity }}" class="cta-btn white">join as a community</a>
      </div>
      <p class="cta-fine">free for communities · cancel anytime · <a href="{{ $appLogin }}" style="color:inherit;text-decoration:underline;text-underline-offset:3px">already have an account?</a></p>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="footer-inner">
    <div class="logo">
      <img class="logo-mark logo-mark--footer" src="/brand/kolabing-logo.webp" alt="Kolabing" width="560" height="250" loading="lazy"/>
    </div>
    <div class="footer-links">
      <a href="{{ route('for-businesses') }}">businesses</a>
      <a href="{{ route('for-communities') }}">communities</a>
      <a href="{{ route('public-events') }}">what's on</a>
      <a href="{{ route('public-kolabs') }}">active kolabs</a>
      <a href="{{ route('pricing') }}">pricing</a>
      <a href="{{ route('directory.index') }}">community directory</a>
      <a href="{{ route('blog.index') }}">blog</a>
      <a href="{{ route('terms') }}">terms</a>
      <a href="{{ route('privacy') }}">privacy</a>
      <a href="{{ route('support') }}">support</a>
      <a href="{{ route('careers') }}">careers</a>
    </div>
  </div>
  <p class="footer-copy">
    free for communities · paid plans for businesses · cancel anytime<br>
    © <span id="yr">2026</span> kolabing. built for real people, in real places.
  </p>
</footer>

<!-- STICKY MOBILE CTA — mobile only; slides in once the hero scrolls away. -->
<div class="kb-sticky" id="kbSticky">
  <a class="kb-sticky__login" href="{{ $appLogin }}">log in</a>
  <a class="kb-sticky__cta" href="{{ $appRegister }}">start free →</a>
</div>

<script>
(function(){
  var bar = document.getElementById('kbSticky');
  var hero = document.querySelector('.hero');
  if (!bar || !hero) return;
  function sync(){
    bar.classList.toggle('is-visible', hero.getBoundingClientRect().bottom < 80);
  }
  sync();
  window.addEventListener('scroll', sync, { passive: true });
  window.addEventListener('resize', sync);
})();
</script>

<script>
  // Subtle parallax on phone mockup
  const phoneWrap = document.getElementById('phoneWrap');
  if (phoneWrap) {
    document.addEventListener('mousemove', (e) => {
      const cx = window.innerWidth / 2;
      const cy = window.innerHeight / 2;
      const dx = (e.clientX - cx) / cx;
      const dy = (e.clientY - cy) / cy;
      const rotX = -dy * 4;
      const rotY = dx * 5;
      phoneWrap.style.transform = `rotate(-4deg) rotateX(${rotX}deg) rotateY(${rotY}deg)`;
    });
    document.addEventListener('mouseleave', () => {
      phoneWrap.style.transform = 'rotate(-4deg)';
    });
  }

  document.getElementById('yr').textContent = new Date().getFullYear();

  // Scroll fade-in
  const fadeEls = document.querySelectorAll('.fade');
  const obs = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); obs.unobserve(e.target); } });
  }, { threshold: 0.1 });
  fadeEls.forEach(el => obs.observe(el));

  // FAQ accordion
  document.querySelectorAll('.faq-q').forEach(btn => {
    btn.addEventListener('click', () => {
      const item = btn.closest('.faq-item');
      const isOpen = item.classList.contains('open');
      document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
      if (!isOpen) item.classList.add('open');
    });
  });
</script>

<!-- ============ NEWSLETTER + BOOK-A-CALL POP-UP ============ -->
<style>
  .kb-pop{position:fixed;inset:0;z-index:1000;display:none;align-items:center;justify-content:center;padding:20px}
  .kb-pop.is-open{display:flex}
  .kb-pop__backdrop{position:absolute;inset:0;background:rgba(13,17,20,.62);backdrop-filter:blur(3px)}
  .kb-pop__card{position:relative;width:100%;max-width:440px;background:#fff;color:var(--dark,#0D1114);
    border-radius:22px;padding:34px 30px 30px;box-shadow:0 30px 80px rgba(13,17,20,.35);
    transform:translateY(14px) scale(.98);opacity:0;transition:transform .28s cubic-bezier(.2,.8,.2,1),opacity .28s}
  .kb-pop.is-open .kb-pop__card{transform:none;opacity:1}
  .kb-pop__x{position:absolute;top:12px;right:14px;border:0;background:transparent;font-size:28px;line-height:1;
    color:#9aa0a6;cursor:pointer;padding:4px 8px;border-radius:8px}
  .kb-pop__x:hover{color:var(--dark,#0D1114);background:#f2f2ef}
  .kb-pop__kicker{font-family:'Anton',sans-serif;letter-spacing:.14em;text-transform:uppercase;font-size:12px;color:var(--purple,#ff6114);margin-bottom:8px}
  .kb-pop__title{font-family:'Anton',sans-serif;font-weight:400;line-height:1.02;font-size:30px;text-transform:uppercase;margin-bottom:10px}
  .kb-pop__sub{font-family:'Inter',sans-serif;font-size:15px;line-height:1.5;color:#4a4f54;margin-bottom:18px}
  .kb-pop__seg{display:flex;gap:8px;margin-bottom:16px}
  .kb-pop__seg-btn{flex:1;font-family:'Inter',sans-serif;font-weight:600;font-size:14px;padding:11px 8px;border-radius:12px;
    border:1.5px solid #e4e4df;background:#fafaf7;color:#4a4f54;cursor:pointer;transition:.15s}
  .kb-pop__seg-btn.is-on{border-color:var(--dark,#0D1114);background:var(--yellow,#FFE28C);color:var(--dark,#0D1114)}
  .kb-pop__label{display:block;font-family:'Inter',sans-serif;font-weight:600;font-size:13px;margin-bottom:6px}
  .kb-pop__input{width:100%;font-family:'Inter',sans-serif;font-size:15px;padding:13px 14px;border-radius:12px;
    border:1.5px solid #e4e4df;background:#fff;outline:none;transition:border-color .15s}
  .kb-pop__input:focus{border-color:var(--dark,#0D1114)}
  .kb-pop__hp{position:absolute;left:-9999px;width:1px;height:1px;opacity:0}
  .kb-pop__err{font-family:'Inter',sans-serif;color:#BA1A1A;font-size:13px;margin-top:8px}
  .kb-pop__submit{width:100%;margin-top:14px;font-family:'Inter',sans-serif;font-weight:700;font-size:15px;
    padding:14px;border-radius:12px;border:0;background:var(--dark,#0D1114);color:#fff;cursor:pointer;transition:.15s}
  .kb-pop__submit:hover{background:#000}
  .kb-pop__submit:disabled{opacity:.55;cursor:not-allowed}
  .kb-pop__or{display:flex;align-items:center;text-align:center;color:#9aa0a6;font-family:'Inter',sans-serif;font-size:12px;
    text-transform:uppercase;letter-spacing:.1em;margin:18px 0 14px}
  .kb-pop__or::before,.kb-pop__or::after{content:"";flex:1;height:1px;background:#e4e4df}
  .kb-pop__or span{padding:0 12px}
  .kb-pop__call{display:block;text-align:center;font-family:'Inter',sans-serif;font-weight:700;font-size:15px;
    padding:13px;border-radius:12px;border:1.5px solid var(--dark,#0D1114);color:var(--dark,#0D1114);text-decoration:none;transition:.15s}
  .kb-pop__call:hover{background:var(--dark,#0D1114);color:#fff}
  .kb-pop__call+.kb-pop__call{margin-top:10px}
  /* The web-app sign-up is the stronger action of the two, so it reads as filled. */
  .kb-pop__call--primary{background:var(--dark,#0D1114);color:#fff}
  .kb-pop__call--primary:hover{background:#1c2025}
  .kb-pop__done{text-align:center}
  .kb-pop__check{width:56px;height:56px;margin:0 auto 14px;border-radius:50%;background:var(--yellow,#FFE28C);
    display:flex;align-items:center;justify-content:center;font-size:28px;color:var(--dark,#0D1114)}
  @media(max-width:480px){.kb-pop__card{padding:30px 22px 24px}.kb-pop__title{font-size:26px}}
</style>

<div class="kb-pop" id="kbPop" role="dialog" aria-modal="true" aria-labelledby="kbPopTitle" aria-hidden="true">
  <div class="kb-pop__backdrop" data-kb-close></div>
  <div class="kb-pop__card">
    <button class="kb-pop__x" type="button" aria-label="Close" data-kb-close>&times;</button>

    <div class="kb-pop__body" id="kbPopForm">
      <p class="kb-pop__kicker">Kolabing</p>
      <h2 class="kb-pop__title" id="kbPopTitle">Get local collabs in your inbox</h2>
      <p class="kb-pop__sub">Join the list for communities &amp; businesses — early access, playbooks, and the best local partnerships near you.</p>

      <div class="kb-pop__seg" role="group" aria-label="I am a">
        <button type="button" class="kb-pop__seg-btn is-on" data-aud="community">A community</button>
        <button type="button" class="kb-pop__seg-btn" data-aud="business">A business</button>
      </div>

      <form id="kbPopFormEl" novalidate>
        <label class="kb-pop__label" for="kbPopEmail">Email</label>
        <input class="kb-pop__input" type="email" id="kbPopEmail" name="email" placeholder="you@example.com" autocomplete="email" required>
        <input type="text" name="website" tabindex="-1" autocomplete="off" class="kb-pop__hp" aria-hidden="true">
        <p class="kb-pop__err" id="kbPopErr" hidden></p>
        <button class="kb-pop__submit" type="submit" id="kbPopSubmit">Join the list</button>
      </form>

      <div class="kb-pop__or"><span>or</span></div>
      <a class="kb-pop__call kb-pop__call--primary" href="{{ $appRegister }}">Create your free account →</a>
      <a class="kb-pop__call kb-pop__bookcall" data-url-community="{{ config('kolabing.book_a_call_url_community') }}" data-url-business="{{ config('kolabing.book_a_call_url_business') }}" href="{{ config('kolabing.book_a_call_url_community') }}" target="_blank" rel="noopener">Book a call with us →</a>
    </div>

    <div class="kb-pop__body kb-pop__done" id="kbPopDone" hidden>
      <div class="kb-pop__check">✓</div>
      <h2 class="kb-pop__title">You're on the list</h2>
      <p class="kb-pop__sub">Thanks — we'll be in touch. Want to start now?</p>
      <a class="kb-pop__call kb-pop__call--primary" href="{{ $appRegister }}">Create your free account →</a>
      <a class="kb-pop__call kb-pop__bookcall" data-url-community="{{ config('kolabing.book_a_call_url_community') }}" data-url-business="{{ config('kolabing.book_a_call_url_business') }}" href="{{ config('kolabing.book_a_call_url_community') }}" target="_blank" rel="noopener">Book a call with us →</a>
    </div>
  </div>
</div>

<script>
(function(){
  var pop = document.getElementById('kbPop');
  if (!pop) return;
  var SEEN_KEY = 'kbPopSeen';
  var audience = 'community';
  var opened = false;

  function open(){
    if (opened) return;
    opened = true;
    try { sessionStorage.setItem(SEEN_KEY, '1'); } catch(e){}
    pop.classList.add('is-open');
    pop.setAttribute('aria-hidden', 'false');
    var email = document.getElementById('kbPopEmail');
    if (email) setTimeout(function(){ email.focus(); }, 300);
  }
  function close(){
    pop.classList.remove('is-open');
    pop.setAttribute('aria-hidden', 'true');
  }

  // Audience toggle
  // Point the "Book a call" CTAs at the discovery call that matches the segment.
  function syncBookCall(){
    pop.querySelectorAll('.kb-pop__bookcall').forEach(function(a){
      var url = a.getAttribute('data-url-' + audience);
      if (url) a.setAttribute('href', url);
    });
  }
  syncBookCall();

  pop.querySelectorAll('.kb-pop__seg-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      audience = btn.getAttribute('data-aud');
      pop.querySelectorAll('.kb-pop__seg-btn').forEach(function(b){ b.classList.remove('is-on'); });
      btn.classList.add('is-on');
      syncBookCall();
    });
  });

  // Close handlers
  pop.querySelectorAll('[data-kb-close]').forEach(function(el){
    el.addEventListener('click', close);
  });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && pop.classList.contains('is-open')) close();
  });

  // Submit
  var form = document.getElementById('kbPopFormEl');
  var submitBtn = document.getElementById('kbPopSubmit');
  var errEl = document.getElementById('kbPopErr');
  var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  form.addEventListener('submit', function(e){
    e.preventDefault();
    errEl.hidden = true;
    var email = document.getElementById('kbPopEmail').value.trim();
    var hp = form.querySelector('input[name="website"]').value;
    if (!email){ showErr('Please enter your email address.'); return; }
    submitBtn.disabled = true;
    submitBtn.textContent = 'Joining…';

    fetch('{{ route('newsletter.store') }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': token
      },
      body: JSON.stringify({ email: email, audience: audience, website: hp })
    }).then(function(res){
      if (res.ok){
        document.getElementById('kbPopForm').hidden = true;
        document.getElementById('kbPopDone').hidden = false;
        return;
      }
      return res.json().then(function(data){
        var msg = (data && data.errors && data.errors.email && data.errors.email[0])
          || (data && data.message) || 'Something went wrong. Please try again.';
        showErr(msg);
      }).catch(function(){ showErr('Something went wrong. Please try again.'); });
    }).catch(function(){
      showErr('Network error. Please try again.');
    }).finally(function(){
      submitBtn.disabled = false;
      submitBtn.textContent = 'Join the list';
    });
  });

  function showErr(msg){ errEl.textContent = msg; errEl.hidden = false; }

  // Triggers: once per session, whichever comes first —
  // exit-intent, 18s dwell, or 45% scroll depth.
  var already = false;
  try { already = sessionStorage.getItem(SEEN_KEY) === '1'; } catch(e){}
  if (!already){
    var timer = setTimeout(open, 18000);
    document.addEventListener('mouseout', function(e){
      if (e.clientY <= 0 && !opened){ clearTimeout(timer); open(); }
    });
    window.addEventListener('scroll', function onScroll(){
      var sc = (window.scrollY + window.innerHeight) / document.body.scrollHeight;
      if (sc >= 0.45 && !opened){ clearTimeout(timer); open(); window.removeEventListener('scroll', onScroll); }
    }, { passive: true });
  }
})();
</script>

</body></html>
