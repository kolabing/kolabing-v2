@php
    /** @var \App\Models\Profile $profile */
    $cp = $profile->communityProfile;
    $status = $cp?->verification_status ?? \App\Enums\VerificationStatus::Unverified->value;
    $statusEnum = \App\Enums\VerificationStatus::tryFrom($status) ?? \App\Enums\VerificationStatus::Unverified;
    $channels = $cp?->verification_channels ?? [];
    $flagged = $cp?->verification_flagged_at;
@endphp

@if ($cp)
    <div class="card card-outline card-info">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-shield-alt mr-1"></i>
                Verification
            </h3>
            <div class="card-tools">
                <span class="badge badge-{{ $statusEnum->badgeClass() }}">{{ $statusEnum->label() }}</span>
            </div>
        </div>
        <div class="card-body">
            @if ($flagged)
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>Channels changed since verified.</strong>
                    The community edited its proof channels on {{ $flagged->toDayDateTimeString() }}.
                    Re-check and re-verify to clear this flag.
                </div>
            @endif

            @if ($status === \App\Enums\VerificationStatus::Rejected->value && $cp->rejection_reason)
                <div class="alert alert-danger">
                    <strong>Rejected:</strong> {{ $cp->rejection_reason }}
                </div>
            @endif

            @if ($cp->verified_at)
                <p class="text-muted mb-2">
                    Verified {{ $cp->verified_at->toDayDateTimeString() }}
                    @if ($cp->verified_by) (by admin #{{ $cp->verified_by }}) @endif
                </p>
            @endif

            <h6 class="text-muted text-uppercase">Submitted channels</h6>
            @if (count($channels) > 0)
                <ul class="list-unstyled mb-3">
                    @foreach ($channels as $channel)
                        <li class="mb-1">
                            <span class="badge badge-light text-uppercase mr-1">{{ $channel['type'] ?? '—' }}</span>
                            <a href="{{ $channel['url'] ?? '#' }}" target="_blank" rel="noopener noreferrer">
                                {{ $channel['url'] ?? '—' }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-muted mb-3">No channels submitted yet.</p>
            @endif

            <div class="d-flex">
                @if ($status !== \App\Enums\VerificationStatus::Verified->value || $flagged)
                    <form method="POST" action="{{ route('admin.users.verification.verify', $profile) }}" class="mr-2">
                        @csrf
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check mr-1"></i> Verify
                        </button>
                    </form>
                @endif

                <button type="button" class="btn btn-outline-danger" data-toggle="collapse" data-target="#rejectForm-{{ $profile->id }}">
                    <i class="fas fa-times mr-1"></i> Reject
                </button>
            </div>

            <div class="collapse mt-3" id="rejectForm-{{ $profile->id }}">
                <form method="POST" action="{{ route('admin.users.verification.reject', $profile) }}">
                    @csrf
                    <div class="form-group">
                        <label for="rejection_reason-{{ $profile->id }}">Rejection reason</label>
                        <textarea name="rejection_reason" id="rejection_reason-{{ $profile->id }}" class="form-control @error('rejection_reason') is-invalid @enderror" rows="2" minlength="3" maxlength="1000" required>{{ old('rejection_reason') }}</textarea>
                        @error('rejection_reason')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-danger">Confirm rejection</button>
                </form>
            </div>
        </div>
    </div>
@endif
