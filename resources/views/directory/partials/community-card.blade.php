@php
    /** @var \App\Models\CrmAccount $a */
    /** @var int $i (0-based rank) */
    $counts = $counts ?? [];
    $m = $a->metrics ?? [];
    $handle = $m['handle'] ?? ($a->instagram_handle ?? null);
    $igUrl = $m['instagram_url'] ?? ($handle ? 'https://instagram.com/'.ltrim($handle, '@') : null);
    $blurb = $m['blurb'] ?? trim(implode(' · ', array_filter([$m['audience'] ?? null, $m['cadence'] ?? null, $m['collab'] ?? null])));

    // Deterministic on-brand avatar tile by vertical; initials in Montserrat black.
    $initials = collect(preg_split('/\s+/', trim(preg_replace('/[^\p{L}\s]/u', '', $a->name)) ?: 'K'))
        ->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $vert = mb_strtolower($m['vertical'] ?? '');
    $tiles = ['run' => '#FFE9A3', 'cycl' => '#FFD560', 'well' => '#CDE9D9', 'sauna' => '#CDE9D9',
        'tech' => '#DDE3EA', 'ai' => '#DDE3EA', 'startup' => '#DDE3EA', 'product' => '#DDE3EA', 'found' => '#DDE3EA',
        'expat' => '#F6D8C7', 'social' => '#F6D8C7', 'lang' => '#F6D8C7', 'nomad' => '#F6D8C7',
        'potter' => '#EAD7C7', 'ceramic' => '#EAD7C7', 'clay' => '#EAD7C7', 'craft' => '#EAD7C7',
        'art' => '#E7D3E8', 'creat' => '#E7D3E8', 'draw' => '#E7D3E8', 'sketch' => '#E7D3E8',
        'book' => '#E9E2CF', 'writ' => '#E9E2CF', 'supper' => '#F3CFC6', 'dinner' => '#F3CFC6', 'food' => '#F3CFC6',
        'board' => '#D7E3D0', 'game' => '#D7E3D0', 'chess' => '#D7E3D0', 'dance' => '#F1D2DE', 'music' => '#F1D2DE'];
    $tile = '#FFEFC2';
    foreach ($tiles as $k => $col) {
        if (str_contains($vert, $k)) { $tile = $col; break; }
    }

    $appBase = rtrim(config('webapp.url', 'https://app.kolabing.com'), '/');
    $appUrl = $m['app_url'] ?? $appBase.'/register';
    $secondary = array_filter([
        'Luma' => $m['luma_url'] ?? null,
        'Meetup' => $m['meetup_url'] ?? null,
        'Eventbrite' => $m['eventbrite_url'] ?? null,
        'Instagram' => $igUrl,
    ]);

    $c = $counts[$a->id] ?? [];
    $vouches = (int) ($c['vouches'] ?? 0);
    $verifiedMembers = (int) ($c['verified_members'] ?? 0);
    $isVerified = ! empty($c['verified']);
@endphp

<li class="group flex gap-4 rounded-3xl border border-off-black/10 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-6">
    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-black {{ $i < 3 ? 'bg-primary text-off-black' : 'bg-off-black text-primary' }}">{{ $i + 1 }}</div>

    <div class="shrink-0">
        <div class="flex h-14 w-14 items-center justify-center rounded-2xl font-montserrat text-lg font-black text-off-black {{ $handle ? 'ring-2 ring-primary' : 'ring-1 ring-off-black/10' }}" style="background: {{ $tile }}">{{ $initials }}</div>
    </div>

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            <h3 class="font-montserrat text-lg font-bold leading-tight">{{ $a->name }}</h3>
            @if ($isVerified)
                <span title="A member of this community has verified it by email" class="inline-flex items-center gap-1 rounded-full bg-off-black px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-primary">✓ Verified</span>
            @endif
            @if ($handle)
                <a href="{{ $igUrl }}" target="_blank" rel="noopener nofollow" class="text-sm font-medium text-off-black/50 hover:text-off-black">{{ $handle }}</a>
            @endif
            @if (! empty($m['vertical']))
                <span class="rounded-full bg-off-black/5 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-off-black/50">{{ $m['vertical'] }}</span>
            @endif
        </div>

        @if (! empty($m['members']) || ! empty($m['cadence']) || ! empty($m['venue']))
            <div class="mt-2 flex flex-wrap items-center gap-2">
                @if (! empty($m['members']))
                    <span class="inline-flex items-center gap-1 rounded-full bg-primary/25 px-2.5 py-0.5 text-xs font-bold text-off-black"><span class="h-1.5 w-1.5 rounded-full bg-off-black"></span>{{ $m['members'] }}</span>
                @endif
                @if (! empty($m['cadence']))
                    <span class="rounded-full bg-off-black/5 px-2.5 py-0.5 text-xs font-semibold text-off-black/60">{{ $m['cadence'] }}</span>
                @endif
                @if (! empty($m['venue']))
                    <span class="rounded-full bg-off-black/5 px-2.5 py-0.5 text-xs font-semibold text-off-black/60">📍 {{ $m['venue'] }}</span>
                @endif
            </div>
        @endif

        @if ($blurb)
            <p class="mt-2.5 text-sm leading-relaxed text-off-black/70">{{ $blurb }}</p>
        @endif

        @if (! empty($m['collabs']))
            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-off-black/40">Works with</span>
                @foreach ($m['collabs'] as $brand)
                    <span class="rounded-md px-2 py-0.5 text-[11px] font-semibold text-off-black/70 ring-1 ring-off-black/15">{{ $brand }}</span>
                @endforeach
            </div>
        @endif

        {{-- CTA hierarchy: primary = find them on Kolabing (the app); secondary = their event/social links. --}}
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <a href="{{ $appUrl }}" class="inline-flex items-center gap-1.5 rounded-full bg-off-black px-4 py-1.5 text-xs font-bold text-primary transition hover:bg-off-black/90">Find them on Kolabing</a>
            @foreach ($secondary as $label => $url)
                <a href="{{ $url }}" target="_blank" rel="noopener nofollow" class="rounded-full border border-off-black/15 px-3 py-1.5 text-xs font-semibold text-off-black/60 transition hover:border-off-black hover:text-off-black">{{ $label }}</a>
            @endforeach
        </div>

        {{-- Honest social proof: real vouch count (0 shown honestly) + verified-member proof. --}}
        <div class="mt-3 flex flex-wrap items-center gap-4 border-t border-off-black/5 pt-3">
            <button type="button" data-vouch="{{ $a->id }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-off-black/60 hover:text-off-black">
                <span aria-hidden="true">👍</span> Vouch <span data-vouch-count class="tabular-nums">{{ $vouches }}</span>
            </button>
            @if ($verifiedMembers > 0)
                <span class="text-xs text-off-black/50" title="Members who verified this community by email">Verified by {{ $verifiedMembers }} {{ \Illuminate\Support\Str::plural('member', $verifiedMembers) }}</span>
            @endif
            <button type="button" data-claim="{{ $a->name }}" data-handle="{{ $handle }}" class="text-xs font-semibold text-off-black/50 underline decoration-off-black/20 underline-offset-4 hover:text-off-black">Is this you? Claim or correct it</button>
        </div>
    </div>
</li>
