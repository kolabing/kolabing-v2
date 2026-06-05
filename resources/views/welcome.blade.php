<!DOCTYPE html><html lang="en" class="scroll-smooth"><head>


  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kolabing — Local Business &amp; Community Collaboration</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
  <link href="https://fonts.googleapis.com/css2?family=Anton&amp;family=Caveat:wght@500;600&amp;family=Inter:wght@400;500;600;700&amp;family=Playfair+Display:ital@1&amp;display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --yellow: #FFE28C;
      --purple: #8e50f1;
      --dark: #0D1114;
      --light: #F7F8FA;
      --mid: #E0E0E0;
      --header-height: 77px;
    }

    html { font-family: 'Inter', sans-serif; }
    body { background: #fff; color: var(--dark); overflow-x: hidden; }

    /* ── NAV ── */
    header {
      position: fixed; top: 0; width: 100%; z-index: 100;
      background: var(--yellow);
      border-bottom: 1px solid rgba(13,17,20,0.12);
      min-height: var(--header-height);
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
      position: relative; width: 100%; height: 100svh; min-height: 640px;
      background: var(--dark);
      display: grid;
      grid-template-columns: 1fr 1fr;
      align-items: center;
      padding-top: var(--header-height);
      overflow: hidden; /* contain phone bleed */
    }

    /* ── Left text panel ── */
    .hero-left {
      position: relative; z-index: 4;
      display: flex; flex-direction: column; justify-content: center;
      padding: 80px 0 60px 72px;
      /* push the column slightly into the phone area for overlap */
      margin-right: -120px;
      pointer-events: none; /* let the phone underneath stay clickable in overlap zone */
    }
    .hero-left > * { pointer-events: auto; }

    .hero-badge {
      display: inline-flex; align-items: center;
      background: rgba(142,80,241,0.12);
      border: 1px solid rgba(142,80,241,0.4); color: rgba(255,255,255,0.75);
      font-size: 10px; font-weight: 600; letter-spacing: 0.16em; text-transform: uppercase;
      padding: 5px 14px; border-radius: 999px; margin-bottom: 28px; width: fit-content;
    }

    .hero h1 {
      font-family: 'Anton', sans-serif; text-transform: uppercase;
      color: #fff;
      font-size: clamp(3.2rem, 7.4vw, 108px);
      line-height: 0.92;
      letter-spacing: -0.01em;
      margin-bottom: 0;
      position: relative;
      /* let long lines extend rightward into the phone area */
      width: max-content;
      max-width: 145%;
      text-shadow: 0 2px 28px rgba(0,0,0,0.55), 0 1px 4px rgba(0,0,0,0.4);
    }
    .hero h1 .line { display: block; }
    .hero h1 .line-indent { padding-left: 0.22em; }
    .hero h1 .accent { color: var(--yellow); }

    .hero-rule {
      width: 40px; height: 2px; background: var(--yellow);
      opacity: 0.6; margin: 26px 0 20px;
    }

    .hero-sub {
      font-size: 13px; color: rgba(255,255,255,0.48);
      font-weight: 400; margin-bottom: 4px;
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

    /* ── Right: phone mockup area ── */
    .hero-right {
      position: relative;
      display: flex; align-items: center; justify-content: center;
      height: 100%;
      overflow: visible;
      perspective: 1000px;
      z-index: 1;
      /* shift phone left, into the text area */
      margin-left: -220px;
    }

    /* Soft glow behind the phone */
    .hero-right::before {
      content: '';
      position: absolute;
      width: 320px; height: 480px;
      border-radius: 50%;
      background: radial-gradient(ellipse, rgba(142,80,241,0.26) 0%, transparent 70%);
      top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      pointer-events: none;
      z-index: 0;
      filter: blur(32px);
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

    /* LEFT column */
    .manifesto-left {}

    .manifesto-headline {
      font-family: 'Anton', sans-serif; text-transform: uppercase;
      font-size: clamp(2.6rem, 5vw, 68px); line-height: 0.92;
      color: var(--dark); letter-spacing: -0.01em;
      margin-bottom: 24px;
    }
    .manifesto-headline .muted {
      color: rgba(13,17,20,0.18);
    }

    .manifesto-support p {
      font-size: 13px; color: rgba(13,17,20,0.45);
      font-weight: 400; line-height: 1.7;
      max-width: 380px;
      border-left: 2px solid var(--yellow); padding-left: 16px;
    }

    /* RIGHT column */
    .manifesto-right {
      display: flex; flex-direction: column; gap: 0;
    }

    .manifesto-friend-label {
      font-size: 10px; font-weight: 600; text-transform: uppercase;
      letter-spacing: 0.18em; color: rgba(13,17,20,0.3);
      margin-bottom: 10px;
      line-height: 1;
    }

    .manifesto-bubble {
      display: inline-block; background: var(--yellow); color: var(--dark);
      font-weight: 500; font-size: 14px;
      padding: 10px 18px; border-radius: 18px 18px 18px 4px;
      margin-bottom: 32px;
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
      font-size: clamp(2rem, 4vw, 52px); color: var(--dark); line-height: 0.92;
      letter-spacing: -0.01em;
    }
    .manifesto-action-rule {
      width: 24px; height: 1.5px; background: rgba(13,17,20,0.12);
      margin: 14px 0;
    }

    /* Footnote */
    .manifesto-footnote {
      margin-top: 48px;
      font-size: 10px; color: rgba(13,17,20,0.22); font-weight: 500;
      letter-spacing: 0.12em; text-transform: uppercase;
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
        radial-gradient(80% 80% at 50% 100%, rgba(142,80,241,0.05), rgba(0,0,0,0) 70%);
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
      color: var(--yellow);
      line-height: 0.9;
      letter-spacing: -0.012em;
      margin-top: 0.08em;
    }

    /* ── HOW IT WORKS — warm parchment, playful editorial ── */
    .section-how {
      background: #F5F0E8; padding: 144px 0 128px; overflow: hidden;
      border-top: 3px solid var(--yellow);
      position: relative;
    }
    /* Section-level decorative graphics */
    .how-deco {
      position: absolute;
      pointer-events: none;
      z-index: 1;
    }
    .how-deco-arrow {
      left: 4%; right: 4%;
      bottom: 92px;
      height: 110px;
      opacity: 0.45;
    }
    .how-deco-arrow svg { width: 100%; height: 100%; display: block; }
    .how-deco-sparkle-1 { top: 96px; right: 7%;  width: 38px; transform: rotate(12deg);  opacity: 0.7; }
    .how-deco-sparkle-2 { top: 240px; left: 6%;  width: 26px; transform: rotate(-18deg); opacity: 0.55; }
    .how-deco-sparkle-3 { bottom: 56px; right: 12%; width: 30px; transform: rotate(8deg); opacity: 0.6; }
    /* Purple marker circle now wraps the word HOW — see .how-word-circle below */
    .how-deco-squiggle {
      bottom: 200px; left: 14%; width: 60px;
      transform: rotate(-8deg); opacity: 0.5;
    }
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
      position: absolute;
      left: -10%; top: -12%;
      width: 120%; height: 130%;
      pointer-events: none;
      z-index: -1;
      overflow: visible;
    }
    .how-word-circle path {
      fill: none;
      stroke: #8e50f1;
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
    .how-step:hover .how-num { color: rgba(142,80,241,0.18); }
    .how-step:hover .how-step-illo { transform: rotate(0deg) translateY(-3px); }
    .how-step:not(:first-child) { padding-left: 32px; padding-right: 24px; }

    /* Per-step illustration — pinned-sticker feel */
    .how-step-illo {
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

    /* ── EXAMPLES — editorial wall, asymmetric, hand-pinned ── */
    .section-examples { background: #111317; padding: 120px 0 180px; overflow: hidden; position: relative; }
    .section-examples .section-label { color: var(--yellow); opacity: 0.7; }
    .section-examples .section-title { color: #fff; }
    .section-examples .examples-header-right p { color: rgba(255,255,255,0.35); }
    .examples-header {
      max-width: 1280px; margin: 0 auto; padding: 0 48px;
      display: grid; grid-template-columns: 2fr 1fr; align-items: end;
      margin-bottom: 80px;
      position: relative;
    }
    .examples-header-left {}
    .examples-header-right { text-align: right; }
    .examples-header-right p {
      font-size: 13px; color: rgba(255,255,255,0.35); line-height: 1.65; max-width: 240px; margin-left: auto;
    }

    /* Tiny handwritten "wall" annotation — sits cleanly inside the header column */
    .examples-scribble {
      margin-top: 18px;
      font-family: 'Caveat', 'Comic Sans MS', cursive;
      font-size: 17px;
      color: rgba(255,255,255,0.42);
      transform: rotate(-3deg);
      transform-origin: right center;
      white-space: nowrap;
      pointer-events: none;
      display: inline-flex; align-items: center; gap: 6px;
    }
    .examples-scribble svg {
      display: inline-block;
      vertical-align: middle;
      width: 44px; height: 16px;
      opacity: 0.6;
    }

    /* Wall of cards — 12-col grid, uniform sizing, rotation + drift carry the rhythm */
    .examples-rack {
      max-width: 1240px; margin: 0 auto; padding: 0 48px;
      display: grid; grid-template-columns: repeat(12, 1fr); gap: 0 28px;
      align-items: start;
      position: relative;
    }

    /* Editorial card — all cards identical size */
    .example-block {
      position: relative;
      display: flex; flex-direction: column;
      padding: 18px 18px 22px;
      border-radius: 14px;
      box-shadow:
        0 1px 0 rgba(255,255,255,0.04) inset,
        0 18px 40px -10px rgba(0,0,0,0.45),
        0 6px 14px -6px rgba(0,0,0,0.25);
      transition: transform 0.45s cubic-bezier(.2,.7,.2,1), box-shadow 0.3s ease;
    }
    /* Alternating warm tones */
    .example-block:nth-child(odd)  { background: #F5F0E6; }   /* soft off-white */
    .example-block:nth-child(even) { background: #FBEFC8; }   /* pale warm yellow */

    /* Uniform column span, varied rotation + vertical drift only */
    .example-block:nth-child(1) {
      grid-column: 1 / span 4;
      transform: rotate(-1.4deg) translateY(36px);
      z-index: 2;
    }
    .example-block:nth-child(2) {
      grid-column: 5 / span 4;
      transform: rotate(0.8deg) translateY(-8px);
      z-index: 3;
    }
    .example-block:nth-child(3) {
      grid-column: 9 / span 4;
      transform: rotate(-0.7deg) translateY(56px);
      z-index: 2;
    }

    .example-block:hover {
      transform: rotate(0deg) translateY(-4px);
      box-shadow:
        0 1px 0 rgba(255,255,255,0.05) inset,
        0 32px 72px -10px rgba(0,0,0,0.55),
        0 12px 22px -8px rgba(0,0,0,0.28);
      z-index: 5;
    }

    /* Chyron: RUN CLUB × CAFÉ — sits above the image strip on each card */
    .example-credit {
      display: grid;
      grid-template-columns: 1fr auto 1fr;
      align-items: center;
      gap: 14px;
      margin-bottom: 12px;
      padding: 0 2px;
      font-family: 'Anton', sans-serif;
      text-transform: uppercase;
      letter-spacing: 0.16em;
      font-size: 12px;
      color: rgba(13,17,20,0.78);
      line-height: 1;
    }
    .example-credit .credit-a { text-align: left; }
    .example-credit .credit-b { text-align: right; }
    .example-credit .credit-x {
      font-family: 'Anton', sans-serif;
      color: var(--purple);
      font-size: 28px;
      letter-spacing: 0;
      line-height: 0.8;
      transform: translateY(-1px);
      opacity: 0.9;
    }

    /* Image composition — same aspect for every card now */
    .example-collab {
      position: relative;
      width: 100%;
      aspect-ratio: 16 / 11;
      border-radius: 8px;
      overflow: hidden;
      margin-bottom: 18px;
      box-shadow: 0 6px 18px rgba(0,0,0,0.18), inset 0 0 0 1px rgba(0,0,0,0.04);
      display: grid;
      grid-template-columns: 1fr 1fr;
      isolation: isolate;
    }

    /* Each card has its own image-frame personality (offsets only — size matched) */
    .example-block:nth-child(1) .example-collab {
      margin-left: -2px;
      margin-right: 6px;
    }
    .example-block:nth-child(2) .example-collab {
      margin-left: 0;
      margin-right: 0;
    }
    .example-block:nth-child(3) .example-collab {
      margin-left: 6px;
      margin-right: -2px;
    }

    .example-img-a, .example-img-b {
      width: 100%; height: 100%;
      object-fit: cover;
      object-position: center;
      display: block;
    }

    /* Tighter crops */
    .example-block:nth-child(1) .example-img-a { object-position: 55% 50%; transform: scale(1.04); }
    .example-block:nth-child(1) .example-img-b { object-position: 50% 50%; transform: scale(1.02); }
    .example-block:nth-child(2) .example-img-a { object-position: 50% 40%; }
    .example-block:nth-child(3) .example-img-b {
      transform: scale(1.18);
      transform-origin: center;
      object-position: 50% 45%;
    }

    /* Soft seam */
    .example-seam {
      position: absolute;
      top: 0; bottom: 0;
      left: 50%;
      width: 14%;
      transform: translateX(-50%);
      background: linear-gradient(
        to right,
        rgba(0,0,0,0) 0%,
        rgba(0,0,0,0.18) 50%,
        rgba(0,0,0,0) 100%
      );
      pointer-events: none;
      z-index: 2;
      mix-blend-mode: multiply;
    }

    /* Old per-image labels removed — collaboration now reads as a chyron above */

    /* Text — clear, two-tier hierarchy */
    .example-meta { padding: 0 2px; position: relative; }
    .example-tag {
      display: inline-block;
      background: rgba(13,17,20,0.06);
      color: rgba(13,17,20,0.55);
      font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.16em;
      padding: 4px 9px; border-radius: 999px; margin-bottom: 14px;
    }
    /* The prominent card no longer larger — unified size; remove special headline rule */
    .example-block h3 {
      font-family: 'Anton', sans-serif; text-transform: uppercase;
      font-size: clamp(1.35rem, 1.85vw, 26px); line-height: 1.0;
      color: var(--dark); letter-spacing: 0.005em; margin-bottom: 10px;
    }
    .example-block p {
      font-size: 12px;
      color: rgba(13,17,20,0.42);
      line-height: 1.55;
      font-weight: 500;
      letter-spacing: 0.01em;
      min-height: calc(2 * 1.55em);
    }

    /* ── Editorial doodles / stickers ── */

    /* Sparkle on card 1 — sits just outside the top-right corner */
    .ex-sparkle {
      position: absolute;
      top: -16px; right: -14px;
      width: 36px; height: 36px;
      color: var(--yellow);
      pointer-events: none;
      z-index: 4;
      filter: drop-shadow(0 2px 6px rgba(0,0,0,0.35));
    }

    /* Sticker-style "★ ★ ★ ★ ★" badge on the prominent card */
    .ex-sticker {
      position: absolute;
      top: -18px; left: 18px;
      background: var(--purple);
      color: #fff;
      font-family: 'Anton', sans-serif;
      font-size: 11px;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      padding: 6px 12px 5px;
      border-radius: 4px;
      transform: rotate(-6deg);
      box-shadow: 0 4px 10px rgba(0,0,0,0.3);
      z-index: 4;
      white-space: nowrap;
    }
    .ex-sticker::before {
      content: ""; position: absolute;
      left: 50%; top: 100%;
      width: 6px; height: 6px; border-radius: 50%;
      background: var(--purple);
      transform: translate(-50%, -2px);
      opacity: 0.55;
    }

    /* Handwritten annotation on card 3 — floats outside the card, soft yellow */
    .ex-annotation {
      position: absolute;
      top: 100%;
      right: 4px;
      margin-top: 14px;
      display: inline-flex; align-items: center; gap: 6px;
      font-family: 'Caveat', 'Comic Sans MS', cursive;
      font-size: 19px;
      color: rgba(255,226,140,0.72);
      transform: rotate(-3deg);
      transform-origin: right top;
      white-space: nowrap;
      pointer-events: none;
    }
    .ex-annotation svg {
      width: 38px; height: 18px;
      opacity: 0.6;
    }

    /* Tiny "01 / 02 / 03" index numerals — set in serif so they read editorial */
    .ex-index {
      position: absolute;
      bottom: 12px; right: 16px;
      font-family: 'Playfair Display', Georgia, serif;
      font-style: italic;
      font-size: 13px;
      color: rgba(13,17,20,0.32);
      letter-spacing: 0.04em;
      z-index: 3;
    }

    /* Remove old stagger */
    .examples-rack .example-block:nth-child(2) { margin-top: 0; }

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
    .footer-links a:hover { color: #a98bff; }
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
      :root { --header-height: 64px; }
      header { padding: 12px 20px; }
      nav { display: none; }
      .menu-icon { display: block; }
      .logo-mark { height: 40px; margin: -6px 0; }

      .hero { grid-template-columns: 1fr; grid-template-rows: auto auto; height: auto; min-height: 100svh; padding-top: 0; overflow: hidden; }
      .hero-left { padding: calc(var(--header-height) + 36px) 24px 40px 32px; margin-right: 0; }
      .hero h1 { font-size: clamp(2.2rem, 10vw, 56px); width: auto; max-width: 100%; }
      .hero-right { height: 480px; justify-content: center; margin-left: 0; }
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

      .examples-header { grid-template-columns: 1fr; padding: 0 24px; }
      .examples-header-right { display: none; }
      .examples-rack { grid-template-columns: 1fr; padding: 0 24px; gap: 56px 0; }
      .examples-rack .example-block:nth-child(1),
      .examples-rack .example-block:nth-child(2),
      .examples-rack .example-block:nth-child(3) { grid-column: 1 / -1; transform: none; }
      .examples-rack .example-block:nth-child(1) .example-collab,
      .examples-rack .example-block:nth-child(2) .example-collab,
      .examples-rack .example-block:nth-child(3) .example-collab {
        margin: 0 0 18px;
      }
      .examples-scribble { display: none; }

      .faq-track { grid-template-columns: 1fr; padding: 0 24px; gap: 32px; }
      .faq-heading-col { position: static; }

      .cta-track { grid-template-columns: 1fr; padding: 0 24px; gap: 40px; }
      .cta-btns { flex-direction: row; flex-wrap: wrap; }
      .cta-polaroid { display: none; }

      footer { padding: 48px 24px 36px; }
    }
    @media (max-width: 540px) {
      .how-steps { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body style="font-size: 12px;">

<!-- NAV -->
<header>
  <div class="logo">
    <img class="logo-mark" src="/brand/kolabing-wordmark-dark.png" alt="kolabing"/>
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

  <!-- Left: text panel -->
  <div class="hero-left">
    <div class="hero-badge">iOS + Android</div>
    <h1>
      <span class="line">
</span>
      <span class="line line-indent">REAL PEOPLE</span>
      <span class="line">  REAL EXPERIENCES</span>
      <span class="line accent">REAL GROWTH</span>
    </h1>
    <div class="hero-rule" aria-hidden="true"></div>
    <p class="hero-sub">not ads. not influencers. <span class="accent">people.</span></p>
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

  <!-- Right: phone mockup -->
  <div class="hero-right">

    <!-- Purple accent line -->
    <div class="phone-accent-line" aria-hidden="true"></div>

    <!-- Phone wrapper (tilted, parallax on hover) -->
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
        <!-- Screen -->
        <div class="phone-screen">
          <video autoplay muted loop playsinline onerror="this.parentElement.style.background='#1a1a2e'">
            <source src="assets/hero2.mp4" type="video/mp4"/>
            <source src="assets/hero.mp4" type="video/mp4"/>
          </video>
        </div>
        <!-- Dynamic island -->
        <div class="phone-notch" aria-hidden="true"></div>
        <!-- Buttons -->
        <div class="phone-btn-right" aria-hidden="true"></div>
        <div class="phone-btn-left-1" aria-hidden="true"></div>
        <div class="phone-btn-left-2" aria-hidden="true"></div>
      </div>

      <!-- Yellow glow beneath phone -->
      <div class="phone-glow" aria-hidden="true"></div>

    </div><!-- /phone-wrap -->
  </div><!-- /hero-right -->
</section>

<!-- MANIFESTO -->
<section class="section-manifesto">
  <div class="manifesto-track">

    <!-- LEFT: main statement + supporting line -->
    <div class="manifesto-left fade in">
      <div class="manifesto-headline">
        ADS ARE<br>IGNORED.<br><span class="muted">PEOPLE TRUST<br>PEOPLE.</span>
      </div>
      <div class="manifesto-support">
        <p>Every algorithm tries to reach your customer. Kolabing connects you to communities that already trust each other.</p>
      </div>
    </div>

    <!-- RIGHT: story flow -->
    <div class="manifesto-right fade in" style="transition-delay:.15s">
      <div class="manifesto-friend-label">A FRIEND POSTS</div>
      <div class="manifesto-bubble">&ldquo;run + coffee tomorrow ☀️&rdquo;</div>
      <div class="manifesto-actions">
        <div class="manifesto-action fade in" style="transition-delay:.2s">YOU GO.</div>
        <div class="manifesto-action-rule" aria-hidden="true"></div>
        <div class="manifesto-action fade in" style="transition-delay:.3s">YOU STAY.</div>
        <div class="manifesto-action-rule" aria-hidden="true"></div>
        <div class="manifesto-action fade in" style="transition-delay:.4s">YOU SHARE IT.</div>
      </div>
    </div>

  </div>
</section>

<!-- REVEAL -->
<section class="section-reveal">
  <div class="reveal-inner">
    <div class="reveal-kicker fade">The idea in one sentence</div>
    <div class="reveal-text-block fade" style="transition-delay:.08s">
      <div class="reveal-line1">THAT'S NOT AN AD.</div>
      <div class="reveal-line2">THAT'S KOLABING.</div>
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
    <svg viewBox="0 0 40 40" fill="none" stroke="#8e50f1" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
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
      Post the opportunity. Match the right partner. Make it happen.<br>
      No agency. No media budget. Just the right people in the same room.
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
          <circle cx="32" cy="14" r="4" fill="#8e50f1"/>
          <line x1="32" y1="18" x2="32" y2="22"/>
        </svg>
      </div>
      <span class="how-num">01</span>
      <h4>Post a Kolab</h4>
      <p>Share what you're looking for — event, venue, product, audience.</p>
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
      <h4>Get Matches</h4>
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
      <h4>Connect</h4>
      <p>Chat in-app. Agree on the details.</p>
    </div>

    <!-- 04: MAKE IT HAPPEN — sparkle burst -->
    <div class="how-step fade" style="transition-delay:.24s">
      <div class="how-step-illo" aria-hidden="true">
        <svg viewBox="0 0 64 64" fill="none" stroke="#0D1114" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
          <!-- big sparkle -->
          <path d="M32 8 L 36 28 L 56 32 L 36 36 L 32 56 L 28 36 L 8 32 L 28 28 Z" fill="#FFE28C"/>
          <!-- small sparkle top right -->
          <path d="M52 12 L 53 16 L 57 17 L 53 18 L 52 22 L 51 18 L 47 17 L 51 16 Z" fill="#8e50f1" stroke="none"/>
          <!-- small dot bottom left -->
          <circle cx="12" cy="52" r="2" fill="#0D1114" stroke="none"/>
          <!-- movement lines -->
          <line x1="6" y1="14" x2="11" y2="12"/>
          <line x1="58" y1="52" x2="53" y2="50"/>
        </svg>
      </div>
      <span class="how-num">04</span>
      <h4>Make it happen</h4>
      <p>Host the Kolab. Real people, real results.</p>
    </div>
  </div>
</section>

<!-- EXAMPLES -->
<section class="section-examples">
  <div class="examples-header">
    <div class="examples-header-left">
      <div class="section-label fade">real examples</div>
      <div class="section-title fade">PROOF<br>IT WORKS</div>
    </div>
    <div class="examples-header-right fade">
      <p>Three collaborations. Three communities. Zero paid ads. All in Barcelona.</p>
      <div class="examples-scribble" aria-hidden="true">
        actual stuff that happened
        <svg viewBox="0 0 48 18" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round">
          <path d="M2 9 C 14 2, 30 16, 44 8" />
          <path d="M40 4 L 44 8 L 39 11" />
        </svg>
      </div>
    </div>
  </div>
  <div class="examples-rack">

    <!-- 01: Café + Running Club -->
    <div class="example-block fade">
      <span class="ex-index" aria-hidden="true">nº 01</span>
      <svg class="ex-sparkle" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true">
        <path d="M16 0 L18.4 13.6 L32 16 L18.4 18.4 L16 32 L13.6 18.4 L0 16 L13.6 13.6 Z"/>
      </svg>
      <div class="example-credit" aria-hidden="true">
        <span class="credit-a">run club</span>
        <span class="credit-x">×</span>
        <span class="credit-b">café</span>
      </div>
      <div class="example-collab">
        <img class="example-img-a" src="uploads/Gemini_Generated_Image_c3rghhc3rghhc3rg.png" alt="Running club on Barcelona waterfront" loading="lazy"/>
        <img class="example-img-b" src="uploads/Screenshot 2026-05-16 at 22.47.19.png" alt="Runners cheers-ing takeaway coffee cups in a circle on cobblestones" loading="lazy"/>
        <div class="example-seam" aria-hidden="true"></div>
      </div>
      <div class="example-meta">
        <h3>30 runners filled a café on a tuesday</h3>
        <p>now they come every week</p>
      </div>
    </div>

    <!-- 02: Yoga + Rooftop -->
    <div class="example-block fade" style="transition-delay:.1s">
      <span class="ex-sticker" aria-hidden="true">★ community fav</span>
      <span class="ex-index" aria-hidden="true">nº 02</span>
      <div class="example-credit" aria-hidden="true">
        <span class="credit-a">yoga club</span>
        <span class="credit-x">×</span>
        <span class="credit-b">rooftop</span>
      </div>
      <div class="example-collab">
        <img class="example-img-a" src="uploads/Gemini_Generated_Image_rfno1grfno1grfno.png" alt="Yoga silhouettes at sunset on a Barcelona rooftop" loading="lazy"/>
        <img class="example-img-b" src="uploads/Gemini_Generated_Image_xeqjidxeqjidxeqj.png" alt="Rooftop drinks with the Sagrada Família at sunset" loading="lazy"/>
        <div class="example-seam" aria-hidden="true"></div>
      </div>
      <div class="example-meta">
        <h3>a yoga club hosted a rooftop session</h3>
        <p>40 new customers in one night</p>
      </div>
    </div>

    <!-- 03: Brewery + Cycling -->
    <div class="example-block fade" style="transition-delay:.2s">
      <span class="ex-index" aria-hidden="true">nº 03</span>
      <div class="example-credit" aria-hidden="true">
        <span class="credit-a">cycling</span>
        <span class="credit-x">×</span>
        <span class="credit-b">brewery</span>
      </div>
      <div class="example-collab">
        <img class="example-img-a" src="uploads/Gemini_Generated_Image_j3ohygj3ohygj3oh.png" alt="Cycling crew on mountain roads outside Barcelona" loading="lazy"/>
        <img class="example-img-b" src="uploads/Gemini_Generated_Image_85ct4x85ct4x85ct.png" alt="Post-ride beers at Born Brewery" loading="lazy"/>
        <div class="example-seam" aria-hidden="true"></div>
      </div>
      <div class="example-meta">
        <h3>a local brewery co-hosted a weekend ride</h3>
        <p>ugc from 60 riders, zero paid content</p>
      </div>
      <div class="ex-annotation" aria-hidden="true">
        <svg viewBox="0 0 40 18" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round">
          <path d="M2 6 C 12 2, 22 14, 36 10" />
          <path d="M32 6 L 36 10 L 31 12" />
        </svg>
        zero ad spend, btw
      </div>
    </div>

  </div>
</section>

<!-- FAQ -->
<section class="section-faq" id="faq">
  <div class="faq-track">
    <div class="faq-heading-col">
      <div class="section-label fade">support</div>
      <div class="section-title fade" style="margin-bottom:6px;">QUESTIONS</div>
      <span class="faq-underline fade" aria-hidden="true" style="transition-delay:.05s">
        <svg viewBox="0 0 180 14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <path d="M4 9 C 30 3, 60 12, 92 6 C 124 1, 150 11, 176 5" />
        </svg>
      </span>
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
      <div class="cta-title">BE PART OF HOW PEOPLE DISCOVER THINGS NOW</div>
    </div>
    <div class="cta-right fade" style="transition-delay:.12s">
      <p class="cta-sub">Through people, not interruption. Join the communities and businesses already making it happen in Barcelona.</p>
      <div class="cta-btns">
        <div class="cta-handnote" aria-hidden="true">
          start here ✨
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
      <img class="logo-mark logo-mark--footer" src="/brand/kolabing-wordmark-light.png" alt="kolabing"/>
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
      phoneWrap.style.transform = `rotate(4deg) translate(-40px, -12px) rotateX(${rotX}deg) rotateY(${rotY}deg)`;
    });
    document.addEventListener('mouseleave', () => {
      phoneWrap.style.transform = 'rotate(4deg) translate(-40px, -12px)';
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
