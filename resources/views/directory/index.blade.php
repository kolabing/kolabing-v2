@php
    /** @var \Illuminate\Support\Collection $cities  each: ['page'=>RankingPage,'count'=>int,'categories'=>Collection<['slug','label']>] */
    $totalCommunities = $cities->sum('count');
    $featured = $cities->first();
@endphp

<x-layouts.marketing-page
    title="The best local communities in every city (2026)"
    description="Kolabing ranks the real community groups in each city — pottery studios, run clubs, supper clubs, AI meetups and more. Find the ones near you, or claim your free listing."
    :canonical="route('directory.index')"
>
    {{-- Hero --}}
    <section class="border-b border-off-black/10 bg-off-black text-white">
        <div class="mx-auto max-w-6xl px-6 py-20">
            <p class="font-montserrat text-sm font-bold uppercase tracking-widest text-primary">Community-led footfall</p>
            <h1 class="mt-4 max-w-4xl font-montserrat text-4xl font-black uppercase leading-[1.02] md:text-6xl">Every city's communities, ranked and ready to host.</h1>
            <p class="mt-6 max-w-2xl text-lg text-white/75">A directory of the real community groups in each city — from pottery studios to run clubs to AI meetups. Find the scenes near you, see who is most active, and discover the venues that host them.</p>
            @if ($featured)
                <div class="mt-8 flex flex-wrap items-center gap-4">
                    <a href="{{ route('directory.city', $featured['page']->city) }}" class="rounded-full bg-primary px-7 py-3 font-bold text-off-black transition hover:bg-primary/90">Explore {{ $featured['page']->city }}</a>
                    <span class="text-sm text-white/60">{{ number_format($totalCommunities) }} communities listed and counting</span>
                </div>
            @endif
        </div>
    </section>

    {{-- Featured city: the deep one, with its categories --}}
    @if ($featured && $featured['categories']->isNotEmpty())
        <section class="mx-auto max-w-6xl px-6 py-16">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-bold uppercase tracking-[0.24em] text-off-black/50">Browse {{ $featured['page']->city }} by category</p>
                    <h2 class="mt-2 font-montserrat text-3xl font-black uppercase tracking-tight">{{ $featured['count'] }} communities across {{ $featured['categories']->count() }} categories</h2>
                </div>
                <a href="{{ route('directory.city', $featured['page']->city) }}" class="hidden shrink-0 text-sm font-bold text-off-black hover:text-primary md:inline">See the full ranking &rarr;</a>
            </div>
            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featured['categories'] as $cat)
                    <a href="{{ route('directory.topic', [$featured['page']->city, $cat['slug']]) }}" class="group flex items-center justify-between rounded-2xl border border-off-black/10 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-off-black/25 hover:shadow-md">
                        <span class="font-montserrat font-bold">{{ $cat['label'] }}</span>
                        <span class="text-off-black/30 transition group-hover:translate-x-0.5 group-hover:text-primary">&rarr;</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- All cities --}}
    <section class="mx-auto max-w-6xl px-6 pb-16">
        <p class="text-sm font-bold uppercase tracking-[0.24em] text-off-black/50">All cities</p>
        <div class="mt-6 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($cities as $c)
                <a href="{{ route('directory.city', $c['page']->city) }}" class="group rounded-3xl border border-off-black/10 bg-white p-7 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                    <h3 class="font-montserrat text-2xl font-black uppercase tracking-tight">{{ $c['page']->city }}</h3>
                    <p class="mt-2 text-sm font-semibold uppercase tracking-wide text-off-black/40">{{ $c['count'] }} active communities</p>
                    @if ($c['categories']->isNotEmpty())
                        <div class="mt-4 flex flex-wrap gap-1.5">
                            @foreach ($c['categories']->take(4) as $cat)
                                <span class="rounded-full bg-off-black/5 px-2.5 py-0.5 text-[11px] font-semibold text-off-black/50">{{ $cat['label'] }}</span>
                            @endforeach
                        </div>
                    @endif
                    <span class="mt-5 inline-block text-sm font-bold text-off-black transition group-hover:text-primary">See the ranking &rarr;</span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Claim CTA --}}
    <section class="mx-auto max-w-6xl px-6 pb-20">
        <div class="rounded-[2rem] bg-primary/25 p-8 md:flex md:items-center md:justify-between md:gap-10">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.24em] text-off-black/50">Run a community?</p>
                <h2 class="mt-3 font-montserrat text-2xl font-black uppercase leading-tight">Get listed free, and get found by venues.</h2>
                <p class="mt-3 max-w-xl text-off-black/75">Claim your spot in your city's ranking. We feature you and introduce you to venues near you that want to host your events.</p>
            </div>
            <a href="{{ route('directory.how-we-rank') }}" class="mt-6 inline-block shrink-0 rounded-full bg-off-black px-7 py-3 font-bold text-primary transition hover:bg-off-black/90 md:mt-0">How it works</a>
        </div>
    </section>
</x-layouts.marketing-page>
