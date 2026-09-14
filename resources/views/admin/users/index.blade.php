@extends('admin.layout', ['title' => 'Users'])

@section('page_title', 'Users')
@section('page_subtitle', 'Manage application profiles from a fixed maintainer panel.')

@section('page_actions')
    <a href="{{ route('admin.users.quick-add') }}" class="btn btn-success mr-2">
        <i class="fas fa-bolt mr-1"></i>
        Quick Add
    </a>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
        <i class="fas fa-user-plus mr-1"></i>
        Create User
    </a>
@endsection

@section('admin_content')
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center" style="gap:.5rem">
            <form method="GET" action="{{ route('admin.users.index') }}" class="form-inline" style="gap:.5rem">
                <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="Search name or email…">
                <select name="user_type" class="form-control form-control-sm">
                    <option value="">All types</option>
                    @foreach ($userTypes as $type)
                        <option value="{{ $type->value }}" @selected($filters['user_type'] === $type->value)>{{ ucfirst($type->value) }}</option>
                    @endforeach
                </select>
                @if ($cities->isNotEmpty())
                    <select name="city_id" class="form-control form-control-sm">
                        <option value="">All cities</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city->id }}" @selected($filters['city_id'] === $city->id)>{{ $city->name }}</option>
                        @endforeach
                    </select>
                @endif
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="show_test" name="show_test" value="1" @checked($filters['show_test'])>
                    <label class="custom-control-label" for="show_test">Show test accounts</label>
                </div>
                <button class="btn btn-sm btn-outline-secondary">Filter</button>
                @if ($filters['q'] || $filters['user_type'] || $filters['city_id'] || $filters['show_test'])
                    <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-link">Clear</a>
                @endif
            </form>
        </div>

        @if ($cityCounts->isNotEmpty())
            @php
                // Active-city centroids (lat, lng) — mirrors CitySeeder::getActiveCities().
                // Add a city here when it goes active there, or it falls into "Other" below.
                $coords = [
                    'Barcelona' => [41.3874, 2.1686], 'Madrid' => [40.4168, -3.7038],
                    'Valencia' => [39.4699, -0.3763], 'Sevilla' => [37.3891, -5.9845],
                    'Bilbao' => [43.2630, -2.9350], 'Mexico City' => [19.4326, -99.1332],
                    'Tallinn' => [59.4370, 24.7536], 'Berlin' => [52.5200, 13.4050],
                    'Paris' => [48.8566, 2.3522], 'Warsaw' => [52.2297, 21.0122],
                ];
                $mapData = [];
                foreach ($cityCounts as $row) {
                    if (isset($coords[$row['name']])) {
                        $active = ($filters['city_id'] ?? null) === $row['id'];
                        $mapData[] = [
                            'city' => $row['name'], 'lat' => $coords[$row['name']][0], 'lng' => $coords[$row['name']][1],
                            'n' => $row['n'], 'active' => $active,
                            'url' => route('admin.users.index', array_merge($filters, ['city_id' => $active ? null : $row['id']])),
                        ];
                    }
                }
                $unmapped = $cityCounts->reject(fn ($row) => isset($coords[$row['name']]));
            @endphp
            <div class="card-body border-bottom">
                <details>
                    <summary style="cursor:pointer; list-style:none" class="text-muted mb-2">
                        <i class="fas fa-map-marked-alt mr-1"></i> Map — listings by city
                        ({{ $cityCounts->count() }} cities, {{ $cityCounts->sum('n') }} total). Click a marker to filter.
                    </summary>
                    @if (count($mapData))
                        <link rel="stylesheet" href="{{ asset('webapp-assets/leaflet/leaflet.css') }}">
                        <div id="users-map" style="height:380px; border-radius:8px; z-index:0"></div>
                        <script src="{{ asset('webapp-assets/leaflet/leaflet.js') }}"></script>
                        <script>
                            (function () {
                                var pts = @json($mapData, JSON_UNESCAPED_SLASHES);
                                if (!window.L || !pts.length) { return; }
                                var map = L.map('users-map', { scrollWheelZoom: false });
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
                            @foreach ($unmapped as $row)
                                <span class="badge badge-info">{{ $row['name'] }} ({{ $row['n'] }})</span>
                            @endforeach
                        </div>
                    @endif
                </details>
            </div>
        @endif
    </div>

    {{-- The bulk bar and the row checkboxes are one form (#256). Two submit
         buttons with their own formaction, so choosing an action needs no
         JavaScript — the script below only adds select-all, the live count and
         the disabled state. With JS off, ticking boxes and pressing a button
         still works. The per-row Edit / Deactivate / Delete forms live OUTSIDE
         this form, via the form="" attribute, because HTML forbids nesting. --}}
    <form method="POST" id="bulk-users-form" action="{{ route('admin.users.bulk-deactivate') }}">
        @csrf

        <div class="card mb-3" id="bulk-bar">
            <div class="card-body py-2 d-flex flex-wrap align-items-center">
                <span class="mr-3 text-muted" id="bulk-count">No accounts selected</span>

                <button type="submit"
                        formaction="{{ route('admin.users.bulk-activate') }}"
                        class="btn btn-sm btn-outline-success mr-2"
                        data-bulk-submit>
                    Activate selected
                </button>

                <button type="submit"
                        formaction="{{ route('admin.users.bulk-deactivate') }}"
                        class="btn btn-sm btn-outline-danger"
                        data-bulk-submit
                        data-confirm-deactivate>
                    Deactivate selected
                </button>

                <small class="text-muted ml-auto">Selection applies to this page only.</small>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 2.5rem;">
                                    <input type="checkbox" id="bulk-select-all" aria-label="Select every account on this page">
                                </th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Type</th>
                                <th>City</th>
                                <th>Phone</th>
                                <th>Verified</th>
                                <th>Status</th>
                                <th class="text-right pr-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($profiles as $profile)
                                @php
                                    $label = $profile->businessProfile?->name
                                        ?? $profile->communityProfile?->name
                                        ?? $profile->email;
                                    $cityName = $profile->businessProfile?->city?->name
                                        ?? $profile->communityProfile?->city?->name;
                                @endphp
                                <tr class="{{ $profile->is_active ? '' : 'table-secondary text-muted' }}">
                                    <td>
                                        <input type="checkbox"
                                               name="profile_ids[]"
                                               value="{{ $profile->id }}"
                                               class="bulk-select-row"
                                               aria-label="Select {{ $label }}">
                                    </td>
                                    <td>
                                        <div class="font-weight-bold">{{ $label }}</div>
                                        <small class="text-muted">{{ $profile->id }}</small>
                                    </td>
                                    <td>{{ $profile->email }}</td>
                                    <td>
                                        <span class="badge badge-light text-uppercase">{{ $profile->user_type->value }}</span>
                                    </td>
                                    <td>{{ $cityName ?? '—' }}</td>
                                    <td>{{ $profile->phone_number ?: '—' }}</td>
                                    <td>
                                        <span class="badge {{ $profile->email_verified_at ? 'badge-success' : 'badge-secondary' }}">
                                            {{ $profile->email_verified_at ? 'Verified' : 'Pending' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $profile->is_active ? 'badge-success' : 'badge-danger' }}">
                                            {{ $profile->is_active ? 'Active' : 'Passive' }}
                                        </span>
                                    </td>
                                    <td class="text-right pr-4">
                                        <a href="{{ route('admin.users.edit', $profile) }}" class="btn btn-sm btn-outline-primary">Edit</a>

                                        @if ($profile->is_active)
                                            <button type="submit"
                                                    form="row-deactivate-{{ $profile->id }}"
                                                    class="btn btn-sm btn-outline-danger"
                                                    title="Hide from the app and block sign-in">Deactivate</button>
                                        @else
                                            <button type="submit"
                                                    form="row-activate-{{ $profile->id }}"
                                                    class="btn btn-sm btn-outline-success"
                                                    title="Restore full access">Activate</button>
                                        @endif

                                        @if ($profile->user_type->value === 'business')
                                            @php $subActive = $profile->subscription?->status?->value === 'active'; @endphp
                                            @if ($subActive)
                                                <button type="submit"
                                                        form="row-sub-revoke-{{ $profile->id }}"
                                                        class="btn btn-sm btn-outline-warning"
                                                        title="Revoke subscription">Revoke sub</button>
                                            @else
                                                <button type="submit"
                                                        form="row-sub-grant-{{ $profile->id }}"
                                                        class="btn btn-sm btn-outline-success"
                                                        title="Grant 12 months of access">Grant sub</button>
                                            @endif
                                        @endif

                                        <button type="submit"
                                                form="row-destroy-{{ $profile->id }}"
                                                class="btn btn-sm btn-outline-danger">Delete</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No users found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($profiles->hasPages())
                <div class="card-footer clearfix">
                    {{ $profiles->links('pagination::bootstrap-4') }}
                </div>
            @endif
        </div>
    </form>

    {{-- Per-row forms, kept out of the bulk form because HTML does not allow a
         form inside a form. The buttons above reference them by id. --}}
    @foreach ($profiles as $profile)
        <form method="POST" id="row-deactivate-{{ $profile->id }}" action="{{ route('admin.users.deactivate', $profile) }}"
              onsubmit="return confirm('Deactivate this account? It disappears from the app and cannot sign in. Nothing is deleted, and you can switch it back on.');">
            @csrf
        </form>
        <form method="POST" id="row-activate-{{ $profile->id }}" action="{{ route('admin.users.activate', $profile) }}">
            @csrf
        </form>
        @if ($profile->user_type->value === 'business')
            <form method="POST" id="row-sub-grant-{{ $profile->id }}" action="{{ route('admin.users.subscription.grant', $profile) }}">
                @csrf
            </form>
            <form method="POST" id="row-sub-revoke-{{ $profile->id }}" action="{{ route('admin.users.subscription.revoke', $profile) }}"
                  onsubmit="return confirm('Revoke subscription access for this user?');">
                @csrf
            </form>
        @endif
        <form method="POST" id="row-destroy-{{ $profile->id }}" action="{{ route('admin.users.destroy', $profile) }}"
              onsubmit="return confirm('Delete this user? They will be soft-deleted.');">
            @csrf
            @method('DELETE')
        </form>
    @endforeach

    <script>
        (function () {
            var form = document.getElementById('bulk-users-form');
            if (!form) { return; }

            var selectAll = document.getElementById('bulk-select-all');
            var rows = Array.prototype.slice.call(form.querySelectorAll('.bulk-select-row'));
            var counter = document.getElementById('bulk-count');
            var buttons = Array.prototype.slice.call(form.querySelectorAll('[data-bulk-submit]'));

            function selected() {
                return rows.filter(function (row) { return row.checked; });
            }

            function sync() {
                var n = selected().length;

                counter.textContent = n === 0
                    ? 'No accounts selected'
                    : n + (n === 1 ? ' account selected' : ' accounts selected');

                buttons.forEach(function (button) { button.disabled = n === 0; });

                if (selectAll) {
                    selectAll.checked = n > 0 && n === rows.length;
                    // Neither all nor none: show the third state rather than lying.
                    selectAll.indeterminate = n > 0 && n < rows.length;
                }
            }

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    rows.forEach(function (row) { row.checked = selectAll.checked; });
                    sync();
                });
            }

            rows.forEach(function (row) { row.addEventListener('change', sync); });

            form.addEventListener('submit', function (event) {
                var n = selected().length;

                if (n === 0) {
                    event.preventDefault();
                    return;
                }

                // event.submitter is the button that was pressed, so the confirm
                // names the real count and only guards the destructive action.
                var submitter = event.submitter;
                if (submitter && submitter.hasAttribute('data-confirm-deactivate')) {
                    var message = n === 1
                        ? 'Deactivate 1 account? It disappears from the app and cannot sign in. Nothing is deleted.'
                        : 'Deactivate ' + n + ' accounts? They disappear from the app and cannot sign in. Nothing is deleted.';

                    if (!window.confirm(message)) {
                        event.preventDefault();
                    }
                }
            });

            sync();
        })();
    </script>
@endsection
