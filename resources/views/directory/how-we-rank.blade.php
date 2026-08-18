@php
    $author = config('rankings.author_name');
    $authorTitle = config('rankings.author_title');
    $reviewer = config('rankings.reviewer_name');
@endphp

<x-layouts.marketing-page
    title="How we rank communities"
    description="How Kolabing ranks local communities: checkable facts only (public audience, cadence, real collaborations), curated and human-reviewed, with a free claim, correct and remove path."
    :canonical="route('directory.how-we-rank')"
>
    <section class="mx-auto max-w-3xl px-6 py-16">
        <nav class="text-sm text-off-black/50">
            <a href="{{ route('directory.index') }}" class="hover:text-off-black">Communities</a>
            <span class="px-1">/</span><span class="text-off-black/70">How we rank</span>
        </nav>

        <header class="mt-6">
            <p class="text-sm font-bold uppercase tracking-[0.24em] text-off-black/50">Methodology</p>
            <h1 class="mt-3 font-montserrat text-4xl font-black uppercase leading-tight md:text-5xl">How we rank communities</h1>
            <p class="mt-4 text-lg text-off-black/70">Our rankings are curated and human-reviewed, not automated. Here is exactly what goes into them, and how to correct or remove your listing.</p>
        </header>

        <div class="prose prose-lg mt-10 max-w-none prose-headings:font-montserrat prose-a:text-off-black">
            <h2>What we rank on</h2>
            <p>Every ranking is built on checkable facts only, the kind a reader could verify themselves:</p>
            <ul>
                <li><strong>Public audience</strong> where a community makes it visible (a public Instagram or Meetup following), marked "from public profile" and re-checked at publish.</li>
                <li><strong>Cadence</strong>: how regularly the community actually runs events, weekly, monthly, or seasonal.</li>
                <li><strong>Real collaborations</strong>: venues, brands, or partners the community has genuinely worked with.</li>
            </ul>
            <p>We never publish a private number, and we never state a fact we cannot check. Where a community's audience is not public, we say so rather than guess.</p>

            <h2>Curated, then human-reviewed</h2>
            <p>The order is drawn live from the Kolabing CRM and reviewed by {{ $author }}, {{ $authorTitle }}, before a city goes live. Each city gets a review by {{ $reviewer }} so a local editor can catch anything the data misses.</p>

            <h2>Claim, correct, or remove your listing</h2>
            <p>Every listing carries an "Is this you? Claim or correct it" link. Claiming is free and takes one field. If you would prefer not to be listed, tell us and we will remove you, no questions asked. We only ever use publicly available information about a community and its organiser.</p>

            <h2>Why we build this</h2>
            <p>Kolabing connects local communities with the venues that want to host them. The ranking is how a community gets discovered, and how a venue sees the demand near it. Being featured is free, and comes with a badge you can post and introductions to venues near you.</p>
        </div>

        <aside class="mt-14 rounded-[2rem] bg-off-black p-8 text-white">
            <p class="text-xs font-bold uppercase tracking-[0.24em] text-primary">Get listed</p>
            <h2 class="mt-3 font-montserrat text-2xl font-black uppercase leading-tight">Run a community? Claim your free spot.</h2>
            <p class="mt-3 text-white/70">Browse your city's ranking, find your community, and claim it in one field.</p>
            <a href="{{ route('directory.index') }}" class="mt-6 inline-block rounded-full bg-primary px-7 py-3 font-bold text-off-black transition hover:bg-primary/90">Browse the directory</a>
        </aside>
    </section>
</x-layouts.marketing-page>
