@php
    $title = 'Careers';
    $description = 'Learn how Kolabing approaches hiring and how to contact the team about future opportunities in product, growth, and community operations.';
    $canonical = route('careers');
@endphp

<x-layouts.marketing-page :title="$title" :description="$description" :canonical="$canonical">
    <section class="mx-auto max-w-5xl px-6 py-20">
        <h1 class="font-montserrat text-4xl font-black uppercase md:text-5xl">Careers</h1>
        <p class="mt-5 max-w-3xl text-lg text-off-black/70">Kolabing is building tools for stronger local ecosystems. We care about thoughtful product work, clear communication, and real-world impact for businesses and communities.</p>
        <div class="mt-10 rounded-[2rem] border border-off-black/10 bg-white p-8 shadow-sm">
            <h2 class="text-2xl font-bold">Future openings</h2>
            <p class="mt-4 text-off-black/70">We are not publishing active openings on this page yet, but we do review strong inbound interest from people who care about marketplaces, local growth, and community-led products.</p>
            <p class="mt-4 text-off-black/70">Send a short introduction and relevant work samples to <a class="font-bold text-off-black underline" href="mailto:support@kolabing.com">support@kolabing.com</a>.</p>
        </div>
    </section>
</x-layouts.marketing-page>
