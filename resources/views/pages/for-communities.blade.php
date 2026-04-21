@php
    $title = 'For Communities';
    $description = 'See how Kolabing helps community leaders secure venues, sponsor member experiences, and partner with aligned local businesses.';
    $canonical = route('for-communities');
@endphp

<x-layouts.marketing-page :title="$title" :description="$description" :canonical="$canonical">
    <section class="mx-auto max-w-6xl px-6 py-20">
        <p class="mb-4 text-sm font-bold uppercase tracking-[0.24em] text-off-black/50">For communities</p>
        <h1 class="max-w-4xl font-montserrat text-4xl font-black uppercase leading-tight md:text-6xl">Give members better experiences without chasing random sponsors.</h1>
        <p class="mt-6 max-w-3xl text-lg text-off-black/70">Kolabing helps organizers, clubs, and local communities find business partners that can offer venues, perks, exposure, and memorable in-person moments.</p>
        <div class="mt-10 grid gap-6 md:grid-cols-3">
            <article class="rounded-3xl border border-off-black/10 bg-white p-7 shadow-sm">
                <h2 class="text-xl font-bold">Secure better venues</h2>
                <p class="mt-3 text-off-black/70">Match with cafes, retail spaces, wellness brands, and neighborhood spots that can host your members in a way that feels natural.</p>
            </article>
            <article class="rounded-3xl border border-off-black/10 bg-white p-7 shadow-sm">
                <h2 class="text-xl font-bold">Reward your members</h2>
                <p class="mt-3 text-off-black/70">Create collaborations that unlock discounts, samples, exclusive access, and recurring reasons to show up.</p>
            </article>
            <article class="rounded-3xl border border-off-black/10 bg-white p-7 shadow-sm">
                <h2 class="text-xl font-bold">Grow with aligned brands</h2>
                <p class="mt-3 text-off-black/70">Choose partnerships that fit your identity, audience, and community rituals instead of one-off promotions that feel off-brand.</p>
            </article>
        </div>
        <div class="mt-12 rounded-[2rem] bg-off-black p-8 text-white">
            <h2 class="text-2xl font-bold text-primary">How community leaders use Kolabing</h2>
            <p class="mt-4 max-w-3xl text-white/75">Running clubs, creative groups, alumni networks, sports teams, and local interest communities can use Kolabing to build calendars, deepen loyalty, and create better partner conversations with less manual outreach.</p>
        </div>
    </section>
</x-layouts.marketing-page>
