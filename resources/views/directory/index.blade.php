@php
    /** @var \Illuminate\Support\Collection $cities  each: ['page'=>RankingPage,'count'=>int,'categories'=>Collection<['slug','label']>] */
    $totalCommunities = $cities->sum('count');
    $featured = $cities->first();
@endphp

<x-layouts.marketing-page
    title="The best local communities in every city (2026)"
    description="Kolabing ranks the real community groups in each city — pottery studios, run clubs, supper clubs, AI meetups and more. Find the ones near you, or claim your free listing."
    :canonical="route('directory.index')"
    :robots="$cities->isEmpty() ? 'noindex,follow' : null"
>
    <x-slot:head>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    </x-slot:head>

    {{-- Hero --}}
    <section class="border-b border-off-black/10 bg-off-black text-white">
        <div class="mx-auto max-w-6xl px-6 py-20">
            <p class="font-montserrat text-sm font-bold uppercase tracking-widest text-primary">Community-led footfall</p>
            <h1 class="mt-4 max-w-4xl font-montserrat text-4xl font-black uppercase leading-[1.02] md:text-6xl">Every city's communities, ranked and ready to host.</h1>
            <p class="mt-6 max-w-2xl text-lg text-white/75">A directory of the real community groups in each city — from pottery studios to run clubs to AI meetups. Find the scenes near you, see who is most active, and discover the venues that host them.</p>
            @if ($featured)
                <div class="mt-8 flex flex-wrap items-center gap-4">
                    <a href="{{ route('directory.city', $featured['page']->city) }}" class="rounded-full bg-primary px-7 py-3 font-bold text-off-black transition hover:bg-primary/90">Explore {{ $featured['page']->city }}</a>
                    <span class="text-sm text-white/60">{{ number_format($totalCommunities) }} communities listed and counting</span>
                </div>
            @endif
        </div>
    </section>

    {{-- Map of active cities --}}
    @if (! empty($map))
        <section class="border-b border-off-black/10">
            <div class="mx-auto max-w-6xl px-6 py-14">
                <p class="text-sm font-bold uppercase tracking-[0.24em] text-off-black/50">Where the communities are</p>
                <h2 class="mt-2 font-montserrat text-3xl font-black uppercase tracking-tight">Explore the map</h2>
                <p class="mt-2 max-w-2xl text-off-black/60">Tap a city to zoom into its neighbourhoods. More cities are coming online.</p>
                <div class="relative mt-6">
                    <div id="rankmap" class="h-[440px] w-full overflow-hidden rounded-[2rem] border border-off-black/10 bg-off-black/5"></div>
                    <button id="rankmap-reset" class="absolute right-4 top-4 z-[500] hidden rounded-full bg-white px-4 py-2 text-xs font-bold text-off-black shadow ring-1 ring-off-black/10">&larr; All cities</button>
                </div>
            </div>
        </section>
    @endif

    {{-- Featured city: photo bento of its scenes --}}
    @if (! empty($bento))
        @php
            $tiles = ['run' => '#FFE9A3', 'cycl' => '#FFD560', 'well' => '#CDE9D9', 'tech' => '#DDE3EA', 'ai' => '#DDE3EA', 'startup' => '#DDE3EA', 'found' => '#DDE3EA', 'expat' => '#F6D8C7', 'social' => '#F6D8C7', 'lang' => '#F6D8C7', 'potter' => '#EAD7C7', 'ceramic' => '#EAD7C7', 'craft' => '#EAD7C7', 'art' => '#E7D3E8', 'creat' => '#E7D3E8', 'draw' => '#E7D3E8', 'sketch' => '#E7D3E8', 'book' => '#E9E2CF', 'supper' => '#F3CFC6', 'dinner' => '#F3CFC6', 'food' => '#F3CFC6', 'board' => '#D7E3D0', 'game' => '#D7E3D0', 'dance' => '#F1D2DE'];
            $tintOf = function ($topic) use ($tiles) {
                $t = strtolower($topic ?? '');
                foreach ($tiles as $k => $c) {
                    if (str_contains($t, $k)) { return $c; }
                }
                return '#FFEFC2';
            };
        @endphp
        <section class="mx-auto max-w-6xl px-6 py-16">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-bold uppercase tracking-[0.24em] text-off-black/50">Browse {{ $featuredCity }} by scene</p>
                    <h2 class="mt-2 font-montserrat text-3xl font-black uppercase tracking-tight">Every scene in {{ $featuredCity }}</h2>
                </div>
                <a href="{{ route('directory.city', $featuredCity) }}" class="hidden shrink-0 text-sm font-bold text-off-black hover:text-primary md:inline">See the full ranking &rarr;</a>
            </div>
            <div class="mt-8 grid grid-cols-2 gap-4 md:auto-rows-[176px] md:grid-cols-4">
                @foreach ($bento as $i => $cat)
                    @php
                        $span = $i === 0 ? 'col-span-2 md:col-span-2 md:row-span-2' : ($i === 3 ? 'md:col-span-2' : '');
                        $hasPhoto = ! empty($cat['photo']);
                    @endphp
                    <a href="{{ route('directory.topic', [$featuredCity, $cat['slug']]) }}"
                       class="group relative min-h-[176px] overflow-hidden rounded-3xl border border-off-black/10 {{ $span }}">
                        @if ($hasPhoto)
                            <div class="absolute inset-0 bg-cover bg-center transition duration-500 group-hover:scale-105" style="background-image:url('{{ $cat['photo'] }}')"></div>
                            <div class="absolute inset-0 bg-gradient-to-t from-off-black/85 via-off-black/35 to-off-black/5"></div>
                        @else
                            <div class="absolute inset-0" style="background: {{ $tintOf($cat['topic']) }}"></div>
                        @endif
                        <div class="relative flex h-full flex-col justify-end p-5">
                            <h3 class="font-montserrat {{ $i === 0 ? 'text-3xl md:text-4xl' : 'text-xl' }} font-black uppercase leading-none {{ $hasPhoto ? 'text-white' : 'text-off-black' }}">{{ $cat['label'] }}</h3>
                            <p class="mt-1.5 text-sm font-bold {{ $hasPhoto ? 'text-primary' : 'text-off-black/70' }}">{{ $cat['count'] }} communities &rarr;</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- All cities --}}
    <section class="mx-auto max-w-6xl px-6 pb-16">
        <p class="text-sm font-bold uppercase tracking-[0.24em] text-off-black/50">All cities</p>
        <div class="mt-6 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($cities as $c)
                <a href="{{ route('directory.city', $c['page']->city) }}" class="group rounded-3xl border border-off-black/10 bg-white p-7 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                    <h3 class="font-montserrat text-2xl font-black uppercase tracking-tight">{{ $c['page']->city }}</h3>
                    <p class="mt-2 text-sm font-semibold uppercase tracking-wide text-off-black/40">{{ $c['count'] }} active communities</p>
                    @if ($c['categories']->isNotEmpty())
                        <div class="mt-4 flex flex-wrap gap-1.5">
                            @foreach ($c['categories']->take(4) as $cat)
                                <span class="rounded-full bg-off-black/5 px-2.5 py-0.5 text-[11px] font-semibold text-off-black/50">{{ $cat['label'] }}</span>
                            @endforeach
                        </div>
                    @endif
                    <span class="mt-5 inline-block text-sm font-bold text-off-black transition group-hover:text-primary">See the ranking &rarr;</span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Claim CTA --}}
    <section class="mx-auto max-w-6xl px-6 pb-20">
        <div class="rounded-[2rem] bg-primary/25 p-8 md:flex md:items-center md:justify-between md:gap-10">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.24em] text-off-black/50">Run a community?</p>
                <h2 class="mt-3 font-montserrat text-2xl font-black uppercase leading-tight">Get listed free, and get found by venues.</h2>
                <p class="mt-3 max-w-xl text-off-black/75">Claim your spot in your city's ranking. We feature you and introduce you to venues near you that want to host your events.</p>
            </div>
            <a href="{{ route('directory.how-we-rank') }}" class="mt-6 inline-block shrink-0 rounded-full bg-off-black px-7 py-3 font-bold text-primary transition hover:bg-off-black/90 md:mt-0">How it works</a>
        </div>
    </section>

    @if (! empty($map))
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            (function () {
                var data = @json($map);
                var el = document.getElementById('rankmap');
                if (!window.L || !el || !data.length) { return; }

                var map = L.map('rankmap', { scrollWheelZoom: false, zoomControl: true }).setView([48.5, 6.5], 4);
                L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                    attribution: '&copy; OpenStreetMap &copy; CARTO', subdomains: 'abcd', maxZoom: 19,
                }).addTo(map);

                function chip(count, size) {
                    return L.divIcon({
                        className: '',
                        html: '<div style="display:flex;align-items:center;justify-content:center;width:' + size + 'px;height:' + size + 'px;border-radius:50%;background:#FFD560;color:#1B1F1C;font-weight:800;font-family:Montserrat,sans-serif;font-size:' + (size > 36 ? 14 : 12) + 'px;box-shadow:0 4px 14px rgba(27,31,28,.28);border:2px solid #1B1F1C">' + count + '</div>',
                        iconSize: [size, size], iconAnchor: [size / 2, size / 2],
                    });
                }

                var cityLayer = L.layerGroup().addTo(map);
                var hoodLayer = L.layerGroup();
                var resetBtn = document.getElementById('rankmap-reset');

                function showCities() {
                    map.removeLayer(hoodLayer);
                    cityLayer.addTo(map);
                    resetBtn.style.display = 'none';
                    map.flyTo([48.5, 6.5], 4);
                }
                function showCity(c) {
                    map.removeLayer(cityLayer);
                    hoodLayer.clearLayers();
                    (c.neighbourhoods || []).forEach(function (n) {
                        L.marker([n.lat, n.lng], { icon: chip(n.count, 34) }).addTo(hoodLayer)
                            .bindPopup('<strong>' + n.name + '</strong><br>' + n.count + ' communit' + (n.count === 1 ? 'y' : 'ies') + '<br><a href="' + c.url + '">See ' + c.name + ' ranking &rarr;</a>');
                    });
                    hoodLayer.addTo(map);
                    map.flyTo([c.lat, c.lng], 12);
                    resetBtn.style.display = 'block';
                }
                resetBtn.addEventListener('click', showCities);

                data.forEach(function (c) {
                    var m = L.marker([c.lat, c.lng], { icon: chip(c.count, 44) }).addTo(cityLayer);
                    var hasHoods = c.neighbourhoods && c.neighbourhoods.length;
                    m.bindPopup('<strong>' + c.name + '</strong><br>' + c.count + ' communities<br><a href="' + c.url + '">See ranking &rarr;</a>' + (hasHoods ? '<br><a href="#" data-zoom="1">Explore neighbourhoods &rarr;</a>' : ''));
                    m.on('click', function () { if (hasHoods) { showCity(c); } else { m.openPopup(); } });
                });
            })();
        </script>
    @endif
</x-layouts.marketing-page>
