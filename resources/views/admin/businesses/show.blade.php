@extends('admin.layout', ['title' => 'Business: '.($business->businessProfile?->name ?? $business->email)])

@section('page_title', $business->businessProfile?->name ?? $business->email)
@section('page_subtitle', 'Business detail and active collaboration chats.')

@section('page_actions')
    <a href="{{ route('admin.businesses.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Back
    </a>
@endsection

@section('admin_content')
    <div class="card">
        <div class="card-header"><h3 class="card-title">Profile</h3></div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Email</dt><dd class="col-sm-9">{{ $business->email }}</dd>
                <dt class="col-sm-3">Business type</dt><dd class="col-sm-9">{{ $business->businessProfile?->business_type ?? '—' }}</dd>
                <dt class="col-sm-3">Subscription</dt>
                <dd class="col-sm-9">{{ $business->subscription?->status?->value ?? 'none' }}</dd>
            </dl>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Active collaboration chats ({{ $threads->count() }})</h3></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Counterparty</th>
                            <th class="text-right">Messages</th>
                            <th>Last message</th>
                            <th class="text-right pr-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($threads as $thread)
                            @php
                                $creator = $thread->application?->collabOpportunity?->creatorProfile;
                                $applicant = $thread->application?->applicantProfile;
                                $other = $creator?->id === $business->id ? $applicant : $creator;
                                $otherLabel = $other?->businessProfile?->name
                                    ?? $other?->communityProfile?->name
                                    ?? $other?->email
                                    ?? '—';
                            @endphp
                            <tr>
                                <td>{{ $otherLabel }}</td>
                                <td class="text-right">{{ $thread->messages_count }}</td>
                                <td>{{ $thread->last_message_at?->diffForHumans() ?? '—' }}</td>
                                <td class="text-right pr-4">
                                    <a href="{{ route('admin.chats.show', $thread) }}" class="btn btn-sm btn-outline-primary">Transcript</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No active chats.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
