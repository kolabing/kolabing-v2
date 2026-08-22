@php
    // New app shell: left sidebar (desktop) + top bar (mobile).
    // Usage: wrap the page in `md:flex min-h-screen`, include this partial,
    // then render <main class="flex-1 min-w-0">…</main>.
    $activeKey = $active ?? '';
    $items = [
        'dashboard'     => ['/dashboard', 'Home', 'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z M9 22V12h6v10'],
        'feed'          => ['/feed', 'Explore', 'M21 21l-4.35-4.35 M11 3a8 8 0 1 1 0 16 8 8 0 0 1 0-16z'],
        'kolabs'        => ['/kolabs', 'My Kolabs', 'M12 2 2 7l10 5 10-5-10-5z M2 17l10 5 10-5 M2 12l10 5 10-5'],
        'notifications' => ['/notifications', 'Notifications', 'M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9 M13.73 21a2 2 0 0 1-3.46 0'],
        'account'       => ['/account', 'Profile', 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2 M12 3a4 4 0 1 1 0 8 4 4 0 0 1 0-8z'],
    ];
@endphp
<div x-data="kbSidebar()" x-init="init()" class="contents">

{{-- Mobile top bar --}}
<header class="md:hidden sticky top-0 z-30 bg-cream/90 backdrop-blur border-b border-ink/10">
    <div class="px-5 h-14 flex items-center justify-between">
        <a href="{{ $base }}/dashboard"><img src="/webapp-assets/wordmark-light.png" alt="Kolabing" class="h-7" onerror="this.replaceWith(Object.assign(document.createElement('span'),{textContent:'KOLABING',className:'font-anton text-lg'}))"></a>
        <button type="button" class="text-ink/70 p-2" @click="open = !open" aria-label="Menu">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>
    <nav class="border-t border-ink/10 bg-cream px-5 py-3 flex flex-col gap-1 text-sm" x-show="open" x-cloak>
        @foreach ($items as $key => [$path, $label, $d])
            <a href="{{ $base.$path }}" class="py-2 {{ $activeKey === $key ? 'font-bold' : 'text-ink/70' }}">{{ $label }}</a>
        @endforeach
        <a href="{{ $base }}/subscription" class="py-2 {{ $activeKey === 'subscription' ? 'font-bold' : 'text-ink/70' }}" x-show="isBusiness" x-cloak>Plan</a>
        <a href="{{ $base }}/kolabs/create" class="mt-1 rounded-pill bg-primary text-ink font-bold text-center py-2.5 shadow-btn">+ Create a Kolab</a>
        <button type="button" @click="window.kb.logout()" class="py-2 text-left text-ink/70">Log out</button>
    </nav>
</header>

{{-- Desktop sidebar --}}
<aside class="hidden md:flex w-[264px] shrink-0 sticky top-0 h-screen overflow-y-auto flex-col bg-white border-r border-ink/10 px-4 pt-6 pb-5">
    <a href="{{ $base }}/dashboard" class="shrink-0 mx-2 mb-6">
        <img src="/webapp-assets/wordmark-light.png" alt="Kolabing" class="w-[138px]" onerror="this.replaceWith(Object.assign(document.createElement('span'),{textContent:'KOLABING',className:'font-anton text-xl'}))">
    </a>
    <a href="{{ $base }}/kolabs/create"
       class="shrink-0 mb-5 flex items-center justify-center gap-2 h-12 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark hover:-translate-y-px transition">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        Create a Kolab
    </a>
    <nav class="shrink-0 flex flex-col gap-0.5">
        @foreach ($items as $key => [$path, $label, $d])
            <a href="{{ $base.$path }}"
               class="flex items-center gap-3 px-3 py-[11px] rounded-xl text-sm transition {{ $activeKey === $key ? 'bg-primary-tint font-bold text-ink' : 'font-medium text-body hover:bg-cream-low' }}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $d }}"/></svg>
                {{ $label }}
                @if ($key === 'notifications')
                    <span class="ml-auto min-w-[20px] h-5 px-1.5 rounded-pill bg-ink text-primary text-[11px] font-bold flex items-center justify-center"
                          x-show="unread > 0" x-text="unread" x-cloak></span>
                @endif
            </a>
        @endforeach
        <a href="{{ $base }}/subscription" x-show="isBusiness" x-cloak
           class="flex items-center gap-3 px-3 py-[11px] rounded-xl text-sm transition {{ $activeKey === 'subscription' ? 'bg-primary-tint font-bold text-ink' : 'font-medium text-body hover:bg-cream-low' }}">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg>
            Plan
        </a>
    </nav>
    <div class="mt-auto pt-5 shrink-0">
        <div class="flex items-center gap-2.5 px-3 py-2.5 rounded-2xl bg-cream-low">
            <template x-if="avatarUrl"><img :src="avatarUrl" alt="" class="w-9 h-9 rounded-full object-cover bg-primary/30"></template>
            <template x-if="!avatarUrl">
                <div class="w-9 h-9 rounded-full bg-primary/50 flex items-center justify-center text-sm font-semibold" x-text="initial"></div>
            </template>
            <div class="flex-1 min-w-0">
                <p class="text-[13px] font-semibold truncate" x-text="displayName"></p>
                <p class="text-[11px] text-muted" x-text="isBusiness ? 'Business' : 'Community'"></p>
            </div>
            <button type="button" @click="window.kb.logout()" title="Log out" class="text-muted hover:text-ink">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
            </button>
        </div>
        <div class="flex items-center gap-2 justify-center mt-3 text-xs">
            @foreach ($localePaths as $l => $href)
                <a href="{{ $href }}" class="{{ $l === $loc ? 'font-bold text-ink' : 'text-muted hover:text-ink' }}">{{ strtoupper($l) }}</a>
            @endforeach
        </div>
    </div>
</aside>
</div>
