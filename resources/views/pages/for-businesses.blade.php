@php
    $title = 'For Businesses';
    $description = 'See how Kolabing helps local businesses drive foot traffic, activate neighborhoods, and launch community partnerships without wasted ad spend.';
    $canonical = route('for-businesses');
@endphp

<x-layouts.marketing-page :title="$title" :description="$description" :canonical="$canonical">
    <section class="mx-auto max-w-6xl px-6 py-20">
        <p class="mb-4 text-sm font-bold uppercase tracking-[0.24em] text-off-black/50">For businesses</p>
        <h1 class="max-w-4xl font-montserrat text-4xl font-black uppercase leading-tight md:text-6xl">Turn local partnerships into repeatable customer growth.</h1>
        <p class="mt-6 max-w-3xl text-lg text-off-black/70">Kolabing helps neighborhood businesses launch collaborations with clubs, teams, creators, and community organizers that bring real people into real spaces.</p>
        <div class="mt-10 grid gap-6 md:grid-cols-3">
            <article class="rounded-3xl border border-off-black/10 bg-white p-7 shadow-sm">
                <h2 class="text-xl font-bold">Fill quiet hours</h2>
                <p class="mt-3 text-off-black/70">Create campaigns for mornings, midweek evenings, seasonal launches, or product drops without relying on discounts alone.</p>
            </article>
            <article class="rounded-3xl border border-off-black/10 bg-white p-7 shadow-sm">
                <h2 class="text-xl font-bold">Reach niche audiences</h2>
                <p class="mt-3 text-off-black/70">Match with communities that already gather around your category, city, or values instead of renting broad attention from generic ads.</p>
            </article>
            <article class="rounded-3xl border border-off-black/10 bg-white p-7 shadow-sm">
                <h2 class="text-xl font-bold">Measure outcomes</h2>
                <p class="mt-3 text-off-black/70">Track attendance, content creation, member response, and repeat visits so your next collaboration gets smarter over time.</p>
            </article>
        </div>
        <div class="mt-12 rounded-[2rem] bg-primary/20 p-8">
            <h2 class="text-2xl font-bold">What businesses launch on Kolabing</h2>
            <p class="mt-4 max-w-3xl text-off-black/75">Coffee shops host running groups, studios partner with wellness communities, venues test new formats, and local brands create events that generate both user-generated content and real-world conversion. The best partnerships feel relevant to the neighborhood, easy to join, and valuable to both sides.</p>
        </div>
    </section>
</x-layouts.marketing-page>
