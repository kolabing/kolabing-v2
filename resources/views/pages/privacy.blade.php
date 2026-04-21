@php
    $title = 'Privacy Policy';
    $description = 'Read the Kolabing privacy policy covering account data, communication preferences, analytics, and how users can request support or data updates.';
    $canonical = route('privacy');
@endphp

<x-layouts.marketing-page :title="$title" :description="$description" :canonical="$canonical">
    <section class="mx-auto max-w-4xl px-6 py-20">
        <h1 class="font-montserrat text-4xl font-black uppercase md:text-5xl">Privacy Policy</h1>
        <div class="prose prose-lg mt-8 max-w-none prose-headings:font-montserrat prose-headings:uppercase prose-a:text-off-black">
            <p>Kolabing collects the information needed to operate accounts, match businesses with communities, and support communications around collaborations.</p>
            <h2>Information we collect</h2>
            <p>We may collect account details, profile information, event and collaboration details, device information, and support communications that help us operate the platform responsibly.</p>
            <h2>How we use information</h2>
            <p>We use data to provide the service, improve matching quality, communicate product updates, prevent abuse, and respond to support requests.</p>
            <h2>Data sharing</h2>
            <p>We do not sell personal information. We may share limited information with infrastructure and communications providers when necessary to operate the product.</p>
            <h2>Your choices</h2>
            <p>You can contact <a href="mailto:support@kolabing.com">support@kolabing.com</a> to request help with account information, communication preferences, or general privacy questions.</p>
        </div>
    </section>
</x-layouts.marketing-page>
