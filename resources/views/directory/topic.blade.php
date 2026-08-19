@php
    use Illuminate\Support\Str;

    /** @var \App\Models\RankingPage $page */
    /** @var \Illuminate\Support\Collection $ranked (CrmAccount) */
    $counts = $counts ?? [];
    $label = Str::of($page->topic ?? '')->replace('-', ' ')->title()->replace(' And ', ' & ');

    $updated = optional($page->updated_at)->format('F Y') ?? now()->format('F Y');
    $updatedIso = optional($page->updated_at)?->toIso8601String() ?? now()->toIso8601String();
    $author = config('rankings.author_name');
    $authorTitle = config('rankings.author_title');

    $items = [];
    foreach ($ranked as $i => $a) {
        $handle = $a->metrics['handle'] ?? $a->instagram_handle ?? null;
        $items[] = array_filter([
            '@type' => 'ListItem',
            'position' => $i + 1,
            'item' => array_filter([
                '@type' => 'Organization',
                'name' => $a->name,
                'url' => $a->metrics['instagram_url'] ?? ($handle ? 'https://instagram.com/'.ltrim($handle, '@') : null),
                'description' => $a->metrics['blurb'] ?? null,
            ]),
        ]);
    }

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $page->title,
        'description' => $page->meta_description,
        'url' => route('directory.topic', [$page->city, $page->slug]),
        'dateModified' => $updatedIso,
        'author' => array_filter(['@type' => 'Person', 'name' => $author, 'jobTitle' => $authorTitle, 'sameAs' => config('rankings.author_url')]),
        'publisher' => ['@type' => 'Organization', 'name' => 'Kolabing', 'url' => route('home')],
        'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => $items],
    ];
    $breadcrumb = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Communities', 'item' => route('directory.index')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $page->city, 'item' => route('directory.city', $page->city)],
            ['@type' => 'ListItem', 'position' => 3, 'name' => (string) $label, 'item' => route('directory.topic', [$page->city, $page->slug])],
        ],
    ];
    $faqSchema = ! empty($page->faq) ? [
        '@context' => 'https://schema.org', '@type' => 'FAQPage',
        'mainEntity' => collect($page->faq)->map(fn ($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]])->all(),
    ] : null;

    $pending = collect($ranked)->contains(fn ($a) => ! empty($a->metrics['needs_verify']));
@endphp

<x-layouts.marketing-page :title="$page->title" :description="$page->meta_description" :canonical="route('directory.topic', [$page->city, $page->slug])">
    <x-slot:head>
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        <script type="application/ld+json">{!! json_encode($breadcrumb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @if ($faqSchema)
            <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
    </x-slot:head>

    <section class="mx-auto max-w-6xl px-6 py-16">
        <nav class="text-sm text-off-black/50">
            <a href="{{ route('directory.index') }}" class="hover:text-off-black">Communities</a>
            <span class="px-1">/</span><a href="{{ route('directory.city', $page->city) }}" class="hover:text-off-black">{{ $page->city }}</a>
            <span class="px-1">/</span><span class="text-off-black/70">{{ $label }}</span>
        </nav>

        <header class="mt-6 max-w-4xl">
            <p class="text-sm font-bold uppercase tracking-[0.24em] text-off-black/50">{{ $label }} · {{ $page->city }}</p>
            <h1 class="mt-3 font-montserrat text-4xl font-black uppercase leading-tight md:text-6xl">{{ $page->title }}</h1>
            @if ($page->intro)
                <p class="mt-6 max-w-3xl text-lg text-off-black/70">{{ $page->intro }}</p>
            @endif
            <p class="mt-4 text-sm text-off-black/60">
                Ranked and reviewed by {{ $author }}, {{ $authorTitle }} · Updated {{ $updated }} ·
                <a href="{{ route('directory.how-we-rank') }}" class="font-semibold text-off-black hover:text-primary">How we rank</a>
            </p>
        </header>

        @if ($pending)
            <div class="mt-6 rounded-2xl border border-primary/40 bg-primary/15 px-5 py-3 text-sm text-off-black/70">
                <span class="font-bold text-off-black">Preview.</span> This category is drafted from public sources and is pending a per-city editorial review before it publishes.
            </div>
        @endif

        @if ($page->spotlight_top && $ranked->isNotEmpty())
            @include('directory.partials.community-spotlight', ['a' => $ranked->first()])
            @include('directory.partials.ranked-list', ['ranked' => $ranked->skip(1), 'counts' => $counts])
        @else
            @include('directory.partials.ranked-list', ['ranked' => $ranked, 'counts' => $counts])
        @endif

        @if ($page->how_ranked)
            <div class="mt-8 rounded-2xl border border-off-black/10 bg-off-black/[0.03] p-6">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-off-black/40">How we ranked this</p>
                <p class="mt-2 text-sm leading-relaxed text-off-black/70">{{ $page->how_ranked }}</p>
            </div>
        @endif

        @if (! empty($page->host_venues))
            <section class="mt-10">
                <h2 class="font-montserrat text-2xl font-black uppercase tracking-tight">Venues that host these communities</h2>
                <p class="mt-2 max-w-2xl text-off-black/60">Studios and spaces where {{ Str::lower($label) }} groups meet. Run one? These are exactly the venues Kolabing helps fill.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($page->host_venues as $v)
                        <div class="rounded-2xl border border-off-black/10 bg-white p-5">
                            <p class="font-montserrat font-bold">{{ $v['name'] }}</p>
                            @if (! empty($v['note']))<p class="mt-1 text-sm text-off-black/60">{{ $v['note'] }}</p>@endif
                            @if (! empty($v['url']))<a href="{{ $v['url'] }}" target="_blank" rel="noopener nofollow" class="mt-2 inline-block text-sm font-semibold text-off-black hover:text-primary">Visit &rarr;</a>@endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @include('directory.partials.cta-rail', ['city' => $page->city, 'source' => $page->slug])

        @if (! empty($page->faq))
            <section class="mt-16 max-w-3xl">
                <h2 class="font-montserrat text-2xl font-black uppercase tracking-tight">Frequently asked</h2>
                <div class="mt-6 space-y-4">
                    @foreach ($page->faq as $f)
                        <div class="rounded-2xl border border-off-black/10 bg-white p-6">
                            <p class="font-bold">{{ $f['q'] }}</p>
                            <p class="mt-2 text-off-black/70">{{ $f['a'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </section>

    <script>
        document.querySelectorAll('[data-claim]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var name = document.getElementById('claim-name');
                if (name) { name.value = btn.getAttribute('data-claim') || ''; }
                var el = document.getElementById('claim');
                if (el) { el.scrollIntoView({ behavior: 'smooth' }); }
                if (name) { name.focus(); }
            });
        });
    </script>
</x-layouts.marketing-page>
