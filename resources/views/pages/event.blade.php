@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;
    use App\Support\PublicEventLink;

    /**
     * A single public event, readable by anyone.
     *
     * Details are open — someone deciding whether to turn up on a Sunday morning
     * needs to know what it is, where, and who runs it. The wall is at the action:
     * "I'm going" hands off to the panel with ?rsvp=1, because the token that proves
     * who you are lives in the app host's storage and this page cannot read it.
     */
    $starts = $event->starts_at ?? ($event->event_date ? Carbon::parse($event->event_date) : null);
    $ends = $event->ends_at;
    $cover = $event->photos->first();
    $rsvpUrl = $appUrl.'/events/'.$event->id.'?rsvp=1';

    $whenLabel = $starts
        ? $starts->translatedFormat('l j F').($event->starts_at ? ' · '.$starts->format('H:i') : '')
        : null;

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $event->name,
        'url' => $canonicalUrl,
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'eventStatus' => 'https://schema.org/EventScheduled',
        // Free to attend is the product rule, not a guess: attendees never pay.
        'isAccessibleForFree' => true,
        'offers' => [
            '@type' => 'Offer',
            'price' => '0',
            'priceCurrency' => 'EUR',
            'availability' => 'https://schema.org/InStock',
            'url' => $canonicalUrl,
        ],
    ];
    if ($starts) {
        $schema['startDate'] = $starts->toIso8601String();
    }
    if ($ends) {
        $schema['endDate'] = $ends->toIso8601String();
    }
    if ($event->community) {
        $schema['organizer'] = ['@type' => 'Organization', 'name' => $event->community->name];
    }
    if ($event->location || $event->city) {
        $schema['location'] = array_filter([
            '@type' => 'Place',
            'name' => $event->location ?: $event->city?->name,
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $event->address ?: null,
                'addressLocality' => $event->city?->name,
            ]),
        ]);
    }
    if ($cover) {
        $schema['image'] = $cover->url;
    }

    $description = Str::limit(trim(sprintf(
        '%s%s%s. Hosted by %s. Free to join on Kolabing.',
        $event->name,
        $whenLabel ? ' — '.$whenLabel : '',
        $event->city ? ' in '.$event->city->name : '',
        $event->community?->name ?? 'a local community'
    )), 155);
@endphp

<x-layouts.marketing-page
    :title="$event->name"
    :description="$description"
    :canonical="$canonicalUrl"
    :image="$cover?->url"
    og-type="article"
>
    <x-slot:head>
        <script type="application/ld+json">
            {!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    </x-slot:head>

    <article class="mx-auto max-w-3xl px-6 py-14 sm:py-20">

        <a href="{{ url('/events') }}" class="text-sm font-semibold text-off-black/50 hover:text-off-black">← All events</a>

        @if ($cover)
            <img src="{{ $cover->url }}" alt="{{ $event->name }}" width="1200" height="675" loading="lazy"
                 class="mt-6 aspect-[16/9] w-full rounded-3xl object-cover">
        @endif

        <header class="mt-8">
            @if ($whenLabel)
                <p class="text-sm font-bold uppercase tracking-[0.12em] text-primary-dark">{{ $whenLabel }}</p>
            @endif
            <h1 class="mt-2 font-display text-3xl font-black leading-tight text-off-black sm:text-4xl">{{ $event->name }}</h1>
            <p class="mt-3 text-off-black/70">
                Hosted by <strong class="font-bold text-off-black">{{ $event->community?->name ?? 'a local community' }}</strong>
                @if ($event->city) · {{ $event->city->name }}@endif
            </p>
        </header>

        <dl class="mt-8 grid gap-3 sm:grid-cols-2">
            @if ($whenLabel)
                <div class="rounded-2xl border border-off-black/10 p-4">
                    <dt class="text-[11px] font-bold uppercase tracking-[0.12em] text-off-black/45">When</dt>
                    <dd class="mt-1 font-semibold text-off-black">
                        {{ $whenLabel }}@if ($ends) – {{ $ends->format('H:i') }}@endif
                    </dd>
                </div>
            @endif
            @if ($event->location || $event->address)
                <div class="rounded-2xl border border-off-black/10 p-4">
                    <dt class="text-[11px] font-bold uppercase tracking-[0.12em] text-off-black/45">Where</dt>
                    <dd class="mt-1 font-semibold text-off-black">{{ $event->location ?: $event->address }}</dd>
                </div>
            @endif
            @if ($event->capacity)
                <div class="rounded-2xl border border-off-black/10 p-4">
                    <dt class="text-[11px] font-bold uppercase tracking-[0.12em] text-off-black/45">Spaces</dt>
                    <dd class="mt-1 font-semibold text-off-black">{{ $event->capacity }} places</dd>
                </div>
            @endif
            <div class="rounded-2xl border border-off-black/10 p-4">
                <dt class="text-[11px] font-bold uppercase tracking-[0.12em] text-off-black/45">Price</dt>
                <dd class="mt-1 font-semibold text-off-black">Free</dd>
            </div>
        </dl>

        {{-- The wall: reading is open, joining needs an account. --}}
        <section class="mt-8 rounded-[2rem] bg-off-black p-8 text-white">
            <h2 class="font-display text-2xl font-black leading-tight">Going?</h2>
            <p class="mt-2 max-w-lg text-white/80">
                Save your place, get the details, and check in with a QR at the door. Free — attendees never pay on Kolabing.
            </p>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                <a href="{{ $rsvpUrl }}"
                   class="inline-flex items-center justify-center rounded-full bg-primary px-6 py-3 font-bold text-off-black transition hover:brightness-95">
                    I'm going
                </a>
                <span class="text-sm text-white/60">Takes a minute · no app needed</span>
            </div>
        </section>

        @if ($event->photos->count() > 1)
            <section class="mt-12">
                <h2 class="font-display text-lg font-black text-off-black">Photos</h2>
                <div class="mt-3 grid grid-cols-3 gap-2">
                    @foreach ($event->photos->skip(1)->take(6) as $photo)
                        <img src="{{ $photo->url }}" alt="{{ $event->name }}" width="400" height="400" loading="lazy"
                             class="aspect-square w-full rounded-xl object-cover">
                    @endforeach
                </div>
            </section>
        @endif

        @if ($alsoOn->isNotEmpty())
            <section class="mt-12">
                <h2 class="font-display text-lg font-black text-off-black">Also coming up{{ $event->city ? ' in '.$event->city->name : '' }}</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    @foreach ($alsoOn as $other)
                        @php $otherWhen = $other->starts_at ?? ($other->event_date ? Carbon::parse($other->event_date) : null); @endphp
                        <a href="{{ PublicEventLink::urlFor($other) }}"
                           class="rounded-2xl border border-off-black/10 p-4 transition hover:border-off-black/30">
                            @if ($otherWhen)
                                <p class="text-[11px] font-bold uppercase tracking-[0.1em] text-primary-dark">{{ $otherWhen->translatedFormat('D j M') }}</p>
                            @endif
                            <p class="mt-1 font-bold leading-snug text-off-black">{{ $other->name }}</p>
                            <p class="mt-1 text-sm text-off-black/55">{{ $other->community?->name }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </article>
</x-layouts.marketing-page>
