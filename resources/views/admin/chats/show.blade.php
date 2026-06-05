@extends('admin.layout', ['title' => 'Chat transcript'])

@section('page_title', $thread->name ?? 'Chat transcript')
@section('page_subtitle', 'Operator transcript view (read-only) and moderation.')

@section('page_actions')
    @if (in_array($thread->type->value, ['community_custom', 'event'], true) && ! $thread->trashed())
        <form method="POST" action="{{ route('admin.chats.destroy', $thread) }}" class="d-inline"
            onsubmit="return confirm('Soft-delete this chat thread?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">Delete thread</button>
        </form>
    @endif
@endsection

@section('admin_content')
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        Messages
                        <span class="badge badge-light text-uppercase ml-1">{{ $thread->type->value }}</span>
                        @if ($thread->trashed())
                            <span class="badge badge-danger ml-1">deleted</span>
                        @endif
                    </h3>
                </div>
                <div class="card-body">
                    @forelse ($messages as $message)
                        @php
                            $sender = $message->senderProfile;
                            $senderLabel = $sender?->businessProfile?->name
                                ?? $sender?->communityProfile?->name
                                ?? $sender?->email
                                ?? 'Unknown';
                        @endphp
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <strong>{{ $senderLabel }}</strong>
                                <small class="text-muted">{{ $message->created_at?->format('Y-m-d H:i') }}</small>
                            </div>
                            <div>{{ $message->content }}</div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No messages in this thread.</p>
                    @endforelse
                </div>
                @if ($messages->hasPages())
                    <div class="card-footer clearfix">
                        {{ $messages->links('pagination::bootstrap-4') }}
                    </div>
                @endif
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Participants</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            @forelse ($participants as $participant)
                                @php
                                    $p = $participant['profile'];
                                    $label = $p->businessProfile?->name
                                        ?? $p->communityProfile?->name
                                        ?? $p->email;
                                @endphp
                                <tr>
                                    <td>
                                        {{ $label }}
                                        @if ($participant['banned'])
                                            <span class="badge badge-danger ml-1">banned</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @unless ($participant['banned'])
                                            <form method="POST" action="{{ route('admin.chats.ban', $thread) }}" class="d-inline"
                                                onsubmit="return confirm('Ban this member from the chat?');">
                                                @csrf
                                                <input type="hidden" name="profile_id" value="{{ $p->id }}">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Ban</button>
                                            </form>
                                        @endunless
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="text-center text-muted py-3">No participants.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
