@extends('admin.layout', ['title' => 'Kolabing team inbox'])

@section('page_title', 'Kolabing team inbox')
@section('page_subtitle', 'Replies from businesses and communities to the Kolabing team chat.')

@section('admin_content')
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Profile</th><th class="text-center">Replies</th><th>Last message</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($threads as $t)
                            @php($r = $repliesByThread[$t->id] ?? null)
                            <tr>
                                <td>{{ $t->teamProfile?->businessProfile?->name ?? $t->teamProfile?->communityProfile?->name ?? $t->teamProfile?->email }}<br><small class="text-muted">{{ $t->teamProfile?->email }}</small></td>
                                <td class="text-center">{{ $r?->replies ?? 0 }}</td>
                                <td>{{ $t->last_message_at?->toDayDateTimeString() }}</td>
                                <td class="text-right"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.kolabing-team.thread', $t) }}">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No team threads yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
