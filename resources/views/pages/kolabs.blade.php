@php
    /**
     * Active Kolabs, open to anyone — the marketplace's shop window.
     *
     * Details are public (ROLES §2.5: a free business already "sees every Kolab's
     * details"), the poster's identity follows PublicKolabPoster, and the wall sits at
     * the action: applying hands off to the panel.
     */
    use App\Enums\IntentType;

    $count = $kolabs->total();

    $intents = [
        '' => 'Everything',
        IntentType::CommunitySeeking->value => 'Communities looking',
        IntentType::VenuePromotion->value => 'Venues offering',
        IntentType::ProductPromotion->value => 'Products offering',
    ];

    $title = $selectedCity !== '' ? 'Kolabs in '.$selectedCity : 'Active Kolabs';
    $description = $selectedCity !== ''
        ? 'Open collaborations between local businesses and communities in '.$selectedCity.' — see what each side offers and apply free.'
        : 'Open collaborations between local businesses and communities: what each side offers, what they are looking for, and how to apply.';

    /*
     * Not indexed yet, on purpose. See config('kolabing.public_kolabs.indexable') and
     * BE-FX-24: production still holds test listings, and asking Google to index them
     * as the product's shop window is hard to undo. The page works for humans today;
     * flipping the config invites crawlers and adds these URLs to sitemap.xml.
     */
    $robots = config('kolabing.public_kolabs.indexable') ? null : 'noindex,follow';

    // Filter chips compose: picking a kind keeps the city and vice versa.
    $filterUrl = function (array $params): string {
        $query = http_build_query(array_filter($params, fn ($value) => $value !== '' && $value !== null));

        return url('/kolabs').($query === '' ? '' : '?'.$query);
    };
@endphp

<x-layouts.marketing-page :title="$title" :description="$description" :canonical="url('/kolabs')" :robots="$robots">
    <article class="mx-auto max-w-5xl px-6 py-14 sm:py-20">

        <header class="max-w-2xl">
            <p class="font-montserrat text-sm font-bold uppercase tracking-widest text-off-black/50">The marketplace</p>
            <h1 class="mt-3 font-display text-4xl font-black leading-tight text-off-black sm:text-5xl">
                {{ $selectedCity !== '' ? 'Kolabs in '.$selectedCity : 'Kolabs open right now' }}
            </h1>
            <p class="mt-4 text-lg leading-relaxed text-off-black/70">
                A Kolab is a deal between a local business and a community — a venue for a run club's brunch,
                a product for a supper club to try, a discount for members. Both sides post, both sides apply.
                Browsing is free.
            </p>
        </header>

        <nav class="mt-8 flex flex-wrap gap-2" aria-label="Filter by kind">
            @foreach ($intents as $value => $label)
                <a href="{{ $filterUrl(['intent' => $value, 'city' => $selectedCity]) }}"
                   class="rounded-full px-4 py-2 text-sm font-semibold transition {{ $selectedIntent === $value ? 'bg-off-black text-white' : 'border border-off-black/15 text-off-black/70 hover:border-off-black' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        {{-- City filter. Only cities with a live listing, so no chip leads nowhere. --}}
        @if ($cities->isNotEmpty())
            <nav class="mt-3 flex flex-wrap gap-2" aria-label="Filter by city">
                <a href="{{ $filterUrl(['intent' => $selectedIntent]) }}"
                   class="rounded-full px-4 py-2 text-sm font-semibold transition {{ $selectedCity === '' ? 'bg-off-black text-white' : 'border border-off-black/15 text-off-black/70 hover:border-off-black' }}">
                    All cities
                </a>
                @foreach ($cities as $city)
                    <a href="{{ $filterUrl(['city' => $city, 'intent' => $selectedIntent]) }}"
                       class="rounded-full px-4 py-2 text-sm font-semibold transition {{ $selectedCity === $city ? 'bg-off-black text-white' : 'border border-off-black/15 text-off-black/70 hover:border-off-black' }}">
                        {{ $city }}
                    </a>
                @endforeach
            </nav>
        @endif

        @if ($count === 0)
            <div class="mt-12 rounded-3xl border-2 border-dashed border-off-black/15 px-8 py-16 text-center">
                <p class="font-display text-xl font-black text-off-black">Nothing open here right now</p>
                <p class="mx-auto mt-2 max-w-md text-off-black/60">
                    Kolabs appear as businesses and communities post them, and drop off once their dates pass.
                    Posting one is free for communities.
                </p>
                <a href="{{ $appUrl }}/register"
                   class="mt-6 inline-flex items-center justify-center rounded-full bg-primary px-6 py-3 font-bold text-off-black transition hover:brightness-95">
                    Post a Kolab
                </a>
            </div>
        @else
            <p class="mt-8 text-sm font-semibold text-off-black/50">
                {{ $count }} {{ $count === 1 ? 'Kolab' : 'Kolabs' }} open
            </p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($kolabs as $kolab)
                    <x-kolab-card :kolab="$kolab" />
                @endforeach
            </div>

            <div class="mt-10">{{ $kolabs->links() }}</div>
        @endif

        <section class="mt-16 rounded-[2rem] bg-off-black p-8 text-white">
            <h2 class="font-display text-2xl font-black leading-tight">Want in on one of these?</h2>
            <p class="mt-3 max-w-xl text-white/80">
                Create an account to see who is behind each Kolab and apply. Communities never pay on Kolabing;
                businesses get a free account and pay only to create and apply.
            </p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ $appUrl }}/register?type=community"
                   class="inline-flex items-center justify-center rounded-full bg-primary px-6 py-3 font-bold text-off-black transition hover:brightness-95">
                    I run a community
                </a>
                <a href="{{ $appUrl }}/register?type=business"
                   class="inline-flex items-center justify-center rounded-full border border-white/30 px-6 py-3 font-bold text-white transition hover:border-white">
                    I run a business
                </a>
            </div>
        </section>
    </article>
</x-layouts.marketing-page>
