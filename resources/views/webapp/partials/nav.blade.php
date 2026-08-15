@php
    // $base, $loc, $localePaths come from the webapp View composer.
    $localeLabels = ['en' => 'EN', 'es' => 'ES', 'ca' => 'CA'];
    $links = [
        'dashboard' => ['/dashboard', __('webapp.nav.home')],
        'feed' => ['/feed', __('webapp.nav.feed')],
        'kolabs' => ['/kolabs', __('webapp.nav.kolabs')],
        'applications' => ['/applications', __('webapp.nav.applications')],
        'subscription' => ['/subscription', __('webapp.nav.plan')],
        'account' => ['/account', __('webapp.nav.account')],
    ];
    $activeKey = $active ?? '';
@endphp
<header class="border-b border-off-black/10 bg-off-white/90 backdrop-blur sticky top-0 z-20" x-data="{ open: false }">
    <div class="max-w-4xl mx-auto px-5 h-14 flex items-center justify-between">
        <a href="{{ $base }}/dashboard" class="font-montserrat font-black text-lg tracking-tight">Kolabing</a>

        {{-- Desktop nav --}}
        <nav class="hidden md:flex items-center gap-4 text-sm">
            @foreach ($links as $key => [$path, $label])
                <a href="{{ $base.$path }}" class="{{ $activeKey === $key ? 'font-semibold' : 'text-off-black/60 hover:text-off-black' }}">{{ $label }}</a>
            @endforeach
            <button type="button" @click="window.kb.logout()" class="text-off-black/60 hover:text-off-black">{{ __('webapp.nav.logout') }}</button>
            <span class="flex items-center gap-1.5 pl-2 border-l border-off-black/10">
                @foreach ($localePaths as $l => $href)
                    <a href="{{ $href }}" class="{{ $l === $loc ? 'font-bold text-off-black' : 'text-off-black/40 hover:text-off-black' }}">{{ $localeLabels[$l] ?? strtoupper($l) }}</a>
                @endforeach
            </span>
        </nav>

        {{-- Mobile toggle --}}
        <button type="button" class="md:hidden text-off-black/70" @click="open = !open" aria-label="{{ __('webapp.nav.menu') }}">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>

    {{-- Mobile menu --}}
    <div class="md:hidden border-t border-off-black/10 bg-off-white" x-show="open" x-cloak style="display:none">
        <nav class="max-w-4xl mx-auto px-5 py-3 flex flex-col gap-3 text-sm">
            @foreach ($links as $key => [$path, $label])
                <a href="{{ $base.$path }}" class="{{ $activeKey === $key ? 'font-semibold' : 'text-off-black/70' }}">{{ $label }}</a>
            @endforeach
            <button type="button" @click="window.kb.logout()" class="text-left text-off-black/70">{{ __('webapp.nav.logout') }}</button>
            <div class="flex items-center gap-3 pt-2 border-t border-off-black/10">
                @foreach ($localePaths as $l => $href)
                    <a href="{{ $href }}" class="{{ $l === $loc ? 'font-bold' : 'text-off-black/40' }}">{{ $localeLabels[$l] ?? strtoupper($l) }}</a>
                @endforeach
            </div>
        </nav>
    </div>
</header>
