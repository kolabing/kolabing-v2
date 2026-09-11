@php
    $articleSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post->title,
        'description' => $post->description,
        'datePublished' => optional($post->published_at)->toIso8601String(),
        'dateModified' => $post->updated_at->toIso8601String(),
        'author' => ['@type' => 'Person', 'name' => $post->author_name],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'Kolabing',
            'logo' => ['@type' => 'ImageObject', 'url' => url('/brand/kolabing-k-on-black.png')],
        ],
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => route('blog.show', $post)],
    ];
    if ($post->cover_image_url) {
        $articleSchema['image'] = $post->cover_image_url;
    }
@endphp
<x-layouts.marketing-page
    :title="$post->title"
    :description="$post->description"
    :canonical="route('blog.show', $post)"
    :image="$post->cover_image_url"
    og-type="article"
>
    <x-slot:head>
        <script type="application/ld+json">
            {!! json_encode($articleSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    </x-slot:head>

    <article class="mx-auto max-w-3xl px-6 py-16">
        <nav class="text-sm text-off-black/50"><a href="{{ route('blog.index') }}" class="hover:text-off-black">&larr; All articles</a></nav>
        <header class="mt-6">
            <time datetime="{{ $post->published_at->toDateString() }}" class="text-xs font-semibold uppercase tracking-wide text-off-black/40">{{ $post->published_at->format('d M Y') }}</time>
            <h1 class="mt-2 font-montserrat text-4xl font-black leading-tight tracking-tight md:text-5xl">{{ $post->title }}</h1>
            <p class="mt-4 text-lg text-off-black/70">{{ $post->description }}</p>
            <p class="mt-4 text-sm text-off-black/60">By {{ $post->author_name }}@if ($post->author_title), {{ $post->author_title }}@endif</p>
        </header>

        @if ($post->cover_image_url)
            <img src="{{ $post->cover_image_url }}" alt="{{ $post->title }}" class="mt-8 aspect-[16/9] w-full rounded-2xl object-cover">
        @endif

        <div class="prose prose-lg mt-8 max-w-none prose-headings:font-montserrat prose-a:text-off-black">
            {!! $post->body !!}
        </div>

        {{-- End-of-article funnel: readers who got this far are the warmest
             traffic the blog produces, so the sign-up sits before "keep reading". --}}
        <aside class="mt-14 rounded-[2rem] bg-off-black p-8 text-white">
            <p class="text-xs font-bold uppercase tracking-[0.24em] text-primary">Start your first kolab</p>
            <h2 class="mt-3 font-montserrat text-2xl font-black uppercase leading-tight">Businesses get customers. Communities get perks.</h2>
            <p class="mt-3 text-white/70">Kolabing runs in your browser — free for communities, cancel anytime for businesses.</p>
            <div class="mt-6 flex flex-wrap items-center gap-4">
                <a href="{{ rtrim(config('webapp.url'), '/') }}/register" class="rounded-full bg-primary px-7 py-3 font-bold text-off-black transition hover:bg-primary/90">Get started free</a>
                <a href="{{ rtrim(config('webapp.url'), '/') }}/login" class="text-sm font-medium text-white/60 hover:text-white">Already have an account? Log in</a>
            </div>
        </aside>

        @if ($related->isNotEmpty())
            <aside class="mt-16 border-t border-off-black/10 pt-10">
                <h2 class="font-montserrat text-lg font-bold uppercase tracking-wide">Keep reading</h2>
                <ul class="mt-4 space-y-3">
                    @foreach ($related as $item)
                        <li><a href="{{ route('blog.show', $item) }}" class="font-medium hover:text-primary">{{ $item->title }}</a></li>
                    @endforeach
                </ul>
            </aside>
        @endif
    </article>
</x-layouts.marketing-page>
