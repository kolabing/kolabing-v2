@php
    use App\Services\Admin\KolabLifecycleService;

    $lifecycle = $summary['lifecycle'];
    $counts = $summary['counts'];
    $collaboration = $summary['collaboration'];
    $reviews = $summary['reviews'];
    $averageRating = $summary['average_rating'];
    $recentApplications = $summary['recent_applications'];
@endphp

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Lifecycle</h3>
        <span class="badge {{ KolabLifecycleService::badgeClass($lifecycle) }} text-uppercase">
            {{ KolabLifecycleService::label($lifecycle) }}
        </span>
    </div>

    <div class="card-body">
        {{-- Applications --}}
        <h5 class="mb-2">Applications</h5>
        <div class="row text-center mb-3">
            <div class="col">
                <div class="text-muted small">Pending</div>
                <div class="h4 mb-0">{{ $counts['pending'] }}</div>
            </div>
            <div class="col">
                <div class="text-muted small">Accepted</div>
                <div class="h4 mb-0 text-success">{{ $counts['accepted'] }}</div>
            </div>
            <div class="col">
                <div class="text-muted small">Declined</div>
                <div class="h4 mb-0">{{ $counts['declined'] }}</div>
            </div>
            <div class="col">
                <div class="text-muted small">Withdrawn</div>
                <div class="h4 mb-0">{{ $counts['withdrawn'] }}</div>
            </div>
        </div>

        @if ($recentApplications->isEmpty())
            <p class="text-muted small mb-0">Nobody has applied yet.</p>
        @else
            <div class="table-responsive mb-3">
                <table class="table table-sm table-borderless mb-0">
                    <thead>
                        <tr class="text-muted">
                            <th class="font-weight-normal small">Applicant</th>
                            <th class="font-weight-normal small">Status</th>
                            <th class="font-weight-normal small">When</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentApplications as $app)
                            @php
                                $applicant = $app->applicantProfile;
                                $applicantLabel = $applicant?->businessProfile?->name
                                    ?? $applicant?->communityProfile?->name
                                    ?? $applicant?->email
                                    ?? '—';
                                $appBadge = match ($app->status->value) {
                                    'accepted' => 'badge-success',
                                    'declined' => 'badge-secondary',
                                    'withdrawn' => 'badge-light',
                                    default => 'badge-warning',
                                };
                            @endphp
                            <tr>
                                <td>{{ $applicantLabel }}</td>
                                <td><span class="badge {{ $appBadge }} text-uppercase">{{ $app->status->value }}</span></td>
                                <td class="text-muted small">{{ $app->created_at?->diffForHumans() ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Collaboration --}}
        <h5 class="mb-2 mt-4">Collaboration</h5>
        @if ($collaboration === null)
            <p class="text-muted small mb-0">No collaboration yet — no accepted match has produced an active deal.</p>
        @else
            @php
                $business = $collaboration->businessProfile;
                $community = $collaboration->communityProfile;
                $collabBadge = match ($collaboration->status->value) {
                    'active' => 'badge-success',
                    'scheduled' => 'badge-info',
                    'completed' => 'badge-dark',
                    'cancelled' => 'badge-danger',
                    default => 'badge-light',
                };
                $canCancel = in_array($collaboration->status->value, ['scheduled', 'active'], true);
            @endphp
            <dl class="row mb-2">
                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9"><span class="badge {{ $collabBadge }} text-uppercase">{{ $collaboration->status->value }}</span></dd>
                <dt class="col-sm-3">Scheduled date</dt>
                <dd class="col-sm-9">{{ $collaboration->scheduled_date?->toFormattedDateString() ?? '—' }}</dd>
                <dt class="col-sm-3">Completed at</dt>
                <dd class="col-sm-9">{{ $collaboration->completed_at?->toDayDateTimeString() ?? '—' }}</dd>
                <dt class="col-sm-3">Business side</dt>
                <dd class="col-sm-9">
                    @if ($business)
                        {{ $business->name ?? '—' }}
                        <span class="text-muted small">{{ $business->profile?->email ? '· '.$business->profile->email : '' }}</span>
                    @else
                        —
                    @endif
                </dd>
                <dt class="col-sm-3">Community side</dt>
                <dd class="col-sm-9">
                    @if ($community)
                        {{ $community->name ?? '—' }}
                        <span class="text-muted small">{{ $community->profile?->email ? '· '.$community->profile->email : '' }}</span>
                    @else
                        —
                    @endif
                </dd>
                @if ($collaboration->contact_methods)
                    <dt class="col-sm-3">Contact methods</dt>
                    <dd class="col-sm-9 small text-muted">{{ implode(', ', array_keys(array_filter($collaboration->contact_methods))) }}</dd>
                @endif
                @if ($collaboration->event_id)
                    <dt class="col-sm-3">Event linked</dt>
                    <dd class="col-sm-9 small text-muted">{{ $collaboration->event_id }}</dd>
                @endif
                @if ($collaboration->qr_code_url)
                    <dt class="col-sm-3">QR</dt>
                    <dd class="col-sm-9 small"><a href="{{ $collaboration->qr_code_url }}" target="_blank" rel="noopener">{{ $collaboration->qr_code_url }}</a></dd>
                @endif
            </dl>

            @if ($canCancel)
                <form method="POST" action="{{ route('admin.kolabs.collaboration.cancel', $kolab) }}" class="mt-3"
                      onsubmit="return confirm('Force-cancel this collaboration? Both parties will be affected.');">
                    @csrf
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-9 mb-0">
                            <label for="cancel-reason" class="small text-muted mb-1">Cancellation reason (required)</label>
                            <input type="text" name="reason" id="cancel-reason" class="form-control" minlength="3" maxlength="500" required placeholder="Why is this being cancelled?">
                            @error('reason')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="form-group col-md-3 mb-0">
                            <button type="submit" class="btn btn-outline-danger btn-block">
                                <i class="fas fa-ban mr-1"></i>
                                Force-cancel
                            </button>
                        </div>
                    </div>
                </form>
            @endif
        @endif

        {{-- Reviews --}}
        @if ($collaboration !== null)
            <h5 class="mb-2 mt-4">Reviews</h5>
            @if ($reviews->isEmpty())
                <p class="text-muted small mb-0">No reviews submitted yet.</p>
            @else
                <p class="mb-2 small">
                    {{ $reviews->count() }} review{{ $reviews->count() === 1 ? '' : 's' }}
                    @if ($averageRating !== null)
                        · average <strong>★ {{ number_format($averageRating, 1) }}</strong>
                    @endif
                </p>
                <ul class="list-unstyled mb-0">
                    @foreach ($reviews as $review)
                        @php
                            $reviewText = $review->body ?: $review->note;
                            $roleLabel = $review->reviewer_role === 'business' ? 'Business → Community' : 'Community → Business';
                        @endphp
                        <li class="mb-2 pl-3 border-left">
                            <div class="small text-muted">{{ $roleLabel }} · ★ {{ $review->rating ?? '—' }}</div>
                            @if ($reviewText)
                                <div class="small">{{ $reviewText }}</div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </div>
</div>
