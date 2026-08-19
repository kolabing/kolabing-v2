{{-- The ranked list (joiner-first hero). $ranked = Collection<CrmAccount>. --}}
<ol class="mt-8 space-y-4">
    @foreach ($ranked as $i => $a)
        @php
            $m = $a->metrics ?? [];
            $handle = $m['handle'] ?? ($a->instagram_handle ?? null);
            $blurb = $m['blurb'] ?? trim(implode(' · ', array_filter([$m['audience'] ?? null, $m['cadence'] ?? null, $m['collab'] ?? null])));
            $igUrl = $handle ? 'https://instagram.com/'.ltrim($handle, '@') : null;
        @endphp
        <li class="flex gap-4 rounded-2xl border border-off-black/10 bg-white p-5">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-off-black text-sm font-black text-primary">{{ $i + 1 }}</div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-montserrat text-lg font-bold leading-tight">{{ $a->name }}</h3>
                    @if ($handle)
                        <a href="{{ $igUrl }}" target="_blank" rel="noopener nofollow" class="text-sm font-medium text-off-black/50 hover:text-off-black">{{ $handle }}</a>
                    @endif
                    @if (! empty($m['vertical']))
                        <span class="rounded-full bg-off-black/5 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-off-black/50">{{ $m['vertical'] }}</span>
                    @endif
                </div>
                @if ($blurb)
                    <p class="mt-1.5 text-sm leading-relaxed text-off-black/70">{{ $blurb }}</p>
                @endif
                <button type="button" data-claim="{{ $a->name }}" data-handle="{{ $handle }}"
                        class="mt-2 text-xs font-semibold text-off-black/50 underline decoration-off-black/20 underline-offset-4 hover:text-off-black">Is this you? Claim or correct it</button>
            </div>
        </li>
    @endforeach
</ol>
