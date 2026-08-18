<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $name }} · Kolabing</title>
    <style>
        body { margin: 0; background: transparent; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .kb-badge { display: inline-flex; align-items: center; gap: 12px; background: #1B1F1C; color: #FDFBF7;
            border-radius: 14px; padding: 12px 16px; text-decoration: none; box-shadow: 0 6px 22px rgba(27,31,28,.18); }
        .kb-rank { font-size: 28px; font-weight: 900; color: #FFD560; line-height: 1; }
        .kb-txt small { display: block; font-size: 9px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: rgba(253,251,247,.5); }
        .kb-txt b { font-size: 14px; font-weight: 700; }
    </style>
</head>
<body>
    <a class="kb-badge" href="{{ $url }}" target="_blank" rel="noopener">
        @if ($rank)<span class="kb-rank">#{{ $rank }}</span>@endif
        <span class="kb-txt">
            <small>Kolabing · {{ now()->year }}</small>
            <b>{{ $name }} · {{ $city }}</b>
        </span>
    </a>
</body>
</html>
