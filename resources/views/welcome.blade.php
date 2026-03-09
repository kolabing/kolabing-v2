<!DOCTYPE html>
<html class="scroll-smooth" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Kolabing - Download the App</title>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Montserrat:ital,wght@0,900;1,900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: "#FFD560",
                        "off-black": "#1B1F1C",
                        "off-white": "#FDFBF7",
                    },
                    fontFamily: {
                        sans: ["DM Sans", "sans-serif"],
                        montserrat: ["Montserrat", "sans-serif"],
                    },
                    borderRadius: {
                        '4xl': '2.5rem',
                        '5xl': '3.5rem',
                    },
                },
            },
        };
    </script>
    <style type="text/tailwindcss">
        @layer base {
            body {
                font-family: "DM Sans", sans-serif;
                background-color: #FDFBF7;
                color: #1B1F1C;
                line-height: 1.6;
                letter-spacing: 0.01em;
            }
        }
        .hero-video-container {
            position: relative;
            width: 100%;
            height: 100svh;
            min-height: 600px;
            overflow: hidden;
        }
        @media (min-width: 768px) {
            .hero-video-container {
                height: 90vh;
            }
        }
        .hero-video {
            position: absolute;
            top: 50%;
            left: 50%;
            min-width: 100%;
            min-height: 100%;
            width: auto;
            height: auto;
            transform: translate(-50%, -50%);
            object-fit: cover;
        }
        .dot-pattern {
            background-image: radial-gradient(#1B1F1C 1px, transparent 1px);
            background-size: 32px 32px;
        }
        .timeline-svg-path {
            stroke: #FFD560;
            stroke-width: 4;
            stroke-dasharray: 12 12;
            fill: none;
            stroke-linecap: round;
        }
        @media (min-width: 1024px) {
            .step-1 { transform: translateY(0); }
            .step-2 { transform: translateY(100px); }
            .step-3 { transform: translateY(-20px); }
            .step-4 { transform: translateY(80px); }
        }
    </style>
</head>
<body class="bg-off-white text-off-black">

{{-- Header --}}
<header class="fixed top-0 w-full z-50 bg-off-white/80 backdrop-blur-md text-off-black px-4 md:px-8 py-4 md:py-5 flex justify-between items-center border-b border-off-black/5">
    <div class="flex items-center gap-2">
        <span class="bg-primary text-off-black font-bold px-2.5 py-1 rounded-lg text-xl">K</span>
        <span class="font-bold tracking-tight text-2xl">kolabing</span>
    </div>
    <nav class="hidden md:flex items-center gap-10 text-sm font-medium">
        <a class="hover:text-primary transition-colors" href="#how-it-works">How it Works</a>
        <a class="hover:text-primary transition-colors" href="#built-for-both">Solutions</a>
        <a class="bg-primary text-off-black px-8 py-2.5 rounded-full hover:shadow-lg transition-all" href="#">Download</a>
    </nav>
    <button class="md:hidden text-off-black">
        <span class="material-symbols-outlined">menu</span>
    </button>
</header>

{{-- Hero Section with Video Background --}}
<section class="hero-video-container">
    <video
        class="hero-video brightness-[0.8] contrast-[1.05]"
        autoplay
        muted
        loop
        playsinline
    >
        <source src="/assets/video.mov" type="video/mp4" />
    </video>

    <div class="absolute inset-0 bg-gradient-to-b from-off-black/40 via-off-black/20 to-off-black/40 flex flex-col items-center justify-center text-center px-4">
        <div class="bg-white/20 backdrop-blur-md border border-white/30 text-white text-xs font-bold px-4 py-1.5 rounded-full mb-8 tracking-widest uppercase">
            Available on iOS + Android
        </div>
        <h1 class="font-montserrat font-[900] text-white uppercase text-[2.2rem] sm:text-5xl lg:text-[64px] mb-6 md:mb-8 max-w-7xl leading-[1.1] tracking-tight px-2">
            Kolabing makes the <span class="text-primary italic">MATCH!</span>
        </h1>
        <p class="text-lg md:text-xl lg:text-2xl font-medium mb-8 md:mb-12 text-white/95 max-w-3xl leading-relaxed px-2">
            Local businesses x real communities.<br/>
            <span class="text-primary font-bold">Not influencers. Communities.</span>
        </p>
        <div class="flex flex-col sm:flex-row gap-4 md:gap-5 mb-6 w-full sm:w-auto px-4 sm:px-0">
            <a class="bg-off-black text-white px-6 md:px-8 py-3.5 md:py-4 rounded-2xl flex items-center gap-3 hover:bg-black transition-all shadow-xl group justify-center sm:justify-start" href="#">
                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.1 2.48-1.34.03-1.77-.79-3.31-.79-1.54 0-2.02.77-3.31.82-1.34.05-2.33-1.32-3.17-2.54-1.72-2.5-3.04-7.07-1.27-10.13 1.13-1.95 3.12-2.73 4.61-2.73 1.3 0 2.21.72 2.91.72.69 0 1.83-.87 3.37-.87 1.26 0 2.39.54 3.13 1.48-1.07.65-1.58 1.94-1.58 3.39 0 1.82 1.48 3.15 2.92 3.15.11 0 .22 0 .33-.01-.2 1.63-.82 3.03-1.73 4.41M15.97 3.38c.63-.77 1.05-1.83.94-2.88-.91.04-2 .61-2.65 1.37-.58.67-1.09 1.76-.95 2.79.99.08 2.03-.51 2.66-1.28z"></path></svg>
                <div class="text-left">
                    <div class="text-[10px] uppercase font-bold opacity-60 leading-none">Download on the</div>
                    <div class="text-lg font-bold leading-none">App Store</div>
                </div>
            </a>
            <a class="bg-white text-off-black px-6 md:px-8 py-3.5 md:py-4 rounded-2xl flex items-center gap-3 hover:bg-gray-50 transition-all shadow-xl group justify-center sm:justify-start" href="#">
                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M3,20.5V3.5C3,2.91 3.34,2.39 3.84,2.15L13.69,12L3.84,21.85C3.34,21.61 3,21.09 3,20.5M16.81,15.12L18.66,16.19C19.21,16.5 19.5,17.07 19.5,17.5C19.5,17.93 19.21,18.5 18.66,18.81L4.67,26.89C4.4,27.04 4.12,27.1 3.87,27.1C3.54,27.1 3.24,26.96 3.03,26.75L14.67,15.12L16.81,15.12M21.3,13.12L14.67,12L21.3,10.88C21.85,10.79 22.25,10.28 22.25,9.75C22.25,9.22 21.85,8.71 21.3,8.62L4.67,0.11C4.4,0 4.12,-0.1 3.87,-0.1C3.54,-0.1 3.24,0.04 3.03,0.25L14.67,11.88L16.81,11.88L18.66,10.81C19.21,10.5 19.5,9.93 19.5,9.5C19.5,9.07 19.21,8.5 18.66,8.19L16.81,7.12"></path></svg>
                <div class="text-left">
                    <div class="text-[10px] uppercase font-bold opacity-60 leading-none">Get it on</div>
                    <div class="text-lg font-bold leading-none">Google Play</div>
                </div>
            </a>
        </div>
        <p class="text-white/80 text-sm font-medium">Free to download • Built for real communities &amp; local businesses</p>
    </div>
</section>

{{-- How It Works --}}
<section class="py-16 md:py-24 lg:py-32 bg-off-white relative overflow-hidden" id="how-it-works">
    <div class="absolute inset-0 dot-pattern opacity-[0.03]"></div>
    <div class="max-w-7xl mx-auto px-6 relative z-10">
        <div class="text-center mb-12 md:mb-20 lg:mb-32">
            <h2 class="text-3xl md:text-4xl lg:text-5xl font-montserrat font-black uppercase mb-4 tracking-tight">HOW TO GET A <span class="text-primary italic">KOLAB</span></h2>
            <p class="text-off-black/60 text-xl font-medium">Post. Match. Connect. Make it happen.</p>
        </div>
        <div class="relative pb-0 md:pb-24 lg:pb-40">
            <svg class="hidden lg:block absolute top-8 left-0 w-full h-[400px] z-0 pointer-events-none" preserveAspectRatio="none" viewBox="0 0 1200 400">
                <path class="timeline-svg-path" d="M 150,32 C 300,32 300,132 450,132 C 600,132 600,12 750,12 C 900,12 900,112 1050,112"></path>
            </svg>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 md:gap-10 lg:gap-6 relative z-10">
                <div class="step-1 flex flex-col items-center sm:items-start text-center sm:text-left">
                    <div class="w-16 h-16 rounded-full bg-primary/30 text-off-black font-black flex items-center justify-center text-xl mb-6 border-4 border-white shadow-sm shrink-0 relative z-20">01</div>
                    <div class="bg-white p-6 md:p-7 lg:p-8 rounded-3xl border-2 border-primary shadow-sm w-full transition-transform hover:-translate-y-2 duration-300">
                        <h4 class="font-bold text-lg md:text-xl mb-3 leading-tight flex flex-col sm:flex-row items-center sm:items-start gap-2">
                            <span class="text-2xl">➕</span> Create a Kolab
                        </h4>
                        <p class="text-off-black/60 text-[15px] leading-relaxed">Got an event? A venue? A product? Post your Kolab and define what you're looking for.</p>
                    </div>
                </div>
                <div class="step-2 flex flex-col items-center sm:items-start text-center sm:text-left">
                    <div class="w-16 h-16 rounded-full bg-primary/30 text-off-black font-black flex items-center justify-center text-xl mb-6 border-4 border-white shadow-sm shrink-0 relative z-20">02</div>
                    <div class="bg-white p-6 md:p-7 lg:p-8 rounded-3xl border-2 border-primary shadow-sm w-full transition-transform hover:-translate-y-2 duration-300">
                        <h4 class="font-bold text-lg md:text-xl mb-3 leading-tight flex flex-col sm:flex-row items-center sm:items-start gap-2">
                            <span class="text-2xl">🔍</span> Get Matches
                        </h4>
                        <p class="text-off-black/60 text-[15px] leading-relaxed">Aligned communities and businesses find you — or you discover them.</p>
                    </div>
                </div>
                <div class="step-3 flex flex-col items-center sm:items-start text-center sm:text-left">
                    <div class="w-16 h-16 rounded-full bg-primary/30 text-off-black font-black flex items-center justify-center text-xl mb-6 border-4 border-white shadow-sm shrink-0 relative z-20">03</div>
                    <div class="bg-white p-6 md:p-7 lg:p-8 rounded-3xl border-2 border-primary shadow-sm w-full transition-transform hover:-translate-y-2 duration-300">
                        <h4 class="font-bold text-lg md:text-xl mb-3 leading-tight flex flex-col sm:flex-row items-center sm:items-start gap-2">
                            <span class="text-2xl">💬</span> Connect
                        </h4>
                        <p class="text-off-black/60 text-[15px] leading-relaxed">Chat directly inside the app. Ask questions. Finalize the plan.</p>
                    </div>
                </div>
                <div class="step-4 flex flex-col items-center sm:items-start text-center sm:text-left">
                    <div class="w-16 h-16 rounded-full bg-primary/30 text-off-black font-black flex items-center justify-center text-xl mb-6 border-4 border-white shadow-sm shrink-0 relative z-20">04</div>
                    <div class="bg-white p-6 md:p-7 lg:p-8 rounded-3xl border-2 border-primary shadow-sm w-full transition-transform hover:-translate-y-2 duration-300">
                        <h4 class="font-bold text-lg md:text-xl mb-3 leading-tight flex flex-col sm:flex-row items-center sm:items-start gap-2">
                            <span class="text-2xl">🚀</span> Make It Happen
                        </h4>
                        <p class="text-off-black/60 text-[15px] leading-relaxed">Host the Kolab. Get real people, real engagement, real results.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Features Strip --}}
<section class="py-12 bg-primary/20">
    <div class="max-w-4xl mx-auto px-6 text-center">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="flex items-center justify-center gap-2 text-sm md:text-base">
                <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-off-black text-lg">bookmark</span>
                </div>
                <span class="font-bold">Save collabs</span>
            </div>
            <div class="flex items-center justify-center gap-2 text-sm md:text-base">
                <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-off-black text-lg">chat_bubble</span>
                </div>
                <span class="font-bold">Chat in-app</span>
            </div>
            <div class="flex items-center justify-center gap-2 text-sm md:text-base">
                <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-off-black text-lg">insights</span>
                </div>
                <span class="font-bold">Track outcomes</span>
            </div>
        </div>
    </div>
</section>

{{-- Real Outcomes --}}
<section class="py-16 md:py-24 lg:py-32 bg-primary relative overflow-hidden">
    <div class="max-w-7xl mx-auto px-6 relative z-10 text-center">
        <h2 class="text-3xl md:text-4xl lg:text-5xl font-montserrat font-black uppercase text-off-black mb-10 md:mb-16"><span class="text-white italic">REAL</span> OUTCOMES</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="bg-white/50 backdrop-blur-sm p-8 md:p-10 lg:p-12 rounded-4xl border border-white/30 shadow-sm">
                <span class="material-symbols-outlined text-6xl mb-8 text-off-black/80">groups</span>
                <h3 class="text-2xl font-bold mb-4">30 runners in your cafe</h3>
                <p class="text-off-black/70 font-medium">Actual physical foot traffic from active community members.</p>
            </div>
            <div class="bg-white/50 backdrop-blur-sm p-8 md:p-10 lg:p-12 rounded-4xl border border-white/30 shadow-sm">
                <span class="material-symbols-outlined text-6xl mb-8 text-off-black/80">videocam</span>
                <h3 class="text-2xl font-bold mb-4">UGC from real customers</h3>
                <p class="text-off-black/70 font-medium">Authentic content created by people who actually use your service.</p>
            </div>
            <div class="bg-white/50 backdrop-blur-sm p-8 md:p-10 lg:p-12 rounded-4xl border border-white/30 shadow-sm">
                <span class="material-symbols-outlined text-6xl mb-8 text-off-black/80">sync</span>
                <h3 class="text-2xl font-bold mb-4">Repeat visits</h3>
                <p class="text-off-black/70 font-medium">Turn community events into long-term loyal customer bases.</p>
            </div>
        </div>
    </div>
</section>

{{-- Why Kolabing Comparison Table --}}
<section class="py-16 md:py-28 lg:py-40 px-4 md:px-6 bg-white">
    <div class="max-w-6xl mx-auto text-center">
        <h2 class="text-3xl md:text-4xl lg:text-5xl font-montserrat font-black uppercase mb-10 md:mb-16 lg:mb-20 text-off-black leading-tight">
            <span class="text-primary italic">WHY</span> KOLABING? <span class="text-primary italic">BECAUSE</span> <br/>
            <span class="text-off-black inline-block mt-2 relative">
                <span class="relative z-10">COMMUNITY MARKETING</span> IS THE PRESENT.
                <span class="absolute -bottom-2 left-0 w-full h-4 bg-primary/30 -z-0"></span>
            </span>
        </h2>
        <div class="overflow-x-auto rounded-[2.5rem] border border-off-black/5 shadow-2xl shadow-off-black/5 bg-white">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-off-white">
                        <th class="p-4 md:p-7 lg:p-10 text-left text-xs uppercase tracking-[0.2em] text-off-black/40 font-bold">WHY IT WORKS</th>
                        <th class="p-4 md:p-7 lg:p-10 text-center bg-primary/10">
                            <div class="flex flex-col items-center">
                                <span class="text-2xl font-bold text-off-black">kolabing</span>
                                <span class="text-[10px] font-bold text-off-black/50 uppercase tracking-widest">Communities</span>
                            </div>
                        </th>
                        <th class="p-4 md:p-7 lg:p-10 text-center text-xs uppercase tracking-[0.2em] text-off-black/40 font-bold">Influencers</th>
                        <th class="p-4 md:p-7 lg:p-10 text-center text-xs uppercase tracking-[0.2em] text-off-black/40 font-bold">Paid Ads</th>
                    </tr>
                </thead>
                <tbody class="font-medium text-sm md:text-base lg:text-lg">
                    <tr class="border-t border-off-black/5">
                        <td class="p-4 md:p-7 lg:p-10 text-left text-off-black/60">Real connection</td>
                        <td class="p-4 md:p-7 lg:p-10 text-center bg-primary/5 font-bold">Real people in your place</td>
                        <td class="p-4 md:p-7 lg:p-10 text-center text-off-black/40">Online shoutout</td>
                        <td class="p-4 md:p-7 lg:p-10 text-center text-off-black/40">Scroll + ignore</td>
                    </tr>
                    <tr class="border-t border-off-black/5">
                        <td class="p-4 md:p-7 lg:p-10 text-left text-off-black/60">Trust</td>
                        <td class="p-4 md:p-7 lg:p-10 text-center bg-primary/5 font-bold">Community presence</td>
                        <td class="p-4 md:p-7 lg:p-10 text-center text-off-black/40">People are tired of influencers — low trust</td>
                        <td class="p-4 md:p-7 lg:p-10 text-center text-off-black/40">Low trust</td>
                    </tr>
                    <tr class="border-t border-off-black/5">
                        <td class="p-4 md:p-7 lg:p-10 text-left text-off-black/60">Results over time</td>
                        <td class="p-4 md:p-7 lg:p-10 text-center bg-primary/5 font-bold">Repeat customers</td>
                        <td class="p-4 md:p-7 lg:p-10 text-center text-off-black/40">One-time spike</td>
                        <td class="p-4 md:p-7 lg:p-10 text-center text-off-black/40">Stops when you stop paying</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

{{-- Built For Both --}}
<section class="py-16 md:py-24 lg:py-32 bg-off-black text-white" id="built-for-both">
    <div class="max-w-7xl mx-auto px-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
            <div class="bg-white/5 p-8 md:p-10 lg:p-12 rounded-5xl border border-white/5">
                <h3 class="text-2xl md:text-3xl font-bold mb-8 md:mb-10 text-primary">For Businesses</h3>
                <ul class="space-y-5 md:space-y-8 mb-10 md:mb-14">
                    <li class="flex gap-5">
                        <span class="material-symbols-outlined text-primary">check_circle</span>
                        <span class="text-lg">Direct access to niche local demographics</span>
                    </li>
                    <li class="flex gap-5">
                        <span class="material-symbols-outlined text-primary">check_circle</span>
                        <span class="text-lg">Guaranteed foot traffic for slow days</span>
                    </li>
                    <li class="flex gap-5">
                        <span class="material-symbols-outlined text-primary">check_circle</span>
                        <span class="text-lg">Cost-effective marketing with high ROI</span>
                    </li>
                </ul>
                <a class="inline-block bg-primary text-off-black font-bold px-10 py-5 rounded-2xl tracking-wide w-full text-center hover:shadow-xl transition-all" href="#">Get more customers</a>
            </div>
            <div class="bg-white/5 p-8 md:p-10 lg:p-12 rounded-5xl border border-white/5">
                <h3 class="text-2xl md:text-3xl font-bold mb-8 md:mb-10 text-white">For Communities</h3>
                <ul class="space-y-5 md:space-y-8 mb-10 md:mb-14">
                    <li class="flex gap-5">
                        <span class="material-symbols-outlined text-primary">check_circle</span>
                        <span class="text-lg">Secure premium venues for free</span>
                    </li>
                    <li class="flex gap-5">
                        <span class="material-symbols-outlined text-primary">check_circle</span>
                        <span class="text-lg">Get perks and discounts for your members</span>
                    </li>
                    <li class="flex gap-5">
                        <span class="material-symbols-outlined text-primary">check_circle</span>
                        <span class="text-lg">Monetize your leadership and influence</span>
                    </li>
                </ul>
                <a class="inline-block bg-white text-off-black font-bold px-10 py-5 rounded-2xl tracking-wide w-full text-center hover:shadow-xl transition-all" href="#">Empower my community</a>
            </div>
        </div>
    </div>
</section>

{{-- Footer --}}
<footer class="bg-off-black text-white py-14 md:py-24">
    <div class="max-w-7xl mx-auto px-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-8 md:gap-16 mb-12 md:mb-20">
            <div class="flex items-center gap-3">
                <span class="bg-primary text-off-black font-bold px-3 py-1.5 rounded-xl text-2xl">K</span>
                <span class="font-bold tracking-tight text-3xl">kolabing</span>
            </div>
            <div class="flex flex-wrap gap-10 text-sm font-medium text-white/60">
                <a class="hover:text-primary transition-colors" href="#">Terms</a>
                <a class="hover:text-primary transition-colors" href="#">Privacy</a>
                <a class="hover:text-primary transition-colors" href="#">Support</a>
                <a class="hover:text-primary transition-colors" href="#">Careers</a>
            </div>
        </div>
        <div class="pt-10 md:pt-16 border-t border-white/10 text-center text-white/30 text-xs font-medium tracking-wide">
            © 2024 Kolabing Platform. Built for real people, in real places.
        </div>
    </div>
</footer>

</body>
</html>
