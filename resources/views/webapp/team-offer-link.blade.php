<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Kolabing</title>
    <style>
        body{font-family:system-ui,sans-serif;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#fafaf7;color:#1a1a1a}
        main{max-width:26rem;padding:2rem;text-align:center}
        a.store{display:block;margin:.75rem 0;padding:.9rem 1rem;border-radius:.75rem;background:#1a1a1a;color:#fff;text-decoration:none;font-weight:600}
        p{line-height:1.5}
    </style>
</head>
<body>
<main>
    <h1>Kolabing</h1>
    @if ($kind === 'claim')
        <p>The Kolabing team has an offer for your page. Get the app, sign in, and claim your page to answer it. You can delete the page whenever you like after you claim it.</p>
    @else
        <p>The Kolabing team sent you an offer. Get the app and sign in: the offer is waiting in the "Kolabing team" chat.</p>
    @endif
    @forelse ($stores as $label => $url)
        <a class="store" href="{{ $url }}">{{ $label }}</a>
    @empty
        <p>Search for "Kolabing" in your phone's app store.</p>
    @endforelse
    <p><small>Already have the app? Open this link again from your phone's message and choose Kolabing.</small></p>
</main>
</body>
</html>
