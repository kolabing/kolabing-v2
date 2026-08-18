{{-- The shareable "#N featured" badge — the supply-side referral loop.
     Vars: $city (string), $topicLabel (string), $topName (string|null). --}}
<div class="mt-12 rounded-[2rem] bg-off-black p-8 text-white md:flex md:items-center md:gap-10">
    <div class="md:flex-1">
        <p class="text-xs font-bold uppercase tracking-[0.24em] text-primary">Featured on Kolabing</p>
        <h2 class="mt-3 font-montserrat text-2xl font-black uppercase leading-tight">Ranked communities get a badge to show it.</h2>
        <p class="mt-3 max-w-xl text-white/70">Every listed community can embed its rank on its own site and socials. It links back here and tells members they are part of {{ $city }}'s best. Free, and yours the moment you claim your listing.</p>
    </div>
    <div class="mt-6 shrink-0 md:mt-0">
        <div class="inline-flex items-center gap-3 rounded-2xl bg-white/10 p-4 ring-1 ring-white/15">
            <span class="font-montserrat text-3xl font-black text-primary">#1</span>
            <span class="text-left">
                <span class="block text-[10px] font-bold uppercase tracking-widest text-white/50">Kolabing · 2026</span>
                <span class="block text-sm font-bold">{{ $topicLabel }} in {{ $city }}</span>
            </span>
        </div>
        @if (! empty($topName))
            <p class="mt-2 text-xs text-white/50">e.g. how {{ $topName }}'s badge reads</p>
        @endif
    </div>
</div>
