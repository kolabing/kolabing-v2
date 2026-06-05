@extends('admin.layout', ['title' => 'Community: '.$community->name])

@section('page_title', $community->name)
@section('page_subtitle', 'Community detail, tiers, upcoming events and chat threads.')

@section('page_actions')
    <a href="{{ route('admin.communities.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Back
    </a>
@endsection

@section('admin_content')
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Profile</h3></div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>Slug</dt><dd>{{ $community->slug }}</dd>
                        <dt>Type</dt><dd>{{ $community->type->value }}</dd>
                        <dt>Owner</dt><dd>{{ $community->owner?->email ?? '—' }}</dd>
                        <dt>Join policy</dt><dd>{{ $community->join_policy->value }}</dd>
                        <dt>Primary</dt><dd>{{ $community->is_primary ? 'Yes' : 'No' }}</dd>
                        <dt>Active members</dt><dd>{{ $community->memberCount() }}</dd>
                        @if ($community->description)
                            <dt>Description</dt><dd>{{ $community->description }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Tiers ({{ $tiers->count() }})</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Name</th><th>Rank</th><th>Rule</th><th>Default</th></tr></thead>
                        <tbody>
                            @forelse ($tiers as $tier)
                                <tr>
                                    <td>{{ $tier->name }}</td>
                                    <td>{{ $tier->rank }}</td>
                                    <td>{{ $tier->assignment_rule->value }}</td>
                                    <td>{{ $tier->is_default ? 'Yes' : '' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">No tiers.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Upcoming events ({{ $upcomingEvents->count() }})</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Name</th><th>Date</th><th class="text-right">Attendees</th></tr></thead>
                        <tbody>
                            @forelse ($upcomingEvents as $event)
                                <tr>
                                    <td>{{ $event->name }}</td>
                                    <td>{{ $event->event_date }}</td>
                                    <td class="text-right">{{ $event->attendee_count }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">No upcoming events.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Chat threads ({{ $threads->count() }})</h3></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Name</th>
                            <th class="text-right">Messages</th>
                            <th class="text-right">Participants</th>
                            <th>Last message</th>
                            <th class="text-right pr-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($threads as $thread)
                            <tr class="{{ $thread->trashed() ? 'text-muted' : '' }}">
                                <td><span class="badge badge-light text-uppercase">{{ $thread->type->value }}</span></td>
                                <td>
                                    {{ $thread->name ?? '—' }}
                                    @if ($thread->trashed())
                                        <span class="badge badge-danger ml-1">deleted</span>
                                    @endif
                                </td>
                                <td class="text-right">{{ $thread->messages_count }}</td>
                                <td class="text-right">{{ $thread->participants_count }}</td>
                                <td>{{ $thread->last_message_at?->diffForHumans() ?? '—' }}</td>
                                <td class="text-right pr-4">
                                    <a href="{{ route('admin.chats.show', $thread) }}" class="btn btn-sm btn-outline-primary">Transcript</a>
                                    @if (! $thread->trashed() && in_array($thread->type->value, ['community_custom', 'event'], true))
                                        <form method="POST" action="{{ route('admin.chats.destroy', $thread) }}" class="d-inline"
                                            onsubmit="return confirm('Soft-delete this chat thread?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No chat threads.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
