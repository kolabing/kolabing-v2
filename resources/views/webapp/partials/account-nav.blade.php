{{--
    Profile section header + tab strip.

    Usage: the page root spreads kbShell() into its x-data, then
      @include('webapp.partials.account-nav', ['accountActive' => 'gallery'])

    No gate: every authenticated profile reaches every tab. The portfolio tabs
    are meaningful for business and community accounts, which are the only types
    whose public profile renders a portfolio.
--}}
@php
    $tabs = [
        'details' => ['/account', __('webapp.account.tabs.details')],
        'gallery' => ['/account/gallery', __('webapp.account.tabs.gallery')],
        'events' => ['/account/events', __('webapp.account.tabs.events')],
        'preview' => ['/account/preview', __('webapp.account.tabs.preview')],
    ];
    $current = $accountActive ?? 'details';
@endphp

<h1 class="font-anton text-[28px] tracking-[1px] text-ink">{{ __('webapp.account.title') }}</h1>

<div class="flex p-1 bg-white border border-ink/[.12] rounded-pill shadow-card mt-[18px] overflow-x-auto kb-scroll">
    @foreach ($tabs as $key => [$path, $label])
        <a href="{{ $base.$path }}"
           class="flex-1 min-w-[104px] h-[34px] rounded-pill text-[12.5px] font-bold tracking-[.4px] transition whitespace-nowrap flex items-center justify-center {{ $current === $key ? 'bg-ink text-white' : 'text-muted hover:text-ink' }}">
            {{ $label }}
        </a>
    @endforeach
</div>
