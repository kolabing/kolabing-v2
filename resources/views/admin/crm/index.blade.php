@extends('admin.layout', ['title' => 'CRM'])

@section('page_title', 'CRM')
@section('page_subtitle', 'Businesses · Communities · Ambassadors — sales pipeline & scoring.')

@section('page_actions')
    <a href="{{ route('admin.crm.create', ['type' => $type]) }}" class="btn btn-primary">
        <i class="fas fa-plus mr-1"></i> New {{ ucfirst($type) }}
    </a>
@endsection

@section('admin_content')
    {{-- Type tabs --}}
    <ul class="nav nav-pills mb-3">
        @foreach (['business' => 'Businesses', 'community' => 'Communities', 'ambassador' => 'Ambassadors'] as $t => $label)
            <li class="nav-item">
                <a class="nav-link {{ $type === $t ? 'active' : '' }}" href="{{ route('admin.crm.index', ['type' => $t]) }}">{{ $label }}</a>
            </li>
        @endforeach
    </ul>

    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center" style="gap:.5rem">
            {{-- Filters --}}
            <form method="GET" action="{{ route('admin.crm.index') }}" class="form-inline" style="gap:.5rem">
                <input type="hidden" name="type" value="{{ $type }}">
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Search name…">
                <select name="owner" class="form-control form-control-sm">
                    <option value="">All owners</option>
                    @foreach ($owners as $o)<option value="{{ $o }}" @selected(($filters['owner'] ?? '') === $o)>{{ $o }}</option>@endforeach
                </select>
                <select name="status" class="form-control form-control-sm">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option>@endforeach
                </select>
                @if ($cities->isNotEmpty())
                    <select name="city" class="form-control form-control-sm">
                        <option value="">All cities</option>
                        @foreach ($cities as $c)<option value="{{ $c }}" @selected(($filters['city'] ?? '') === $c)>{{ $c }}</option>@endforeach
                    </select>
                @endif
                <button class="btn btn-sm btn-outline-secondary">Filter</button>
            </form>

            @if ($type === 'community')
                <a href="{{ route('admin.crm.board') }}" class="btn btn-sm btn-outline-secondary" title="Pipeline board">
                    <i class="fas fa-columns mr-1"></i> Pipeline
                </a>
                <a href="{{ route('admin.crm.index', array_merge(['type' => 'community'], ($workNow ?? false) ? [] : ['work_now' => 1])) }}"
                    class="btn btn-sm {{ ($workNow ?? false) ? 'btn-success' : 'btn-outline-success' }}" title="High/Med confidence, local, fit ≥ 40, not yet contacted">
                    <i class="fas fa-bolt mr-1"></i>{{ ($workNow ?? false) ? 'Work now: ON' : 'Work now' }}
                </a>
            @endif

            {{-- Column picker — native <details> (no JS dependency), saved per admin --}}
            <details class="ml-auto" style="position:relative">
                <summary class="btn btn-sm btn-outline-secondary" style="list-style:none">
                    <i class="fas fa-table-columns mr-1"></i> Columns
                </summary>
                <div class="card card-body p-2 shadow"
                    style="position:absolute; right:0; z-index:1030; min-width:240px; max-height:60vh; overflow:auto">
                    <form method="POST" action="{{ route('admin.crm.columns') }}">
                        @csrf
                        <input type="hidden" name="type" value="{{ $type }}">
                        @foreach ($catalog as $key => [$label, $def, $isMetric])
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="columns[]" value="{{ $key }}"
                                    id="col-{{ $key }}" @checked(in_array($key, $visible, true)) @disabled($key === 'name')>
                                <label class="form-check-label" for="col-{{ $key }}">{{ $label }}</label>
                            </div>
                        @endforeach
                        <button class="btn btn-sm btn-primary btn-block mt-2">Apply</button>
                    </form>
                </div>
            </details>
        </div>

        @if ($cityCounts->isNotEmpty())
            @php
                // City centroids (lat, lng). Add a city here to place it on the map.
                $coords = [
                    'Madrid' => [40.4168, -3.7038], 'Tallinn' => [59.4370, 24.7536],
                    'Berlin' => [52.5200, 13.4050], 'Paris' => [48.8566, 2.3522],
                    'Lisbon' => [38.7223, -9.1393], 'Amsterdam' => [52.3676, 4.9041],
                    'Warsaw' => [52.2297, 21.0122], 'Barcelona' => [41.3874, 2.1686],
                    'London' => [51.5072, -0.1276], 'Milan' => [45.4642, 9.1900],
                ];
                $mapData = [];
                foreach ($cityCounts as $c => $n) {
                    if (isset($coords[$c])) {
                        $active = ($filters['city'] ?? '') === $c;
                        $mapData[] = [
                            'city' => $c, 'lat' => $coords[$c][0], 'lng' => $coords[$c][1], 'n' => $n, 'active' => $active,
                            'url' => route('admin.crm.index', ['type' => $type, 'city' => $active ? null : $c]),
                        ];
                    }
                }
                $unmapped = collect($cityCounts->keys())->reject(fn ($c) => isset($coords[$c]));
            @endphp
            <div class="card-body border-bottom">
                <details open>
                    <summary style="cursor:pointer; list-style:none" class="text-muted mb-2">
                        <i class="fas fa-map-marked-alt mr-1"></i> Map — {{ ucfirst($type) }} by city
                        ({{ $cityCounts->count() }} cities, {{ $cityCounts->sum() }} total). Click a marker to filter.
                    </summary>
                    @if (count($mapData))
                        <link rel="stylesheet" href="{{ asset('webapp-assets/leaflet/leaflet.css') }}">
                        <div id="crm-map" style="height:420px; border-radius:8px; z-index:0"></div>
                        <script src="{{ asset('webapp-assets/leaflet/leaflet.js') }}"></script>
                        <script>
                            (function () {
                                var pts = @json($mapData, JSON_UNESCAPED_SLASHES);
                                if (!window.L || !pts.length) { return; }
                                var map = L.map('crm-map', { scrollWheelZoom: false });
                                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                    maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
                                }).addTo(map);
                                var maxN = Math.max.apply(null, pts.map(function (p) { return p.n; })) || 1;
                                var bounds = [];
                                pts.forEach(function (p) {
                                    var r = 10 + Math.sqrt(p.n / maxN) * 22;
                                    var m = L.circleMarker([p.lat, p.lng], {
                                        radius: r, color: '#fff', weight: 2,
                                        fillColor: p.active ? '#dc3545' : '#007bff', fillOpacity: 0.85
                                    }).addTo(map);
                                    m.bindTooltip(p.city + ' (' + p.n + ')', { permanent: true, direction: 'top', opacity: 0.9 });
                                    m.on('click', function () { window.location = p.url; });
                                    bounds.push([p.lat, p.lng]);
                                });
                                map.fitBounds(bounds, { padding: [40, 40], maxZoom: 6 });
                            })();
                        </script>
                    @endif
                    @if ($unmapped->isNotEmpty())
                        <div class="mt-2">
                            <span class="text-muted small mr-1">Other (no map coords):</span>
                            @foreach ($unmapped as $city)
                                <a href="{{ route('admin.crm.index', ['type' => $type, 'city' => $city]) }}"
                                    class="badge {{ ($filters['city'] ?? '') === $city ? 'badge-danger' : 'badge-info' }}">{{ $city }} ({{ $cityCounts[$city] }})</a>
                            @endforeach
                        </div>
                    @endif
                    @if ($filters['city'] ?? false)
                        <a href="{{ route('admin.crm.index', ['type' => $type]) }}" class="btn btn-sm btn-outline-secondary mt-2">Clear city filter ({{ $filters['city'] }})</a>
                    @endif
                </details>
            </div>
        @endif

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            @foreach ($visible as $key)<th>{{ $catalog[$key][0] }}</th>@endforeach
                            <th class="text-right pr-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($accounts as $a)
                            <tr>
                                @foreach ($visible as $key)
                                    <td>
                                        @switch($key)
                                            @case('name')
                                                <a href="{{ route('admin.crm.show', $a) }}" class="font-weight-bold">{{ $a->name }}</a>
                                                @if ($a->isTrialCandidate()) <span title="Trial-Kolab candidate">🔥</span>@endif
                                                @if ($a->needsFollowUp()) <span title="No contact 14d+">⚠️</span>@endif
                                                @break
                                            @case('status')
                                                @if ($type === 'community')
                                                    @include('admin.crm.partials.stage', ['stage' => $a->currentStage(), 'small' => true])
                                                @else
                                                    <span class="badge badge-light text-uppercase">{{ $a->status ?: '—' }}</span>
                                                @endif
                                                @break
                                            @case('score')
                                                <span class="badge {{ $a->score >= 80 ? 'badge-success' : ($a->score >= 50 ? 'badge-warning' : 'badge-secondary') }}">{{ $a->score }}</span>
                                                @break
                                            @case('last_activity_at')
                                                {{ $a->last_activity_at?->format('d M Y') ?? '—' }}
                                                @break
                                            @case('instagram_handle')
                                                {{ $a->instagram_handle ?: '—' }}
                                                @break
                                            @case('evidence_url')
                                                @php $ev = $a->metrics['evidence_url'] ?? null; @endphp
                                                @if ($ev)
                                                    <a href="{{ $ev }}" target="_blank" rel="noopener" title="{{ $ev }}">{{ parse_url($ev, PHP_URL_HOST) ?: 'link' }}</a>
                                                @else — @endif
                                                @break
                                            @default
                                                @php
                                                    $v = $catalog[$key][2] ? ($a->metrics[$key] ?? null) : $a->{$key};
                                                    if (is_bool($v)) { $v = $v ? '✓' : '—'; }
                                                    elseif ($key === 'followers' && is_numeric($v)) { $v = number_format((int) $v); }
                                                @endphp
                                                {{ ($v === null || $v === '') ? '—' : $v }}
                                        @endswitch
                                    </td>
                                @endforeach
                                <td class="text-right pr-3 text-nowrap">
                                    <a href="{{ route('admin.crm.edit', $a) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form method="POST" action="{{ route('admin.crm.destroy', $a) }}" class="d-inline" onsubmit="return confirm('Delete {{ $a->name }}?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($visible) + 1 }}" class="text-center text-muted py-4">No accounts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">{{ $accounts->links() }}</div>
    </div>
@endsection
