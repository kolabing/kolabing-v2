<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#111317">
    <title>Kolabing - Local Business & Community Collaboration</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Caveat:wght@500;600&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #111317;
            --bg-soft: #15181d;
            --text: #f4f2ee;
            --muted: rgba(244, 242, 238, 0.6);
            --muted-strong: rgba(244, 242, 238, 0.82);
            --yellow: #f7df8f;
            --yellow-deep: #e5c972;
            --card-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
        }

        * {
            box-sizing: border-box;
        }

        html {
            background: var(--bg);
            color: var(--text);
            font-family: "Inter", sans-serif;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(1200px 500px at 50% 0%, rgba(255, 255, 255, 0.03), transparent 62%),
                var(--bg);
            color: var(--text);
            overflow-x: hidden;
        }

        img {
            display: block;
            max-width: 100%;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .page {
            width: 100%;
        }

        .hero {
            position: relative;
            padding: 88px 24px 40px;
        }

        .hero-inner,
        .proof-inner,
        .footer-inner {
            width: min(100%, 1600px);
            margin: 0 auto;
        }

        .hero-inner {
            max-width: 1460px;
            text-align: center;
        }

        .eyebrow {
            margin: 0 0 18px;
            color: var(--yellow);
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 0.34em;
            text-transform: uppercase;
        }

        .hero-title {
            margin: 0;
            font-family: "Anton", sans-serif;
            font-size: clamp(3.4rem, 8vw, 8.2rem);
            line-height: 0.88;
            letter-spacing: 0.01em;
            text-transform: uppercase;
            color: #fff;
            text-wrap: balance;
            text-shadow: 0 2px 30px rgba(0, 0, 0, 0.35);
        }

        .hero-copy {
            width: min(100%, 870px);
            margin: 24px auto 0;
            font-size: clamp(1.02rem, 1.65vw, 1.65rem);
            line-height: 1.48;
            color: rgba(244, 242, 238, 0.55);
            font-weight: 600;
            text-wrap: balance;
        }

        .hero-formula {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 16px;
            margin-top: 48px;
            flex-wrap: wrap;
        }

        .goal-note {
            position: absolute;
            left: max(0px, calc(50% - 640px));
            top: -8px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--yellow);
            font-family: "Caveat", cursive;
            font-size: clamp(1.9rem, 2.6vw, 3.1rem);
            line-height: 0.9;
            transform: rotate(-7deg);
            transform-origin: left center;
            user-select: none;
            white-space: nowrap;
            pointer-events: none;
        }

        .goal-note svg {
            width: 76px;
            height: 76px;
            transform: translateY(14px);
        }

        .formula-pill {
            min-width: 180px;
            padding: 16px 24px;
            border-radius: 999px;
            background: var(--yellow);
            color: #151515;
            font-family: "Anton", sans-serif;
            font-size: clamp(1.1rem, 1.5vw, 1.45rem);
            line-height: 1;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.28);
            display: inline-flex;
            justify-content: center;
            align-items: center;
            white-space: nowrap;
        }

        .formula-pill.outline {
            background: transparent;
            color: var(--yellow);
            border: 2px solid rgba(247, 223, 143, 0.92);
            box-shadow: none;
            min-width: 140px;
        }

        .formula-op {
            color: rgba(247, 223, 143, 0.8);
            font-family: "Anton", sans-serif;
            font-size: clamp(1.7rem, 2.4vw, 2.35rem);
            line-height: 1;
        }

        .proof {
            padding: 38px 24px 56px;
        }

        .proof-inner {
            max-width: 1600px;
        }

        .proof-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 22px;
            align-items: start;
        }

        .goal-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 18px;
        }

        .goal-badge {
            width: 78px;
            height: 78px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            color: #171717;
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.18);
            border: 1px solid rgba(0, 0, 0, 0.08);
        }

        .goal-badge svg {
            width: 32px;
            height: 32px;
            stroke: currentColor;
        }

        .goal-badge.yellow { background: #f7d35d; }
        .goal-badge.mint { background: #7dd6ca; }
        .goal-badge.violet { background: #9a76ff; }
        .goal-badge.pink { background: #f38fb2; }
        .goal-badge.blue { background: #7aa6ff; }
        .goal-badge.orange { background: #f8a65a; }

        .goal-label {
            min-height: 38px;
            text-align: center;
            color: rgba(244, 242, 238, 0.9);
            font-family: "Anton", sans-serif;
            font-size: clamp(1.05rem, 1.4vw, 1.4rem);
            line-height: 1.02;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            text-wrap: balance;
        }

        .goal-arrow {
            width: 22px;
            height: 44px;
            color: inherit;
            opacity: 0.95;
        }

        .goal-arrow svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        .goal-card.yellow { --accent: #f7d35d; }
        .goal-card.mint { --accent: #7dd6ca; }
        .goal-card.violet { --accent: #9a76ff; }
        .goal-card.pink { --accent: #f38fb2; }
        .goal-card.blue { --accent: #7aa6ff; }
        .goal-card.orange { --accent: #f8a65a; }

        .goal-card.yellow .goal-arrow { color: var(--accent); }
        .goal-card.mint .goal-arrow { color: var(--accent); }
        .goal-card.violet .goal-arrow { color: var(--accent); }
        .goal-card.pink .goal-arrow { color: var(--accent); }
        .goal-card.blue .goal-arrow { color: var(--accent); }
        .goal-card.orange .goal-arrow { color: var(--accent); }

        .polaroid {
            width: 100%;
            background: #fff;
            border-radius: 8px;
            padding: 10px 10px 16px;
            box-shadow: var(--card-shadow);
            transform: rotate(var(--tilt, 0deg));
            transform-origin: center top;
        }

        .polaroid-media {
            aspect-ratio: 0.92;
            overflow: hidden;
            border-radius: 4px;
            background: #f3f3f3;
        }

        .polaroid-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        .polaroid-body {
            padding: 10px 6px 0;
            text-align: center;
            color: #1b1b1b;
        }

        .polaroid-title {
            margin: 0;
            font-size: clamp(1.08rem, 1.45vw, 1.28rem);
            line-height: 1.1;
            font-weight: 800;
            text-wrap: balance;
        }

        .polaroid-subtitle {
            margin: 6px 0 0;
            font-size: clamp(0.96rem, 1.08vw, 1.08rem);
            line-height: 1.2;
            color: rgba(27, 27, 27, 0.56);
            text-wrap: balance;
        }

        .tilt-a { --tilt: 1.2deg; }
        .tilt-b { --tilt: -1.3deg; }
        .tilt-c { --tilt: 1.6deg; }
        .tilt-d { --tilt: -0.9deg; }
        .tilt-e { --tilt: 1.15deg; }
        .tilt-f { --tilt: -0.7deg; }

        .footer {
            padding: 28px 24px 42px;
            color: rgba(244, 242, 238, 0.46);
            font-size: 0.95rem;
        }

        .footer-inner {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .footer-copy {
            margin: 0;
            text-align: center;
            text-wrap: balance;
        }

        @media (max-width: 1280px) {
            .proof-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 18px;
            }

            .goal-note {
                position: static;
                justify-content: center;
                margin: 0 auto 18px;
                transform: rotate(-5deg);
                white-space: normal;
                text-align: left;
            }
        }

        @media (max-width: 820px) {
            .hero {
                padding-top: 64px;
            }

            .hero-title {
                font-size: clamp(2.9rem, 15vw, 5.3rem);
            }

            .hero-copy {
                font-size: 1rem;
            }

            .hero-formula {
                gap: 12px;
                margin-top: 38px;
            }

            .formula-pill {
                min-width: 154px;
                padding: 14px 18px;
            }

            .proof-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 560px) {
            .hero {
                padding-left: 18px;
                padding-right: 18px;
            }

            .eyebrow {
                font-size: 12px;
                margin-bottom: 14px;
            }

            .hero-copy {
                margin-top: 18px;
            }

            .hero-formula {
                gap: 10px;
            }

            .formula-pill {
                min-width: 140px;
                padding: 13px 16px;
            }

            .formula-pill.outline {
                min-width: 120px;
            }

            .formula-op {
                width: 100%;
                text-align: center;
            }

            .proof {
                padding-top: 28px;
            }

            .proof-grid {
                grid-template-columns: 1fr;
                gap: 28px;
            }

            .goal-card {
                gap: 14px;
            }

            .polaroid {
                width: min(100%, 340px);
            }

            .footer {
                padding-top: 8px;
            }
        }
    </style>
</head>
<body>
<main class="page">
    <section class="hero" aria-label="Kolabing ideas hero">
        <div class="hero-inner">
            <p class="eyebrow">Kolab Ideas</p>
            <h1 class="hero-title">Build Any Kind of Kolab</h1>
            <p class="hero-copy">
                One community. One business goal. Endless ways to make people show up, try, share,
                review, buy or come back.
            </p>

            <div class="hero-formula" aria-label="Community plus business goal equals kolab">
                <div class="goal-note" aria-hidden="true">
                    what's your business goal?
                    <svg viewBox="0 0 80 80" fill="none" aria-hidden="true">
                        <path d="M10 28 C 20 18, 38 18, 52 24 C 61 28, 66 34, 70 46" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                        <path d="M59 39 C 64 46, 67 53, 70 62" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                        <path d="M64 55 L 70 62 L 73 53" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>

                <span class="formula-pill">Community</span>
                <span class="formula-op">+</span>
                <span class="formula-pill">Business Goal</span>
                <span class="formula-op">=</span>
                <span class="formula-pill outline">Kolab</span>
            </div>
        </div>
    </section>

    <section class="proof" aria-label="Proof of work examples">
        <div class="proof-inner">
            <div class="proof-grid">
                <article class="goal-card yellow tilt-a">
                    <div class="goal-badge yellow" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M8 16v-4a4 4 0 0 1 8 0v4" />
                            <path d="M4 20a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4" />
                            <path d="M17 7a3 3 0 1 1 0 6" />
                        </svg>
                    </div>
                    <div class="goal-label">Fill a place</div>
                    <div class="goal-arrow" aria-hidden="true">
                        <svg viewBox="0 0 24 44" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 4v30" />
                            <path d="M5 28l7 7 7-7" />
                        </svg>
                    </div>
                    <div class="polaroid">
                        <div class="polaroid-media">
                            <img src="{{ asset('uploads/run-club-cafe.png') }}" alt="People sharing coffee cups outdoors" loading="lazy">
                        </div>
                        <div class="polaroid-body">
                            <p class="polaroid-title">Run club + caf&eacute;</p>
                            <p class="polaroid-subtitle">Morning run + coffee</p>
                        </div>
                    </div>
                </article>

                <article class="goal-card mint tilt-b">
                    <div class="goal-badge mint" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M7 20h10" />
                            <path d="M10 20v-7l-4-7h12l-4 7v7" />
                        </svg>
                    </div>
                    <div class="goal-label">Test a product</div>
                    <div class="goal-arrow" aria-hidden="true">
                        <svg viewBox="0 0 24 44" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 4v30" />
                            <path d="M5 28l7 7 7-7" />
                        </svg>
                    </div>
                    <div class="polaroid">
                        <div class="polaroid-media">
                            <img src="{{ asset('uploads/Gemini_Generated_Image_j3ohygj3ohygj3oh.png') }}" alt="Cyclists on a mountain road" loading="lazy">
                        </div>
                        <div class="polaroid-body">
                            <p class="polaroid-title">Cycling crew + hydration brand</p>
                            <p class="polaroid-subtitle">Ride test</p>
                        </div>
                    </div>
                </article>

                <article class="goal-card violet tilt-c">
                    <div class="goal-badge violet" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 21s6-4.35 6-10a6 6 0 0 0-12 0c0 5.65 6 10 6 10Z" />
                            <circle cx="12" cy="11" r="2.2" />
                        </svg>
                    </div>
                    <div class="goal-label">Launch</div>
                    <div class="goal-arrow" aria-hidden="true">
                        <svg viewBox="0 0 24 44" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 4v30" />
                            <path d="M5 28l7 7 7-7" />
                        </svg>
                    </div>
                    <div class="polaroid">
                        <div class="polaroid-media">
                            <img src="{{ asset('uploads/Gemini_Generated_Image_rfno1grfno1grfno.png') }}" alt="Yoga silhouettes at sunset" loading="lazy">
                        </div>
                        <div class="polaroid-body">
                            <p class="polaroid-title">Yoga club + activewear brand</p>
                            <p class="polaroid-subtitle">Try-on flow</p>
                        </div>
                    </div>
                </article>

                <article class="goal-card pink tilt-d">
                    <div class="goal-badge pink" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 7h16v10H8l-4 4V7Z" />
                            <path d="M8 11h8" />
                            <path d="M8 14h5" />
                        </svg>
                    </div>
                    <div class="goal-label">Feedback</div>
                    <div class="goal-arrow" aria-hidden="true">
                        <svg viewBox="0 0 24 44" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 4v30" />
                            <path d="M5 28l7 7 7-7" />
                        </svg>
                    </div>
                    <div class="polaroid">
                        <div class="polaroid-media">
                            <img src="{{ asset('uploads/feedback-skincare.png') }}" alt="Women testing skincare products together" loading="lazy">
                        </div>
                        <div class="polaroid-body">
                            <p class="polaroid-title">Women&#39;s group + skincare brand</p>
                            <p class="polaroid-subtitle">Product testing circle</p>
                        </div>
                    </div>
                </article>

                <article class="goal-card blue tilt-e">
                    <div class="goal-badge blue" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="5" y="7" width="14" height="11" rx="2" />
                            <path d="M9 7l2-3h2l2 3" />
                            <circle cx="12" cy="12.5" r="2.4" />
                        </svg>
                    </div>
                    <div class="goal-label">Content</div>
                    <div class="goal-arrow" aria-hidden="true">
                        <svg viewBox="0 0 24 44" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 4v30" />
                            <path d="M5 28l7 7 7-7" />
                        </svg>
                    </div>
                    <div class="polaroid">
                        <div class="polaroid-media">
                            <img src="{{ asset('uploads/content-dog-walk.png') }}" alt="Dog community on a city photo walk" loading="lazy">
                        </div>
                        <div class="polaroid-body">
                            <p class="polaroid-title">Dog community + pet brand</p>
                            <p class="polaroid-subtitle">Dog photo walk</p>
                        </div>
                    </div>
                </article>

                <article class="goal-card orange tilt-f">
                    <div class="goal-badge orange" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 21s7-4.6 7-10.2A7 7 0 0 0 5 11c0 5.4 7 10 7 10Z" />
                            <path d="M9 11.8h6" />
                            <path d="M10 9.3h4" />
                        </svg>
                    </div>
                    <div class="goal-label">Loyalty</div>
                    <div class="goal-arrow" aria-hidden="true">
                        <svg viewBox="0 0 24 44" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 4v30" />
                            <path d="M5 28l7 7 7-7" />
                        </svg>
                    </div>
                    <div class="polaroid">
                        <div class="polaroid-media">
                            <img src="{{ asset('uploads/loyalty-wine-book.png') }}" alt="Book club gathered around a table with wine" loading="lazy">
                        </div>
                        <div class="polaroid-body">
                            <p class="polaroid-title">Book club + wine bar</p>
                            <p class="polaroid-subtitle">Monthly tasting night</p>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="footer-inner">
            <p class="footer-copy">
                Kolabing - built for real people, in real places.
            </p>
        </div>
    </footer>
</main>
</body>
</html>
