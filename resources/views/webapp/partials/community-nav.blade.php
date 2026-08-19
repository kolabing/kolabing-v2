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

{{-- The tabs are meaningless until a community exists — every one would be empty. --}}
<div x-show="canManageCommunity" x-cloak
     class="flex p-1 bg-white border border-ink/[.12] rounded-pill shadow-card mt-[18px] overflow-x-auto kb-scroll">
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

{{--
    No community yet.

    A community user lands here to CREATE one — this is the only web path to
    becoming a leader, so it must be a real form, not a dead end. Anyone else
    (an attendee who lost their can_manage grant) gets the plain explanation.
--}}
<template x-if="shellReady && !canManageCommunity && isCommunity">
    <div class="mt-8 max-w-[520px] mx-auto bg-white border border-ink/[.08] rounded-2xl p-8 shadow-card">
        <p class="font-anton text-[21px] tracking-[.5px] text-ink">{{ __('webapp.community.create.title') }}</p>
        <p class="mt-2 text-sm text-muted">{{ __('webapp.community.create.body') }}</p>

        <label class="block mt-5 text-[12px] font-bold text-body" for="kb-new-community-name">{{ __('webapp.community.create.name_label') }}</label>
        <input id="kb-new-community-name" type="text" x-model="newCommunityName" maxlength="100"
               @keydown.enter="createCommunity()"
               placeholder="{{ __('webapp.community.create.name_placeholder') }}"
               class="mt-1.5 w-full h-11 px-4 rounded-2xl bg-white border border-ink/[.12] text-sm">

        <template x-if="createCommunityError">
            <div class="mt-4 rounded-2xl bg-bad-surface text-bad-ink text-sm px-4 py-3" x-text="createCommunityError"></div>
        </template>

        <button type="button" @click="createCommunity()" :disabled="creatingCommunity || newCommunityName.trim().length < 2"
                class="mt-5 h-11 px-6 rounded-pill bg-primary text-ink text-sm font-bold shadow-btn hover:bg-primary-dark transition disabled:opacity-50"
                x-text="creatingCommunity ? '{{ __('webapp.common.saving') }}' : '{{ __('webapp.community.create.submit') }}'"></button>

        <p class="mt-4 text-[11px] text-muted">{{ __('webapp.community.create.after') }}</p>
    </div>
</template>

{{-- Not a community account and no can_manage grant — nothing to manage. --}}
<template x-if="shellReady && !canManageCommunity && !isCommunity">
    <div class="mt-8 bg-white border border-ink/[.08] rounded-2xl p-8 text-center shadow-card">
        <p class="font-bold text-ink">{{ __('webapp.community.none_title') }}</p>
        <p class="mt-2 text-sm text-muted">{{ __('webapp.community.none_body') }}</p>
    </div>
</template>
