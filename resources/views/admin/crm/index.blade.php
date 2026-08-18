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
                $coords = [
                    'Madrid' => [40.4168, -3.7038], 'Tallinn' => [59.4370, 24.7536],
                    'Berlin' => [52.5200, 13.4050], 'Paris' => [48.8566, 2.3522],
                    'Lisbon' => [38.7223, -9.1393], 'Amsterdam' => [52.3676, 4.9041],
                    'Warsaw' => [52.2297, 21.0122], 'Barcelona' => [41.3874, 2.1686],
                    'London' => [51.5072, -0.1276], 'Milan' => [45.4642, 9.1900],
                ];
                $known = [];
                foreach ($cityCounts as $c => $n) {
                    if (isset($coords[$c])) { $known[$c] = ['ll' => $coords[$c], 'n' => $n]; }
                }
                $unmapped = collect($cityCounts->keys())->reject(fn ($c) => isset($coords[$c]));
                $W = 780; $H = 440; $pad = 70;
                $lats = array_map(fn ($k) => $k['ll'][0], $known);
                $lngs = array_map(fn ($k) => $k['ll'][1], $known);
                $latMin = $known ? min($lats) : 0; $latMax = $known ? max($lats) : 1;
                $lngMin = $known ? min($lngs) : 0; $lngMax = $known ? max($lngs) : 1;
                $latSpan = ($latMax - $latMin) ?: 1; $lngSpan = ($lngMax - $lngMin) ?: 1;
                $maxN = $known ? max(array_map(fn ($k) => $k['n'], $known)) : 1;
                $px = fn ($lng) => $pad + ($lng - $lngMin) / $lngSpan * ($W - 2 * $pad);
                $py = fn ($lat) => $pad + ($latMax - $lat) / $latSpan * ($H - 2 * $pad);
                $rr = fn ($n) => 12 + sqrt($n / max($maxN, 1)) * 16;
            @endphp
            <div class="card-body border-bottom">
                <details open>
                    <summary style="cursor:pointer; list-style:none" class="text-muted mb-2">
                        <i class="fas fa-map-marked-alt mr-1"></i> Map — {{ ucfirst($type) }} by city
                        ({{ $cityCounts->count() }} cities, {{ $cityCounts->sum() }} total). Click a city to filter.
                    </summary>
                    @if (count($known))
                        <svg viewBox="0 0 {{ $W }} {{ $H }}" style="width:100%; max-width:820px; height:auto; background:#f4f6f9; border-radius:8px">
                            @foreach ($known as $city => $d)
                                @php $x = $px($d['ll'][1]); $y = $py($d['ll'][0]); $r = $rr($d['n']);
                                    $active = ($filters['city'] ?? '') === $city; @endphp
                                <a href="{{ route('admin.crm.index', ['type' => $type, 'city' => $active ? null : $city]) }}">
                                    <circle cx="{{ $x }}" cy="{{ $y }}" r="{{ $r }}"
                                        fill="{{ $active ? '#dc3545' : '#007bff' }}" fill-opacity="0.72" stroke="#fff" stroke-width="2"/>
                                    <text x="{{ $x }}" y="{{ $y + 4 }}" text-anchor="middle" font-size="13" font-weight="bold" fill="#fff">{{ $d['n'] }}</text>
                                    <text x="{{ $x }}" y="{{ $y + $r + 14 }}" text-anchor="middle" font-size="12" fill="#2b333b">{{ $city }}</text>
                                </a>
                            @endforeach
                        </svg>
                    @endif
                    @if ($unmapped->isNotEmpty())
                        <div class="mt-2">
                            <span class="text-muted small mr-1">Other:</span>
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
                                                <span class="font-weight-bold">{{ $a->name }}</span>
                                                @if ($a->isTrialCandidate()) <span title="Trial-Kolab candidate">🔥</span>@endif
                                                @if ($a->needsFollowUp()) <span title="No contact 14d+">⚠️</span>@endif
                                                @break
                                            @case('status')
                                                <span class="badge badge-light text-uppercase">{{ $a->status ?: '—' }}</span>
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
