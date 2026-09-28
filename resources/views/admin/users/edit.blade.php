@extends('admin.layout', ['title' => 'Edit User'])

@section('page_title', 'Edit User')
@section('page_subtitle', 'Update the selected application profile without changing API-side workflows.')

@section('page_actions')
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary mr-2">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Users
    </a>

    @if ($profile->trashed())
        <form method="POST" action="{{ route('admin.users.restore', $profile) }}" class="d-inline" onsubmit="return confirm('Restore this account? It comes back exactly as it was before the delete.');">
            @csrf
            <button type="submit" class="btn btn-success">
                <i class="fas fa-undo mr-1"></i>
                Restore account
            </button>
        </form>
    @else
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
            <form method="POST" action="{{ route('admin.users.subscription.grant', $profile) }}" class="d-inline mr-2">
                @csrf
                <input type="hidden" name="plan" value="pro">
                <button type="submit" class="btn btn-outline-success">
                    <i class="fas fa-hotel mr-1"></i>
                    Grant Venue Pro (12mo)
                </button>
            </form>
        @endif
    @endif

    @if (in_array($profile->user_type->value, ['business', 'community'], true))
        <a href="{{ \App\Support\PublicProfileLink::urlFor($profile) }}" target="_blank" rel="noopener" class="btn btn-outline-primary mr-2">
            <i class="fas fa-external-link-alt mr-1"></i>
            View public profile
        </a>
    @endif

    <form method="POST" action="{{ route('admin.users.destroy', $profile) }}" class="d-inline" onsubmit="return confirm('Delete this user? They will be soft-deleted.');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger">
            <i class="fas fa-trash mr-1"></i>
            Delete user
        </button>
    </form>
    @endif
@endsection

@section('admin_content')
    @if ($profile->trashed())
        <div class="alert alert-dark">
            <i class="fas fa-trash mr-1"></i>
            <strong>Deleted</strong> on {{ $profile->deleted_at->toDayDateTimeString() }}.
            It is hidden from the app and cannot sign in. Restore it to edit it again.
        </div>
    @endif

    @php
        $kolabTotal = $kolabCounts->sum();
    @endphp
    <div class="card card-outline card-secondary">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-id-card mr-1"></i> Account</h3>
        </div>
        <div class="card-body py-2">
            <dl class="row mb-0">
                <dt class="col-sm-3">Profile ID</dt>
                <dd class="col-sm-9"><code>{{ $profile->id }}</code></dd>
                <dt class="col-sm-3">Created</dt>
                <dd class="col-sm-9">{{ $profile->created_at?->toDayDateTimeString() ?? '—' }}</dd>
                <dt class="col-sm-3">Kolabs</dt>
                <dd class="col-sm-9">
                    {{ $kolabTotal }}
                    @if ($kolabTotal > 0)
                        @foreach (\App\Enums\KolabStatus::cases() as $kolabStatus)
                            @if (($kolabCounts[$kolabStatus->value] ?? 0) > 0)
                                <span class="badge badge-light ml-1">{{ ucfirst($kolabStatus->value) }}: {{ $kolabCounts[$kolabStatus->value] }}</span>
                            @endif
                        @endforeach
                    @endif
                </dd>
            </dl>
        </div>
    </div>

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

    @if ($profile->user_type->value === 'community' && ! $profile->trashed())
        @include('admin.users._verification', ['profile' => $profile])
    @endif

    @if (in_array($profile->user_type->value, ['business', 'community'], true) && ! $profile->trashed())
        <div class="card card-outline card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-envelope mr-1"></i> Send welcome email</h3>
            </div>
            <div class="card-body">
                @if ($welcomeEmailLocales->isEmpty())
                    <p class="text-muted mb-0">
                        No languages set up yet.
                        <a href="{{ route('admin.email-templates.create') }}">Add one</a> to start sending.
                    </p>
                @else
                    <form method="GET" action="{{ route('admin.users.welcome-email-preview', $profile) }}" class="form-inline">
                        <label for="locale" class="mr-2">Language</label>
                        <select id="locale" name="locale" class="form-control mr-2" required>
                            @foreach ($welcomeEmailLocales as $locale)
                                <option value="{{ $locale->locale }}">{{ $locale->label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-eye mr-1"></i>
                            Preview
                        </button>
                    </form>
                    <small class="form-text text-muted mt-2">Shows you exactly what will be sent — nothing sends until you confirm on that screen.</small>
                @endif
            </div>
        </div>
    @endif

    <div class="card card-primary card-outline">
        <form method="post" action="{{ route('admin.users.update', $profile) }}">
            @csrf
            @method('PUT')
            <div class="card-body">
                <fieldset class="mb-0" @disabled($profile->trashed())>
                    @php($isEdit = true)
                    @php($userTypes = [])
                    @include('admin.users.form')
                </fieldset>
            </div>

            <div class="card-footer">
                <button type="submit" class="btn btn-primary" @disabled($profile->trashed())>Save Changes</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-default">Back</a>
            </div>
        </form>
    </div>
@endsection
