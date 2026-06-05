@extends('admin.layout', ['title' => 'Communities'])

@section('page_title', 'Communities')
@section('page_subtitle', 'Every community on the platform, with owner, membership and chat tallies.')

@section('admin_content')
    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ route('admin.communities.index') }}" class="form-inline">
                <div class="input-group">
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control"
                        placeholder="Search name, slug or owner email">
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </div>
                @if (($filters['q'] ?? '') !== '')
                    <a href="{{ route('admin.communities.index') }}" class="btn btn-link">Reset</a>
                @endif
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Owner</th>
                            <th>Type</th>
                            <th class="text-right">Members</th>
                            <th class="text-right">Tiers</th>
                            <th class="text-right">Chats</th>
                            <th class="text-right pr-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($communities as $community)
                            <tr>
                                <td>
                                    <div class="font-weight-bold">{{ $community->name }}</div>
                                    <small class="text-muted">{{ $community->slug }}</small>
                                </td>
                                <td>{{ $community->owner?->email ?? '—' }}</td>
                                <td><span class="badge badge-light text-uppercase">{{ $community->type->value }}</span></td>
                                <td class="text-right">{{ $community->active_members_count }}</td>
                                <td class="text-right">{{ $community->tiers_count }}</td>
                                <td class="text-right">{{ $community->chat_threads_count }}</td>
                                <td class="text-right pr-4">
                                    <a href="{{ route('admin.communities.show', $community) }}" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No communities found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($communities->hasPages())
            <div class="card-footer clearfix">
                {{ $communities->links('pagination::bootstrap-4') }}
            </div>
        @endif
    </div>
@endsection
