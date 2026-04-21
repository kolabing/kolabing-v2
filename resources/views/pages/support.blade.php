@php
    $title = 'Support';
    $description = 'Find Kolabing support details, product guidance, and the fastest way to contact the team about partnerships, onboarding, and account help.';
    $canonical = route('support');
@endphp

<x-layouts.marketing-page :title="$title" :description="$description" :canonical="$canonical">
    <section class="mx-auto max-w-5xl px-6 py-20">
        <h1 class="font-montserrat text-4xl font-black uppercase md:text-5xl">Support</h1>
        <p class="mt-5 max-w-3xl text-lg text-off-black/70">Need help with onboarding, collaboration setup, product access, or partnership questions? The Kolabing team can help you get the right answer fast.</p>
        <div class="mt-10 grid gap-6 md:grid-cols-2">
            <article class="rounded-3xl border border-off-black/10 bg-white p-8 shadow-sm">
                <h2 class="text-xl font-bold">Email support</h2>
                <p class="mt-3 text-off-black/70">For account, product, and launch questions, email the team directly.</p>
                <a href="mailto:support@kolabing.com" class="mt-5 inline-flex rounded-full bg-off-black px-5 py-3 font-bold text-white">support@kolabing.com</a>
            </article>
            <article class="rounded-3xl border border-off-black/10 bg-primary/20 p-8 shadow-sm">
                <h2 class="text-xl font-bold">What to include</h2>
                <p class="mt-3 text-off-black/70">Share your business or community name, city, device type, and a short summary of the issue or opportunity so the team can respond with context.</p>
            </article>
        </div>
    </section>
</x-layouts.marketing-page>
