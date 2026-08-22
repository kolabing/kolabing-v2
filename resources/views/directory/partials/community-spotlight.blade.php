@php
    /** @var \App\Models\CrmAccount $a  the #1 community */
    $m = $a->metrics ?? [];
    $handle = $m['handle'] ?? ($a->instagram_handle ?? null);
    $igUrl = $m['instagram_url'] ?? ($handle ? 'https://instagram.com/'.ltrim($handle, '@') : null);
    $appUrl = $m['app_url'] ?? rtrim(config('webapp.url', 'https://app.kolabing.com'), '/').'/register';
    $cleanName = trim(preg_replace('/\s+/', ' ', preg_replace('/[^\p{L}\s]/u', ' ', str_replace(['º', 'ª'], '', $a->name))));
    $words = array_values(array_filter(explode(' ', $cleanName)));
    $initials = count($words) >= 2 ? mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1)) : mb_strtoupper(mb_substr($words[0] ?? 'K', 0, 2));
@endphp

<div class="mt-8 overflow-hidden rounded-[2rem] bg-off-black p-7 text-white sm:p-9">
    <p class="text-xs font-bold uppercase tracking-[0.24em] text-primary">Featured · #1 in {{ $a->metrics['city'] ?? '' }}</p>
    <div class="mt-5 flex flex-col gap-6 sm:flex-row sm:items-center">
        <div class="shrink-0">
            @if (! empty($m['photo_url']))
                <img src="{{ $m['photo_url'] }}" alt="{{ $a->name }}" width="112" height="112" class="h-28 w-28 rounded-3xl object-cover ring-1 ring-white/15">
            @else
                <div class="flex h-28 w-28 items-center justify-center rounded-3xl bg-primary font-montserrat text-3xl font-black text-off-black">{{ $initials }}</div>
            @endif
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="font-montserrat text-3xl font-black uppercase leading-none">{{ $a->name }}</h2>
                @if ($handle)
                    <a href="{{ $igUrl }}" target="_blank" rel="noopener nofollow" class="text-sm font-medium text-white/50 hover:text-white">{{ $handle }}</a>
                @endif
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                @if (! empty($m['members']))<span class="rounded-full bg-primary px-3 py-1 text-xs font-bold text-off-black">{{ $m['members'] }}</span>@endif
                @if (! empty($m['cadence']))<span class="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-white/80">{{ $m['cadence'] }}</span>@endif
                @if (! empty($m['venue']))<span class="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-white/80">📍 {{ $m['venue'] }}</span>@endif
            </div>
            @if (! empty($m['blurb']))
                <p class="mt-3 max-w-2xl text-white/75">{{ $m['blurb'] }}</p>
            @endif
            <div class="mt-5 flex flex-wrap items-center gap-3">
                <a href="{{ $appUrl }}" class="rounded-full bg-primary px-6 py-2.5 text-sm font-bold text-off-black transition hover:bg-primary/90">Find them on Kolabing</a>
                @if ($igUrl)<a href="{{ $igUrl }}" target="_blank" rel="noopener nofollow" class="rounded-full border border-white/25 px-5 py-2.5 text-sm font-semibold text-white/80 transition hover:border-white hover:text-white">Instagram</a>@endif
            </div>
        </div>
    </div>
</div>
