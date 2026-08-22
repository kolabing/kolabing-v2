@php
    /**
     * One Kolab, open to anyone.
     *
     * The deal is public; the poster's identity is not (PublicKolabPoster explains why),
     * and applying needs an account. The apply CTA hands off to the panel with ?apply=1
     * so a visitor who registers lands back on the same Kolab ready to act — the same
     * pattern the public event page uses for ?rsvp=1.
     */
    use App\Enums\IntentType;
    use App\Support\OfferOptionLabels;
    use App\Support\PublicKolabPoster;
    use Illuminate\Support\Str;

    $poster = PublicKolabPoster::describe($kolab);

    $isCommunityAsk = $kolab->intent_type === IntentType::CommunitySeeking;

    $gives = $isCommunityAsk
        ? OfferOptionLabels::many('deliverable', $kolab->offers_in_return)
        : OfferOptionLabels::many('offering', $kolab->offering);

    $wants = $isCommunityAsk
        ? OfferOptionLabels::many('need', $kolab->needs)
        : OfferOptionLabels::many('deliverable', $kolab->expects);

    $kind = match ($kolab->intent_type) {
        IntentType::CommunitySeeking => 'Community looking for a partner',
        IntentType::VenuePromotion => 'Venue offering space',
        IntentType::ProductPromotion => 'Product looking for communities',
    };

    /*
     * Who this Kolab wants to hear from. These are community-type slugs, which live in
     * the `community_types` vocabulary rather than `offer_options`, so they are
     * headlined directly — §3.4's rule is only that the label must read "Run Club",
     * never "Run_Club".
     */
    $audience = collect($isCommunityAsk
            ? $kolab->community_types
            : data_get($kolab->seeking_communities, 'types'))
        ->filter(fn ($type) => is_string($type) && trim($type) !== '')
        ->map(fn (string $type) => Str::headline($type))
        ->unique()
        ->values()
        ->all();

    $robots = config('kolabing.public_kolabs.indexable') ? null : 'noindex,follow';

    $description = Str::limit(trim((string) ($kolab->description ?: $kolab->base_offer ?: $kolab->title)), 155);

    $applyUrl = $appUrl.'/kolabs/'.$kolab->id.'?apply=1';
@endphp

<x-layouts.marketing-page :title="$kolab->title" :description="$description" :canonical="$canonicalUrl" :robots="$robots">
    <article class="mx-auto max-w-3xl px-6 py-14 sm:py-20">

        <a href="{{ url('/kolabs') }}" class="text-sm font-bold text-off-black/60 hover:text-off-black">← All Kolabs</a>

        <header class="mt-6">
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-primary-dark">{{ $kind }}</p>
            <h1 class="mt-2 font-display text-3xl font-black leading-tight text-off-black sm:text-4xl">{{ $kolab->title }}</h1>
            <p class="mt-3 text-lg text-off-black/70">
                {{ $poster['description'] }}@if (! $poster['is_named']) · name shown to members @endif
            </p>
        </header>

        @if ($kolab->offer_headline)
            <p class="mt-6 font-display text-xl font-black leading-snug text-off-black">{{ $kolab->offer_headline }}</p>
        @endif

        @if ($kolab->description)
            <div class="mt-4 whitespace-pre-line text-lg leading-relaxed text-off-black/80">{{ $kolab->description }}</div>
        @endif

        <dl class="mt-10 grid gap-4 sm:grid-cols-2">
            @if ($gives !== [])
                <div class="rounded-3xl border border-off-black/10 bg-white p-5">
                    <dt class="text-xs font-bold uppercase tracking-[0.12em] text-off-black/50">What's on offer</dt>
                    <dd class="mt-2 text-off-black">{{ implode(' · ', $gives) }}</dd>
                </div>
            @endif

            @if ($wants !== [])
                <div class="rounded-3xl border border-off-black/10 bg-white p-5">
                    <dt class="text-xs font-bold uppercase tracking-[0.12em] text-off-black/50">Looking for</dt>
                    <dd class="mt-2 text-off-black">{{ implode(' · ', $wants) }}</dd>
                </div>
            @endif

            @if ($kolab->typical_attendance || $kolab->community_size)
                <div class="rounded-3xl border border-off-black/10 bg-white p-5">
                    <dt class="text-xs font-bold uppercase tracking-[0.12em] text-off-black/50">Reach</dt>
                    <dd class="mt-2 text-off-black">
                        @if ($kolab->typical_attendance)
                            {{ $kolab->typical_attendance }} people at a typical event
                        @else
                            {{ $kolab->community_size }} members
                        @endif
                    </dd>
                </div>
            @endif

            @if ($kolab->capacity)
                <div class="rounded-3xl border border-off-black/10 bg-white p-5">
                    <dt class="text-xs font-bold uppercase tracking-[0.12em] text-off-black/50">Space for</dt>
                    <dd class="mt-2 text-off-black">{{ $kolab->capacity }} people</dd>
                </div>
            @endif

            @if ($audience !== [])
                <div class="rounded-3xl border border-off-black/10 bg-white p-5">
                    <dt class="text-xs font-bold uppercase tracking-[0.12em] text-off-black/50">Best fit</dt>
                    <dd class="mt-2 text-off-black">{{ implode(' · ', $audience) }}</dd>
                </div>
            @endif

            @if ($kolab->availability_start || $kolab->availability_end)
                <div class="rounded-3xl border border-off-black/10 bg-white p-5">
                    <dt class="text-xs font-bold uppercase tracking-[0.12em] text-off-black/50">Dates</dt>
                    <dd class="mt-2 text-off-black">
                        @if ($kolab->availability_end)
                            Open until {{ $kolab->availability_end->translatedFormat('j F Y') }}
                        @else
                            From {{ $kolab->availability_start->translatedFormat('j F Y') }}, flexible
                        @endif
                    </dd>
                </div>
            @endif
        </dl>

        {{-- The wall: the deal is readable, acting on it is not. --}}
        <section class="mt-12 rounded-[2rem] bg-off-black p-8 text-white">
            <h2 class="font-display text-2xl font-black leading-tight">Apply to this Kolab</h2>
            <p class="mt-3 text-white/80">
                Create an account to see who posted it, message them, and apply for a date.
                @if ($isCommunityAsk)
                    Businesses need a plan to apply; browsing stays free.
                @else
                    Communities apply free — always.
                @endif
            </p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ $applyUrl }}"
                   class="inline-flex items-center justify-center rounded-full bg-primary px-6 py-3 font-bold text-off-black transition hover:brightness-95">
                    Apply on Kolabing
                </a>
                <a href="{{ url('/kolabs') }}"
                   class="inline-flex items-center justify-center rounded-full border border-white/30 px-6 py-3 font-bold text-white transition hover:border-white">
                    See other Kolabs
                </a>
            </div>
        </section>

        @if ($alsoOpen->isNotEmpty())
            <section class="mt-14">
                <h2 class="font-display text-2xl font-black text-off-black">
                    Also open{{ $kolab->preferred_city ? ' in '.$kolab->preferred_city : '' }}
                </h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ($alsoOpen as $other)
                        <x-kolab-card :kolab="$other" />
                    @endforeach
                </div>
            </section>
        @endif
    </article>
</x-layouts.marketing-page>
