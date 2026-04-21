<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} | Kolabing</title>
    <meta name="description" content="{{ $description }}">
    <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Kolabing">
    <meta property="og:title" content="{{ $title }} | Kolabing">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ url('/social-preview.svg') }}">
    <meta property="og:image:alt" content="Kolabing local business and community collaboration platform">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }} | Kolabing">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ url('/social-preview.svg') }}">
    <meta name="theme-color" content="#0D1216">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/brand/logo-mark.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,700;9..40,800&family=Montserrat:ital,wght@0,700;0,900;1,700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
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
                },
            },
        };
    </script>
</head>
<body class="bg-off-white text-off-black font-sans">
    <header class="border-b border-off-black/10 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-6 py-4">
            <a href="{{ route('home') }}" class="flex items-center gap-3 text-off-black">
                <img src="/brand/logo-wordmark.svg" alt="Kolabing" width="1200" height="260" class="h-8 w-auto">
            </a>
            <nav class="flex flex-wrap items-center gap-4 text-sm font-medium text-off-black/70">
                <a href="{{ route('for-businesses') }}" class="hover:text-off-black">Businesses</a>
                <a href="{{ route('for-communities') }}" class="hover:text-off-black">Communities</a>
                <a href="{{ route('support') }}" class="hover:text-off-black">Support</a>
                <a href="{{ route('privacy') }}" class="hover:text-off-black">Privacy</a>
                <a href="{{ route('terms') }}" class="hover:text-off-black">Terms</a>
            </nav>
        </div>
    </header>

    <main>
        {{ $slot }}
    </main>

    <footer class="border-t border-off-black/10 bg-off-black py-12 text-white">
        <div class="mx-auto flex max-w-6xl flex-col gap-6 px-6 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="font-montserrat text-xl font-black uppercase tracking-wide">Kolabing</p>
                <p class="mt-2 max-w-xl text-sm text-white/70">Kolabing helps local businesses and communities plan partnerships that turn events into foot traffic, member value, and repeat visits.</p>
            </div>
            <div class="flex flex-wrap gap-4 text-sm text-white/70">
                <a href="{{ route('support') }}" class="hover:text-primary">Support</a>
                <a href="mailto:support@kolabing.com" class="hover:text-primary">support@kolabing.com</a>
                <a href="{{ route('sitemap') }}" class="hover:text-primary">Sitemap</a>
                <a href="{{ route('llms') }}" class="hover:text-primary">llms.txt</a>
            </div>
        </div>
    </footer>
</body>
</html>
