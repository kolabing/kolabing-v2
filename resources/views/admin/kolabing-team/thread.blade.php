@extends('admin.layout', ['title' => 'Kolabing team thread'])

@section('page_title', 'Kolabing team: '.($thread->teamProfile?->businessProfile?->name ?? $thread->teamProfile?->communityProfile?->name ?? $thread->teamProfile?->email))
@section('page_subtitle', 'What they see in the app, and what they wrote back.')

@section('admin_content')
    <div class="card">
        <div class="card-body">
            @foreach ($messages as $m)
                <div class="mb-3 {{ $m->isFromTeam() ? 'text-right' : '' }}">
                    <small class="text-muted">{{ $m->isFromTeam() ? 'Kolabing team' : ($thread->teamProfile?->email) }} · {{ $m->created_at?->toDayDateTimeString() }}</small>
                    <div class="d-inline-block p-2 {{ $m->isFromTeam() ? 'bg-light' : 'bg-info' }} rounded">{!! nl2br(e($m->content)) !!}</div>
                    @if ($m->teamOffer)
                        <div class="small text-muted">Offer card: {{ $m->teamOffer->title }} — {{ $m->teamOffer->status->value }}</div>
                    @endif
                </div>
            @endforeach
        </div>
        <form method="post" action="{{ route('admin.kolabing-team.reply', $thread) }}">
            @csrf
            <div class="card-footer">
                <textarea name="content" rows="3" class="form-control mb-2" maxlength="2000" required placeholder="Reply as the Kolabing team"></textarea>
                @error('content')<div class="text-danger small">{{ $message }}</div>@enderror
                <button type="submit" class="btn btn-primary">Send reply</button>
            </div>
        </form>
    </div>
@endsection
