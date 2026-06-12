@extends('admin.layout', ['title' => 'Partner rewards'])

@section('page_title', 'Partner rewards')
@section('page_subtitle', 'The global "Redeem your XP" catalogue. Users spend global XP on these.')

@section('page_actions')
    <a href="{{ route('admin.gamification.partner-rewards.create') }}" class="btn btn-primary">
        <i class="fas fa-plus mr-1"></i>
        New
    </a>
@endsection

@section('admin_content')
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Partner</th>
                            <th class="text-center">Cost (XP)</th>
                            <th class="text-center">Status</th>
                            <th class="text-right pr-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rewards as $reward)
                            <tr>
                                <td>
                                    <div class="font-weight-bold">{{ $reward->title }}</div>
                                    @if ($reward->description)
                                        <small class="text-muted">{{ \Illuminate\Support\Str::limit($reward->description, 80) }}</small>
                                    @endif
                                </td>
                                <td>{{ $reward->partner }}</td>
                                <td class="text-center font-weight-bold">{{ number_format($reward->cost_xp) }}</td>
                                <td class="text-center">
                                    @if ($reward->is_active)
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-right pr-4">
                                    <a href="{{ route('admin.gamification.partner-rewards.edit', $reward) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form method="POST" action="{{ route('admin.gamification.partner-rewards.destroy', $reward) }}" class="d-inline" onsubmit="return confirm('Delete this partner reward?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No partner rewards yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($rewards->hasPages())
            <div class="card-footer clearfix">
                {{ $rewards->links('pagination::bootstrap-4') }}
            </div>
        @endif
    </div>
@endsection
