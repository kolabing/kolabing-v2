@php
    /**
     * Public teaser for a business or community profile.
     *
     * Everything a logged-out visitor may see is computed in
     * PublicProfilePageController and passed in. Do NOT reach for more off $profile
     * here: contact details, the full review list, reviewer identities, past-event
     * detail and collaboration partners are deliberately absent from this HTML
     * rather than hidden with CSS — they are the reason to create an account.
     */
    $ratingLabel = $averageRating ? number_format((float) $averageRating, 1) : null;

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => $isBusiness ? 'LocalBusiness' : 'Organization',
        'name' => $displayName,
        'url' => $canonicalUrl,
    ];
    if ($aboutPreview !== '') {
        $schema['description'] = $aboutPreview;
    }
    if ($avatarUrl) {
        $schema['image'] = $avatarUrl;
    }
    if ($cityName) {
        $schema['address'] = ['@type' => 'PostalAddress', 'addressLocality' => $cityName];
    }
    // Only claim an aggregate rating when there is a real one behind it.
    if ($ratingLabel && $reviewCount > 0) {
        $schema['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => $ratingLabel,
            'reviewCount' => $reviewCount,
            'bestRating' => 5,
            'worstRating' => 1,
        ];
    }

    $metaDescription = trim(sprintf(
        '%s%s on Kolabing.%s%s',
        $displayName,
        $cityName ? ' — '.$typeLabel.' in '.$cityName : ' — '.$typeLabel,
        $ratingLabel && $reviewCount > 0 ? ' Rated '.$ratingLabel.'/5 from '.$reviewCount.' verified collaboration '.($reviewCount === 1 ? 'review' : 'reviews').'.' : '',
        $completedKolabs > 0 ? ' '.$completedKolabs.' completed '.($completedKolabs === 1 ? 'Kolab' : 'Kolabs').'.' : ''
    ));
@endphp

<x-layouts.marketing-page
    :title="$displayName"
    :description="$metaDescription"
    :canonical="$canonicalUrl"
    :image="$avatarUrl"
    og-type="profile"
>
    <x-slot:head>
        <script type="application/ld+json">
            {!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    </x-slot:head>

    <article class="mx-auto max-w-3xl px-6 py-14 sm:py-20">

        {{-- ── Identity ─────────────────────────────────────────────────── --}}
        <header class="flex flex-col items-start gap-5 sm:flex-row sm:items-center">
            <div class="h-24 w-24 shrink-0 overflow-hidden rounded-3xl bg-primary/40">
                @if ($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="{{ $displayName }}" class="h-full w-full object-cover" loading="lazy">
                @else
                    <div class="flex h-full w-full items-center justify-center font-display text-3xl font-black text-off-black">
                        {{ mb_strtoupper(mb_substr($displayName, 0, 1)) }}
                    </div>
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <h1 class="font-display text-3xl font-black leading-tight text-off-black sm:text-4xl">{{ $displayName }}</h1>
                <p class="mt-1.5 text-sm font-semibold text-off-black/60">
                    {{ $typeLabel }}@if ($cityName) · {{ $cityName }}@endif
                </p>

                @if ($ratingLabel && $reviewCount > 0)
                    <p class="mt-3 inline-flex items-center gap-2 rounded-full bg-primary/30 px-3 py-1.5 text-sm font-bold text-off-black">
                        <span aria-hidden="true">★</span>
                        {{ $ratingLabel }}
                        <span class="font-medium text-off-black/60">({{ $reviewCount }} {{ $reviewCount === 1 ? 'review' : 'reviews' }})</span>
                    </p>
                @endif
            </div>
        </header>

        {{-- ── Headline numbers ─────────────────────────────────────────── --}}
        <dl class="mt-10 grid grid-cols-3 gap-3 text-center">
            @foreach ([
                ['n' => $completedKolabs, 'label' => 'Completed Kolabs'],
                ['n' => $pastEventCount, 'label' => 'Past events'],
                ['n' => $reviewCount, 'label' => 'Reviews'],
            ] as $stat)
                <div class="rounded-2xl border border-off-black/10 px-3 py-5">
                    <dt class="sr-only">{{ $stat['label'] }}</dt>
                    <dd>
                        <span class="block font-display text-2xl font-black text-off-black">{{ $stat['n'] }}</span>
                        <span class="mt-1 block text-[11px] font-semibold uppercase tracking-[0.12em] text-off-black/50">{{ $stat['label'] }}</span>
                    </dd>
                </div>
            @endforeach
        </dl>

        {{-- ── About (trimmed) ──────────────────────────────────────────── --}}
        @if ($aboutPreview !== '')
            <section class="mt-10">
                <h2 class="font-display text-lg font-black text-off-black">About</h2>
                <p class="mt-2 whitespace-pre-line leading-relaxed text-off-black/80">{{ $aboutPreview }}</p>
            </section>
        @endif

        {{-- ── A few photos ─────────────────────────────────────────────── --}}
        @if (count($photos) > 0)
            <section class="mt-10">
                <h2 class="font-display text-lg font-black text-off-black">Photos</h2>
                <div class="mt-3 grid grid-cols-3 gap-2">
                    @foreach ($photos as $photo)
                        <img src="{{ $photo['url'] }}" alt="{{ $displayName }}"
                             class="aspect-square w-full rounded-xl object-cover" loading="lazy">
                    @endforeach
                </div>
                @if ($hiddenPhotoCount > 0)
                    <p class="mt-2 text-sm text-off-black/50">+{{ $hiddenPhotoCount }} more in the app</p>
                @endif
            </section>
        @endif

        {{-- ── One quote ────────────────────────────────────────────────── --}}
        @if ($featuredReview)
            <section class="mt-10">
                <h2 class="font-display text-lg font-black text-off-black">What partners say</h2>
                <figure class="mt-3 rounded-2xl border border-off-black/10 bg-off-white p-5">
                    @if ($featuredReview['rating'])
                        <p class="text-sm text-primary-dark" aria-label="{{ $featuredReview['rating'] }} out of 5">
                            {{ str_repeat('★', (int) $featuredReview['rating']) }}<span class="text-off-black/20">{{ str_repeat('★', 5 - (int) $featuredReview['rating']) }}</span>
                        </p>
                    @endif
                    <blockquote class="mt-2 leading-relaxed text-off-black/85">“{{ $featuredReview['comment'] }}”</blockquote>
                    {{-- Who wrote it is part of what an account buys. --}}
                    <figcaption class="mt-3 text-xs font-semibold uppercase tracking-[0.1em] text-off-black/45">
                        Verified {{ $featuredReview['reviewer_type'] === 'business' ? 'business' : 'community' }} partner
                    </figcaption>
                </figure>
            </section>
        @endif

        {{-- ── The wall ─────────────────────────────────────────────────── --}}
        <section class="relative mt-10 overflow-hidden rounded-[2rem] bg-off-black p-8 text-white">
            <h2 class="font-display text-2xl font-black leading-tight">
                See everything about {{ $displayName }}
            </h2>
            <ul class="mt-4 space-y-2 text-sm text-white/80">
                @if ($reviewCount > 1)
                    <li>· All {{ $reviewCount }} reviews, and who wrote them</li>
                @endif
                @if ($pastEventCount > 0)
                    <li>· {{ $pastEventCount }} past {{ $pastEventCount === 1 ? 'event' : 'events' }} with dates, partners and photos</li>
                @endif
                @if ($collaborationCount > 0)
                    <li>· {{ $collaborationCount }} completed {{ $collaborationCount === 1 ? 'collaboration' : 'collaborations' }}</li>
                @endif
                <li>· Contact details and social links</li>
                <li>· Message them directly once you match</li>
            </ul>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                <a href="{{ $appUrl }}/register"
                   class="inline-flex items-center justify-center rounded-full bg-primary px-6 py-3 font-bold text-off-black transition hover:brightness-95">
                    Create your free account
                </a>
                <a href="{{ $appUrl }}/login" class="text-sm font-semibold text-white/70 underline-offset-4 hover:underline">
                    Already on Kolabing? Log in
                </a>
            </div>
            <p class="mt-4 text-xs text-white/50">Free for communities · no app needed</p>
        </section>

        <p class="mt-8 text-center text-sm text-off-black/50">
            Is this you?
            <a href="{{ $appUrl }}/login" class="font-semibold text-off-black underline underline-offset-4">Log in to edit your profile</a>
        </p>
    </article>
</x-layouts.marketing-page>
