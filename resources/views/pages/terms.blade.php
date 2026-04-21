@php
    $title = 'Terms of Service';
    $description = 'Review the Kolabing terms of service for access, acceptable use, collaboration responsibilities, and support contact details.';
    $canonical = route('terms');
@endphp

<x-layouts.marketing-page :title="$title" :description="$description" :canonical="$canonical">
    <section class="mx-auto max-w-4xl px-6 py-20">
        <h1 class="font-montserrat text-4xl font-black uppercase md:text-5xl">Terms of Service</h1>
        <div class="prose prose-lg mt-8 max-w-none prose-headings:font-montserrat prose-headings:uppercase prose-a:text-off-black">
            <p>These terms govern access to Kolabing and the use of its marketplace, messaging, and collaboration features.</p>
            <h2>Platform use</h2>
            <p>Users are responsible for providing accurate information, respecting other users, and using the service lawfully.</p>
            <h2>Collaborations</h2>
            <p>Businesses and communities are responsible for the specific terms, logistics, and outcomes of their agreements unless Kolabing expressly states otherwise.</p>
            <h2>Content and accounts</h2>
            <p>Users must not upload unlawful, misleading, or harmful content. Kolabing may limit or suspend accounts that violate these terms or create risk for the platform.</p>
            <h2>Contact</h2>
            <p>Questions about these terms can be sent to <a href="mailto:support@kolabing.com">support@kolabing.com</a>.</p>
        </div>
    </section>
</x-layouts.marketing-page>
