@extends('admin.layout', ['title' => 'Kolabs'])

@section('page_title', 'Kolabs')
@section('page_subtitle', 'Review, edit, and delete collaboration offers.')

@section('admin_content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.kolabs.index') }}" class="form-row align-items-end">
                <div class="form-group col-md-5">
                    <label for="q" class="small text-muted mb-1">Search</label>
                    <input type="text" name="q" id="q" value="{{ $filters['q'] }}" class="form-control" placeholder="Title or city…">
                </div>
                <div class="form-group col-md-4">
                    <label for="status" class="small text-muted mb-1">Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="">All</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" {{ $filters['status'] === $status->value ? 'selected' : '' }}>
                                {{ ucfirst($status->value) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <button type="submit" class="btn btn-primary btn-block">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Creator</th>
                            <th>City</th>
                            <th>Intent</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-right pr-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($kolabs as $kolab)
                            @php
                                $creator = $kolab->creatorProfile;
                                $creatorLabel = $creator?->businessProfile?->name
                                    ?? $creator?->communityProfile?->name
                                    ?? $creator?->email
                                    ?? '—';
                                $statusClass = match ($kolab->status->value) {
                                    'published' => 'badge-success',
                                    'closed' => 'badge-secondary',
                                    default => 'badge-warning',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <div class="font-weight-bold">{{ $kolab->title }}</div>
                                    <small class="text-muted">{{ $kolab->id }}</small>
                                </td>
                                <td>{{ $creatorLabel }}</td>
                                <td>{{ $kolab->preferred_city }}</td>
                                <td>
                                    <span class="badge badge-light text-uppercase">{{ str_replace('_', ' ', $kolab->intent_type->value) }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $statusClass }} text-uppercase">{{ $kolab->status->value }}</span>
                                </td>
                                <td>{{ $kolab->created_at?->toDateString() ?? '—' }}</td>
                                <td class="text-right pr-4">
                                    <a href="{{ route('admin.kolabs.edit', $kolab) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form method="POST" action="{{ route('admin.kolabs.destroy', $kolab) }}" class="d-inline" onsubmit="return confirm('Delete this Kolab? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No Kolabs found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($kolabs->hasPages())
            <div class="card-footer clearfix">
                {{ $kolabs->links('pagination::bootstrap-4') }}
            </div>
        @endif
    </div>
@endsection
