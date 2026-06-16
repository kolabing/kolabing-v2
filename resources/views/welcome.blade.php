<!DOCTYPE html><html lang="en" class="scroll-smooth"><head>


  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kolabing — Local Business &amp; Community Collaboration</title>
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
      height: 48px;
      width: auto;
      transform: rotate(-2deg);
      transform-origin: left center;
      margin: -8px 0;
    }
    .logo-mark--footer {
      transform: rotate(-2deg);
      filter: drop-shadow(0 6px 16px rgba(0,0,0,0.35));
    }
    nav { display: flex; align-items: center; gap: 32px; }
    nav a { text-decoration: none; color: var(--dark); font-size: 13px; font-weight: 600; opacity: 0.75; transition: opacity .2s, color .2s; }
    nav a:hover { opacity: 1; color: var(--dark); }
    .btn-nav {
      background: var(--dark); color: var(--yellow); padding: 11px 26px;
      border-radius: 999px; font-weight: 700; font-size: 13px; text-decoration: none; opacity: 1 !important;
      letter-spacing: 0.02em;
      transition: background .2s, transform .2s;
    }
    .btn-nav:hover { background: #1c2025; transform: translateY(-1px); }
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

    .download-btns { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; }
    .dl-btn {
      background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.13);
      color: #fff; display: flex; align-items: center;
      gap: 9px; padding: 9px 14px; border-radius: 11px; text-decoration: none;
      backdrop-filter: blur(10px); transition: background .2s; min-width: 130px;
    }
    .dl-btn:hover { background: rgba(255,255,255,0.15); }
    .dl-btn svg { width: 20px; height: 20px; flex-shrink: 0; }
    .dl-btn-label { text-align: left; }
    .dl-btn-label small { display: block; font-size: 9px; text-transform: uppercase; font-weight: 600; opacity: 0.5; line-height: 1; }
    .dl-btn-label span { display: block; font-size: 13px; font-weight: 600; line-height: 1.2; }
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

    /* RIGHT column — phone mockup */
    .manifesto-phone {
      position: relative;
      display: flex; align-items: center; justify-content: center;
      min-height: 520px;
    }
    .manifesto-phone .phone-wrap { transform: rotate(-4deg); }
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
    @media (max-width: 900px) {
      header { padding: 12px 20px; }
      nav { display: none; }
      .menu-icon { display: block; }
      .logo-mark { height: 40px; margin: -6px 0; }

      .hero { min-height: 100svh; padding: 96px 8px 40px; overflow: hidden; }
      .hero-inner { grid-template-columns: 1fr; gap: 32px; }
      .hero-left { padding: 0 16px; max-width: none; }
      .hero-right { justify-content: center; padding: 0 16px 24px; height: auto; min-height: 440px; }
      .hero h1 { font-size: clamp(2.2rem, 10vw, 56px); width: auto; max-width: 100%; }
      .phone-wrap { transform: rotate(2deg) translateY(0); }
      .chip-runners { left: 16px; bottom: 20px; }
      .chip-notification { right: 16px; top: 20px; }

      .manifesto-track { padding: 0 24px; grid-template-columns: 1fr; gap: 40px; }
      .manifesto-headline { font-size: clamp(2.4rem, 10vw, 56px); }
      .manifesto-action { font-size: clamp(2rem, 9vw, 48px); }
      .manifesto-footnote { margin-top: 0; }
      .manifesto-bubble { font-size: 13px; }

      .reveal-inner { padding: 96px 24px 96px; gap: 28px; }
      .reveal-kicker::before, .reveal-kicker::after { width: 24px; }
      .reveal-kicker { letter-spacing: 0.24em; gap: 12px; }

      .how-header { grid-template-columns: 1fr; padding: 0 24px; gap: 12px; }
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

      footer { padding: 48px 24px 36px; }
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
    <img class="logo-mark" src="assets/kolabing-logo.png" alt="kolabing"/>
  </div>
  <nav>
    <a href="#how-it-works">how it works</a>
    <a href="#faq">questions</a>
    <a class="btn-nav" href="#cta">download</a>
  </nav>
  <button class="menu-icon" aria-label="Menu">
    <svg viewBox="0 0 28 22" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round">
      <path d="M3 5 L 26 4"/>
      <path d="M4 11 L 24 11"/>
      <path d="M3 17 L 23 18"/>
    </svg>
  </button>
</header>

<!-- HERO -->
<section class="hero">
  <div class="hero-inner">

  <!-- Left: text panel -->
  <div class="hero-left">
    <div class="hero-badge">iOS + Android</div>
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
    <div class="download-btns" style="gap: 0px">
      <a href="#" class="dl-btn">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.1 2.48-1.34.03-1.77-.79-3.31-.79-1.54 0-2.02.77-3.31.82-1.34.05-2.33-1.32-3.17-2.54-1.72-2.5-3.04-7.07-1.27-10.13 1.13-1.95 3.12-2.73 4.61-2.73 1.3 0 2.21.72 2.91.72.69 0 1.83-.87 3.37-.87 1.26 0 2.39.54 3.13 1.48-1.07.65-1.58 1.94-1.58 3.39 0 1.82 1.48 3.15 2.92 3.15.11 0 .22 0 .33-.01-.2 1.63-.82 3.03-1.73 4.41M15.97 3.38c.63-.77 1.05-1.83.94-2.88-.91.04-2 .61-2.65 1.37-.58.67-1.09 1.76-.95 2.79.99.08 2.03-.51 2.66-1.28z"></path></svg>
        <div class="dl-btn-label"><small>Download on the</small><span>App Store</span></div>
      </a>
      <a href="#" class="dl-btn">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 20.5v-17c0-.83.94-1.3 1.6-.8l14 8.5c.6.36.6 1.24 0 1.6l-14 8.5c-.66.5-1.6.03-1.6-.8z"></path></svg>
        <div class="dl-btn-label"><small>Get it on</small><span>Google Play</span></div>
      </a>
    </div>
    <p class="hero-fine">free to download · cancel anytime</p>
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

    <!-- RIGHT: phone mockup -->
    <div class="manifesto-phone fade in" style="transition-delay:.15s">
      <div class="phone-wrap" id="phoneWrap">

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

        <!-- Phone frame -->
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
        <div class="polaroid-img"><img src="uploads/Screenshot 2026-05-16 at 22.47.19.png" alt="Run club cheers-ing coffee cups after a morning run" loading="lazy"/></div>
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
        <div class="polaroid-img"><img src="uploads/Gemini_Generated_Image_j3ohygj3ohygj3oh.png" alt="Cycling crew on the road testing gear" loading="lazy"/></div>
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
        <div class="polaroid-img"><img src="uploads/Gemini_Generated_Image_rfno1grfno1grfno.png" alt="Yoga class at sunset on a rooftop" loading="lazy"/></div>
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
        <div class="polaroid-img"><img src="uploads/feedback-skincare.png" alt="Women's group testing skincare products together" loading="lazy"/></div>
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
        <div class="polaroid-img"><img src="uploads/content-dog-walk.png" alt="Dog community on a city photo walk with their dogs" loading="lazy"/></div>
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
        <div class="polaroid-img"><img src="uploads/loyalty-wine-book.png" alt="Book club gathered around a table reading with wine in a cellar" loading="lazy"/></div>
        <figcaption><strong>Book club + wine bar</strong><span>Monthly tasting night</span></figcaption>
      </figure>
    </div>
  </div>

  <p class="ideas-foot fade"><b>And more:</b> discounts &middot; community perks &middot; reviews &middot; co-created merch &middot; challenges</p>
</section>

<!-- FAQ -->
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
        <a href="#" class="cta-btn dark">join as a business</a>
        <a href="#" class="cta-btn white">join as a community</a>
      </div>
      <p class="cta-fine">free to download · cancel anytime</p>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="footer-inner">
    <div class="logo">
      <img class="logo-mark logo-mark--footer" src="assets/kolabing-logo.png" alt="kolabing"/>
    </div>
    <div class="footer-links">
      <a href="#">terms</a>
      <a href="#">privacy</a>
      <a href="#">support</a>
      <a href="#">careers</a>
    </div>
  </div>
  <p class="footer-copy">
    free for communities · paid plans for businesses · cancel anytime<br>
    © <span id="yr">2026</span> kolabing. built for real people, in real places.
  </p>
</footer>

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



</body></html>
