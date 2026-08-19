{{--
    Community Hub header: the community switcher + the tab strip.

    Usage: the page root spreads kbShell() into its x-data, then
      @include('webapp.partials.community-nav', ['communityActive' => 'members'])

    The switcher only appears when the viewer manages more than one community
    (owning two is beyond the free cap, but a can_manage grant can span other
    people's communities).
--}}
@php
    $tabs = [
        'overview'    => ['/community', __('webapp.community.tabs.overview')],
        'members'     => ['/community/members', __('webapp.community.tabs.members')],
        'requests'    => ['/community/requests', __('webapp.community.tabs.requests')],
        'tiers'       => ['/community/tiers', __('webapp.community.tabs.tiers')],
        'economy'     => ['/community/economy', __('webapp.community.tabs.economy')],
        'leaderboard' => ['/community/leaderboard', __('webapp.community.tabs.leaderboard')],
        'settings'    => ['/community/settings', __('webapp.community.tabs.settings')],
    ];
    $current = $communityActive ?? 'overview';
@endphp

<div class="flex flex-wrap items-center gap-3 justify-between">
    <h1 class="font-anton text-[28px] tracking-[1px] text-ink" x-text="activeCommunity?.name || '{{ __('webapp.community.title') }}'">&nbsp;</h1>

    <div x-show="communities.length > 1" x-cloak>
        <label class="sr-only" for="kb-community-switcher">{{ __('webapp.community.switch') }}</label>
        <select id="kb-community-switcher"
                @change="setActiveCommunity($event.target.value)"
                class="h-10 px-3 rounded-pill bg-white border border-ink/[.12] text-sm font-semibold">
            <template x-for="c in communities" :key="c.id">
                <option :value="c.id" :selected="activeCommunity?.id === c.id" x-text="c.name"></option>
            </template>
        </select>
    </div>
</div>

<div class="flex p-1 bg-white border border-ink/[.12] rounded-pill shadow-card mt-[18px] overflow-x-auto kb-scroll">
    @foreach ($tabs as $key => [$path, $label])
        <a href="{{ $base.$path }}"
           class="flex-1 min-w-[92px] h-[34px] rounded-pill text-[12.5px] font-bold tracking-[.4px] transition whitespace-nowrap flex items-center justify-center gap-1.5 {{ $current === $key ? 'bg-ink text-white' : 'text-muted hover:text-ink' }}">
            {{ $label }}
            @if ($key === 'requests')
                <span x-show="communityPending > 0" x-cloak
                      class="min-w-[18px] h-[18px] px-1 rounded-pill text-[10px] font-bold flex items-center justify-center {{ $current === $key ? 'bg-primary text-ink' : 'bg-ink text-primary' }}"
                      x-text="communityPending"></span>
            @endif
        </a>
    @endforeach
</div>

{{-- Nothing to manage: the account owns no community and holds no can_manage grant. --}}
<template x-if="shellReady && !canManageCommunity">
    <div class="mt-8 bg-white border border-ink/[.08] rounded-2xl p-8 text-center shadow-card">
        <p class="font-bold text-ink">{{ __('webapp.community.none_title') }}</p>
        <p class="mt-2 text-sm text-muted">{{ __('webapp.community.none_body') }}</p>
    </div>
</template>
