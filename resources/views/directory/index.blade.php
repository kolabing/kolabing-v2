@php
    /** @var \Illuminate\Support\Collection $cities  each: ['page' => RankingPage, 'count' => int] */
    $author = config('rankings.author_name');
@endphp

<x-layouts.marketing-page
    title="The best local communities in every city (2026)"
    description="Kolabing ranks the real community groups in each city — running clubs, pottery studios, supper clubs, AI meetups and more. Find the ones near you, or claim your free listing."
    :canonical="route('directory.index')"
>
    <section class="mx-auto max-w-6xl px-6 py-16">
        <header class="max-w-3xl">
            <p class="font-montserrat text-sm font-bold uppercase tracking-widest text-off-black/50">Community-led footfall</p>
            <h1 class="mt-3 font-montserrat text-4xl font-black uppercase leading-tight md:text-6xl">Every city's communities, ranked and ready to host.</h1>
            <p class="mt-6 text-lg text-off-black/70">A directory of the real community groups in each city — drawn live from the Kolabing network. Find the scenes near you, see who is most active, and discover the venues that host them.</p>
        </header>

        <div class="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($cities as $c)
                <a href="{{ route('directory.city', $c['page']->city) }}" class="group rounded-3xl border border-off-black/10 bg-white p-7 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                    <div class="flex items-baseline justify-between gap-3">
                        <h2 class="font-montserrat text-2xl font-black uppercase tracking-tight">{{ $c['page']->city }}</h2>
                        <span class="font-montserrat text-sm font-bold text-off-black/40">{{ $c['count'] }}</span>
                    </div>
                    <p class="mt-2 text-sm font-semibold uppercase tracking-wide text-off-black/40">{{ $c['count'] }} active communities</p>
                    <span class="mt-5 inline-block text-sm font-bold text-off-black transition group-hover:text-primary">See the ranking &rarr;</span>
                </a>
            @endforeach
        </div>

        <aside class="mt-14 rounded-[2rem] bg-off-black p-8 text-white md:flex md:items-center md:justify-between md:gap-10">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.24em] text-primary">Run a community?</p>
                <h2 class="mt-3 font-montserrat text-2xl font-black uppercase leading-tight">Get listed free, and get found by venues.</h2>
                <p class="mt-3 max-w-xl text-white/70">Claim your spot in your city's ranking. We feature you and introduce you to venues near you that want to host your events.</p>
            </div>
            <a href="{{ route('directory.how-we-rank') }}" class="mt-6 inline-block shrink-0 rounded-full bg-primary px-7 py-3 font-bold text-off-black transition hover:bg-primary/90 md:mt-0">How it works</a>
        </aside>
    </section>
</x-layouts.marketing-page>
