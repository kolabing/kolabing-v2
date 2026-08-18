@php
    // App shell: 264px left sidebar (desktop) + top bar (mobile), per the design.
    // Usage: the page root carries x-data with kbShell() spread in, then:
    //   <div class="md:flex min-h-screen"> @include('webapp.partials.sidebar', ['active' => 'dashboard'])
    //   <main class="flex-1 min-w-0"> … </main> </div>
    $activeKey = $active ?? '';
    $items = [
        'dashboard'     => ['/dashboard', __('webapp.nav.home'), '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>'],
        'feed'          => ['/feed', __('webapp.nav.explore'), '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>'],
        'kolabs'        => ['/kolabs', __('webapp.nav.my_kolabs'), '<path d="m12 2-10 5 10 5 10-5z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/>'],
        'chats'         => ['/chats', __('webapp.nav.messages'), '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'],
        'notifications' => ['/notifications', __('webapp.nav.notifications'), '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>'],
        'account'       => ['/account', __('webapp.nav.profile'), '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
    ];
    $planIcon = '<rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/>';
@endphp

{{-- ── Mobile top bar ────────────────────────────────────────────────────── --}}
<header class="md:hidden sticky top-0 z-30 bg-cream/95 backdrop-blur border-b border-ink/10">
    <div class="px-5 h-14 flex items-center justify-between">
        <a href="{{ $base }}/dashboard" class="flex items-center">
            <img src="/webapp-assets/wordmark-light.png" alt="Kolabing" class="h-6 w-auto">
        </a>
        <div class="flex items-center gap-1">
            <a href="{{ $base }}/chats" class="relative w-10 h-10 flex items-center justify-center text-ink" aria-label="{{ __('webapp.nav.messages') }}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <span x-show="chatUnread > 0" x-cloak class="absolute top-2 right-2 w-2 h-2 rounded-full bg-accent ring-2 ring-cream"></span>
            </a>
            <a href="{{ $base }}/notifications" class="relative w-10 h-10 flex items-center justify-center text-ink" aria-label="{{ __('webapp.nav.notifications') }}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <span x-show="unread > 0" x-cloak class="absolute top-2 right-2 w-2 h-2 rounded-full bg-accent ring-2 ring-cream"></span>
            </a>
            <button type="button" class="w-10 h-10 flex items-center justify-center text-ink" @click="menuOpen = !menuOpen" aria-label="{{ __('webapp.nav.menu') }}">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
    </div>
    <nav class="border-t border-ink/10 bg-cream px-5 py-3 flex flex-col gap-1 text-sm" x-show="menuOpen" x-cloak>
        <a href="{{ $base }}/kolabs/create" class="kb-on-yellow mb-2 h-11 rounded-pill bg-primary text-ink font-bold flex items-center justify-center gap-2 shadow-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            {{ __('webapp.nav.create') }}
        </a>
        @foreach ($items as $key => [$path, $label, $icon])
            <a href="{{ $base.$path }}" class="py-2.5 px-2 rounded-xl {{ $activeKey === $key ? 'bg-primary-tint font-bold' : 'text-body' }}">{{ $label }}</a>
        @endforeach
        <a href="{{ $base }}/subscription" x-show="isBusiness" x-cloak class="py-2.5 px-2 rounded-xl {{ $activeKey === 'subscription' ? 'bg-primary-tint font-bold' : 'text-body' }}">{{ __('webapp.nav.plan') }}</a>
        <div class="flex items-center gap-3 pt-3 mt-1 border-t border-ink/10">
            @foreach ($localePaths as $l => $href)
                <a href="{{ $href }}" class="text-xs {{ $l === $loc ? 'font-bold text-ink' : 'text-muted' }}">{{ strtoupper($l) }}</a>
            @endforeach
            <button type="button" @click="toggleTheme()" class="text-body flex items-center gap-1.5"
                    :aria-label="isDark ? '{{ __('webapp.nav.theme_light') }}' : '{{ __('webapp.nav.theme_dark') }}'">
                <template x-if="!isDark">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                </template>
                <template x-if="isDark">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                </template>
            </button>
            <button type="button" @click="window.kb.logout()" class="ml-auto text-body font-semibold">{{ __('webapp.nav.logout') }}</button>
        </div>
    </nav>
</header>

{{-- ── Desktop sidebar ───────────────────────────────────────────────────── --}}
<aside class="hidden md:flex w-[264px] shrink-0 sticky top-0 h-screen overflow-y-auto kb-scroll flex-col bg-white border-r border-ink/[.08] px-4 pt-6 pb-5">
    <a href="{{ $base }}/dashboard" class="shrink-0 mx-2 mb-6">
        <img src="/webapp-assets/wordmark-light.png" alt="Kolabing" class="w-[138px]">
    </a>

    <a href="{{ $base }}/kolabs/create"
       class="kb-on-yellow shrink-0 mb-5 flex items-center justify-center gap-2 h-12 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark hover:-translate-y-px transition">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
        {{ __('webapp.nav.create') }}
    </a>

    <nav class="shrink-0 flex flex-col gap-0.5">
        @foreach ($items as $key => [$path, $label, $icon])
            <a href="{{ $base.$path }}"
               class="flex items-center gap-3 px-3 py-[11px] rounded-xl text-sm transition {{ $activeKey === $key ? 'bg-primary-tint font-bold text-ink' : 'font-medium text-body hover:bg-cream-low' }}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $icon !!}</svg>
                {{ $label }}
                @if ($key === 'notifications')
                    <span class="ml-auto min-w-[20px] h-5 px-1.5 rounded-pill bg-inverse text-on-inverse text-[11px] font-bold flex items-center justify-center"
                          x-show="unread > 0" x-text="unread" x-cloak></span>
                @elseif ($key === 'chats')
                    <span class="ml-auto min-w-[20px] h-5 px-1.5 rounded-pill bg-inverse text-on-inverse text-[11px] font-bold flex items-center justify-center"
                          x-show="chatUnread > 0" x-text="chatUnread" x-cloak></span>
                @endif
            </a>
        @endforeach
        <a href="{{ $base }}/subscription" x-show="isBusiness" x-cloak
           class="flex items-center gap-3 px-3 py-[11px] rounded-xl text-sm transition {{ $activeKey === 'subscription' ? 'bg-primary-tint font-bold text-ink' : 'font-medium text-body hover:bg-cream-low' }}">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $planIcon !!}</svg>
            {{ __('webapp.nav.plan') }}
        </a>
    </nav>

    <div class="mt-auto pt-5 shrink-0 flex flex-col gap-3.5">
        {{-- Appearance: the same pill group as the language switcher. --}}
        <div class="flex flex-col gap-1.5">
            <div class="text-[10px] font-semibold tracking-[.16em] uppercase text-muted px-1">{{ __('webapp.nav.appearance') }}</div>
            <div class="flex p-1 bg-white border border-ink/[.12] rounded-pill">
                <button type="button" @click="setTheme('light')"
                        class="flex-1 h-8 rounded-pill text-xs font-bold tracking-wide flex items-center justify-center gap-1.5 transition"
                        :class="!isDark ? 'kb-on-yellow bg-primary text-ink' : 'text-muted hover:text-ink'"
                        :aria-pressed="!isDark">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                    {{ __('webapp.nav.theme_light') }}
                </button>
                <button type="button" @click="setTheme('dark')"
                        class="flex-1 h-8 rounded-pill text-xs font-bold tracking-wide flex items-center justify-center gap-1.5 transition"
                        :class="isDark ? 'kb-on-yellow bg-primary text-ink' : 'text-muted hover:text-ink'"
                        :aria-pressed="isDark">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                    {{ __('webapp.nav.theme_dark') }}
                </button>
            </div>
        </div>

        <div class="flex flex-col gap-1.5">
            <div class="text-[10px] font-semibold tracking-[.16em] uppercase text-muted px-1">{{ __('webapp.nav.language') }}</div>
            <div class="flex p-1 bg-white border border-ink/[.12] rounded-pill">
                @foreach ($localePaths as $l => $href)
                    <a href="{{ $href }}"
                       class="kb-on-yellow flex-1 h-8 rounded-pill text-xs font-bold tracking-wide flex items-center justify-center {{ $l === $loc ? 'kb-on-yellow bg-primary text-ink' : 'text-muted hover:text-ink' }}">{{ strtoupper($l) }}</a>
                @endforeach
            </div>
        </div>

        <div class="flex items-center gap-2.5 px-3 py-2.5 rounded-2xl bg-cream-low">
            <template x-if="avatarUrl">
                <img :src="avatarUrl" alt="" class="w-9 h-9 rounded-full object-cover bg-primary/30 shrink-0">
            </template>
            <template x-if="!avatarUrl">
                <div class="w-9 h-9 rounded-full bg-primary/50 flex items-center justify-center text-sm font-semibold shrink-0" x-text="initial">&nbsp;</div>
            </template>
            <div class="flex-1 min-w-0">
                <p class="text-[13px] font-semibold truncate" x-text="displayName">&nbsp;</p>
                <p class="text-[11px] text-muted" x-text="roleLabel">&nbsp;</p>
            </div>
            <button type="button" @click="window.kb.logout()" title="{{ __('webapp.nav.logout') }}" class="text-muted hover:text-ink shrink-0">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
            </button>
        </div>
    </div>
</aside>

@include('webapp.partials.billing-banner')
