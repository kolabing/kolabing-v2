@php
    /**
     * What's on — the attendee's front door, open to anyone.
     *
     * Only `EventVisibility::Public` events reach this page; members-only and
     * tier-gated events are filtered out in EventDiscoveryService and are not
     * resolvable by URL either. Details are open and the wall sits at the action:
     * signing up hands off to the panel, because the token that proves who you are
     * lives in the app host's storage and this page cannot see it.
     */
    $count = $events->total();
    $cityName = $selectedCity !== '' ? optional($cities->firstWhere('id', $selectedCity))->name : null;

    $title = $cityName ? 'Events in '.$cityName : 'What\'s on near you';
    $description = $cityName
        ? 'Community events happening in '.$cityName.' — run clubs, supper clubs, meetups and more. Free to browse, free to join.'
        : 'Community events you can actually turn up to: run clubs, supper clubs, meetups and workshops hosted by local communities. Free to browse, free to join.';
@endphp

<x-layouts.marketing-page :title="$title" :description="$description" :canonical="url('/events')">
    <article class="mx-auto max-w-5xl px-6 py-14 sm:py-20">

        <header class="max-w-2xl">
            <p class="font-montserrat text-sm font-bold uppercase tracking-widest text-off-black/50">Community events</p>
            <h1 class="mt-3 font-display text-4xl font-black leading-tight text-off-black sm:text-5xl">
                {{ $cityName ? 'What\'s on in '.$cityName : 'What\'s on near you' }}
            </h1>
            <p class="mt-4 text-lg leading-relaxed text-off-black/70">
                Run clubs, supper clubs, meetups, workshops — hosted by real communities, open to anyone.
                Browsing is free and so is going.
            </p>
        </header>

        {{-- City filter. Only cities with something on, so no link leads nowhere. --}}
        @if ($cities->isNotEmpty())
            <nav class="mt-8 flex flex-wrap gap-2" aria-label="Filter by city">
                <a href="{{ url('/events') }}"
                   class="rounded-full px-4 py-2 text-sm font-semibold transition {{ $selectedCity === '' ? 'bg-off-black text-white' : 'border border-off-black/15 text-off-black/70 hover:border-off-black' }}">
                    All cities
                </a>
                @foreach ($cities as $city)
                    <a href="{{ url('/events?city='.$city->id) }}"
                       class="rounded-full px-4 py-2 text-sm font-semibold transition {{ $selectedCity === $city->id ? 'bg-off-black text-white' : 'border border-off-black/15 text-off-black/70 hover:border-off-black' }}">
                        {{ $city->name }}
                    </a>
                @endforeach
            </nav>
        @endif

        @if ($count === 0)
            <div class="mt-12 rounded-3xl border-2 border-dashed border-off-black/15 px-8 py-16 text-center">
                <p class="font-display text-xl font-black text-off-black">Nothing listed here yet</p>
                <p class="mx-auto mt-2 max-w-md text-off-black/60">
                    New events appear as communities publish them. If you run one, you can list it in a minute.
                </p>
                <a href="{{ $appUrl }}/register?type=community"
                   class="mt-6 inline-flex items-center justify-center rounded-full bg-primary px-6 py-3 font-bold text-off-black transition hover:brightness-95">
                    List your community's events
                </a>
            </div>
        @else
            <p class="mt-8 text-sm font-semibold text-off-black/50">
                {{ $count }} {{ $count === 1 ? 'event' : 'events' }} coming up
            </p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($events as $event)
                    @php
                        $when = $event->starts_at ?? ($event->event_date ? \Illuminate\Support\Carbon::parse($event->event_date) : null);
                        $cover = $event->photos->first();
                    @endphp
                    <a href="{{ \App\Support\PublicEventLink::urlFor($event) }}"
                       class="group flex flex-col overflow-hidden rounded-3xl border border-off-black/10 bg-white transition hover:border-off-black/25">
                        @if ($cover)
                            <img src="{{ $cover->url }}" alt="{{ $event->name }}" width="800" height="500" loading="lazy"
                                 class="aspect-[8/5] w-full object-cover">
                        @else
                            <div class="flex aspect-[8/5] w-full items-center justify-center bg-primary/25">
                                <span class="font-display text-3xl font-black text-off-black/25">{{ mb_strtoupper(mb_substr($event->name, 0, 1)) }}</span>
                            </div>
                        @endif

                        <div class="flex flex-1 flex-col p-5">
                            @if ($when)
                                <p class="text-xs font-bold uppercase tracking-[0.12em] text-primary-dark">
                                    {{ $when->translatedFormat('D j M') }}{{ $event->starts_at ? ' · '.$when->format('H:i') : '' }}
                                </p>
                            @endif
                            <h2 class="mt-1.5 font-display text-lg font-black leading-snug text-off-black">{{ $event->name }}</h2>
                            <p class="mt-1 text-sm text-off-black/60">
                                {{ $event->community?->name ?? 'A local community' }}@if ($event->city) · {{ $event->city->name }}@endif
                            </p>
                            @if ($event->location)
                                <p class="mt-2 text-sm text-off-black/50">{{ $event->location }}</p>
                            @endif
                            <span class="mt-auto pt-4 text-sm font-bold text-off-black group-hover:underline">See details →</span>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-10">{{ $events->links() }}</div>
        @endif

        <section class="mt-16 rounded-[2rem] bg-off-black p-8 text-white">
            <h2 class="font-display text-2xl font-black leading-tight">Run a community?</h2>
            <p class="mt-3 max-w-xl text-white/80">
                List your events here for free, manage your members and tiers, and check people in at the door with a QR.
                Communities never pay on Kolabing.
            </p>
            <a href="{{ $appUrl }}/register?type=community"
               class="mt-6 inline-flex items-center justify-center rounded-full bg-primary px-6 py-3 font-bold text-off-black transition hover:brightness-95">
                Get started free
            </a>
        </section>
    </article>
</x-layouts.marketing-page>
