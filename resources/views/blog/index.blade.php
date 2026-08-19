<x-layouts.marketing-page
    title="Blog"
    description="Community Commerce insights for local businesses and communities: turn real-world partnerships into footfall, members, and repeat visits."
    :canonical="route('blog.index')"
>
    <section class="mx-auto max-w-6xl px-6 py-16">
        <header class="max-w-2xl">
            <p class="font-montserrat text-sm font-bold uppercase tracking-widest text-off-black/50">Community Commerce</p>
            <h1 class="mt-3 font-montserrat text-4xl font-black uppercase tracking-tight md:text-5xl">The Kolabing blog</h1>
            <p class="mt-4 text-lg text-off-black/70">Real playbooks for local businesses and communities: footfall without paid ads, event marketing that actually works, and how the two sides grow together.</p>
        </header>

        @if ($posts->isEmpty())
            <p class="mt-16 text-off-black/60">New articles are on the way. Check back soon.</p>
        @else
            <div class="mt-12 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <article class="flex flex-col overflow-hidden rounded-2xl border border-off-black/10 bg-white transition hover:shadow-lg">
                        @if ($post->cover_image_url)
                            <a href="{{ route('blog.show', $post) }}"><img src="{{ $post->cover_image_url }}" alt="{{ $post->title }}" class="aspect-[16/9] w-full object-cover"></a>
                        @endif
                        <div class="flex flex-1 flex-col p-6">
                            <time datetime="{{ $post->published_at->toDateString() }}" class="text-xs font-semibold uppercase tracking-wide text-off-black/40">{{ $post->published_at->format('d M Y') }}</time>
                            <h2 class="mt-2 font-montserrat text-xl font-bold leading-tight"><a href="{{ route('blog.show', $post) }}" class="hover:text-primary">{{ $post->title }}</a></h2>
                            <p class="mt-3 flex-1 text-sm text-off-black/70">{{ $post->description }}</p>
                            <a href="{{ route('blog.show', $post) }}" class="mt-4 text-sm font-bold text-off-black hover:text-primary">Read more &rarr;</a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-12">{{ $posts->links() }}</div>
        @endif
    </section>
</x-layouts.marketing-page>
