@extends('admin.layout', ['title' => 'Edit User'])

@section('page_title', 'Edit User')
@section('page_subtitle', 'Update the selected application profile without changing API-side workflows.')

@section('page_actions')
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary mr-2">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Users
    </a>

    @if ($profile->user_type->value === 'business')
        @php $subActive = $profile->subscription?->status?->value === 'active'; @endphp
        @if ($subActive)
            <form method="POST" action="{{ route('admin.users.subscription.revoke', $profile) }}" class="d-inline mr-2" onsubmit="return confirm('Revoke subscription access for this user?');">
                @csrf
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-ban mr-1"></i>
                    Revoke subscription
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.users.subscription.grant', $profile) }}" class="d-inline mr-2">
                @csrf
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check mr-1"></i>
                    Grant subscription (12mo)
                </button>
            </form>
        @endif
    @endif

    <form method="POST" action="{{ route('admin.users.destroy', $profile) }}" class="d-inline" onsubmit="return confirm('Delete this user? They will be soft-deleted.');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger">
            <i class="fas fa-trash mr-1"></i>
            Delete user
        </button>
    </form>
@endsection

@section('admin_content')
    @if ($profile->user_type->value === 'business' && $profile->subscription)
        @php
            $sub = $profile->subscription;
            $statusClass = match ($sub->status->value) {
                'active' => 'success',
                'past_due' => 'danger',
                'cancelled' => 'secondary',
                default => 'warning',
            };
        @endphp
        <div class="alert alert-{{ $statusClass }}">
            <strong>Subscription:</strong>
            {{ $sub->status->label() }}
            <span class="text-muted">(source: {{ $sub->source->value }})</span>
            @if ($sub->current_period_end)
                · ends {{ $sub->current_period_end->toDayDateTimeString() }}
            @endif
        </div>
    @endif

    <div class="card card-primary card-outline">
        <form method="post" action="{{ route('admin.users.update', $profile) }}">
            @csrf
            @method('PUT')
            <div class="card-body">
                @php($isEdit = true)
                @php($userTypes = [])
                @include('admin.users.form')
            </div>

            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-default">Back</a>
            </div>
        </form>
    </div>
@endsection
