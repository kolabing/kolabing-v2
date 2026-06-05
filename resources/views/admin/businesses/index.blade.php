@extends('admin.layout', ['title' => 'Businesses'])

@section('page_title', 'Businesses')
@section('page_subtitle', 'Business profiles and their active collaboration chats.')

@section('admin_content')
    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ route('admin.businesses.index') }}" class="form-inline">
                <div class="input-group">
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control"
                        placeholder="Search business name or email">
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </div>
                @if (($filters['q'] ?? '') !== '')
                    <a href="{{ route('admin.businesses.index') }}" class="btn btn-link">Reset</a>
                @endif
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Subscription</th>
                            <th class="text-right pr-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($businesses as $business)
                            @php $subActive = $business->subscription?->status?->value === 'active'; @endphp
                            <tr>
                                <td>
                                    <div class="font-weight-bold">{{ $business->businessProfile?->name ?? $business->email }}</div>
                                    <small class="text-muted">{{ $business->id }}</small>
                                </td>
                                <td>{{ $business->email }}</td>
                                <td>
                                    <span class="badge {{ $subActive ? 'badge-success' : 'badge-secondary' }}">
                                        {{ $subActive ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-right pr-4">
                                    <a href="{{ route('admin.businesses.show', $business) }}" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No businesses found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($businesses->hasPages())
            <div class="card-footer clearfix">
                {{ $businesses->links('pagination::bootstrap-4') }}
            </div>
        @endif
    </div>
@endsection
