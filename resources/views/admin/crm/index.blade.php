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
                <button class="btn btn-sm btn-outline-secondary">Filter</button>
            </form>

            {{-- Column picker (server-side, saved per admin) --}}
            <div class="dropdown ml-auto">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-toggle="dropdown">
                    <i class="fas fa-table-columns mr-1"></i> Columns
                </button>
                <div class="dropdown-menu dropdown-menu-right p-2" style="min-width:230px">
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
            </div>
        </div>

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
