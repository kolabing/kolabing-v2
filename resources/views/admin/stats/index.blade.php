@extends('admin.layout', ['title' => 'Statistics'])

@section('page_title', 'Statistics')
@section('page_subtitle', 'Platform-wide health, funnels, and quality signals.')

@section('page_actions')
    <div class="btn-group" role="group" aria-label="Date range">
        @foreach ($ranges as $r)
            @php
                $label = match ($r) {
                    '7d' => 'Last 7d',
                    '30d' => 'Last 30d',
                    '90d' => 'Last 90d',
                    'all' => 'All time',
                    default => $r,
                };
            @endphp
            <a href="{{ route('admin.stats.index', ['range' => $r]) }}"
               class="btn {{ $range === $r ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
        @endforeach
    </div>
@endsection

@php
    $a = $summary['audience'];
    $k = $summary['kolabs'];
    $apps = $summary['applications'];
    $col = $summary['collaborations'];
    $q = $summary['quality'];
    $f = $summary['funnel'];
    $m = $summary['money'];
    $act = $summary['activity'];

    $fmt = fn ($n) => $n === null ? '—' : (is_numeric($n) ? number_format((float) $n, is_int($n + 0) ? 0 : 1) : $n);
    $pct = fn ($n) => $n === null ? '—' : number_format((float) $n, 1).'%';
@endphp

@section('admin_content')
    <h4 class="mb-3">Audience</h4>
    <div class="row">
        <div class="col-md-3">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">Total profiles</span>
                <span class="info-box-number">{{ $fmt($a['total']) }}</span>
                <span class="text-muted small">+{{ $fmt($a['new_in_range']) }} in range</span>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">Businesses</span>
                <span class="info-box-number">{{ $fmt($a['business']) }}</span>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">Communities</span>
                <span class="info-box-number">{{ $fmt($a['community']) }}</span>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">Attendees</span>
                <span class="info-box-number">{{ $fmt($a['attendee']) }}</span>
                <span class="text-muted small">soft-deleted: {{ $fmt($a['soft_deleted']) }}</span>
            </div></div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="info-box bg-success"><div class="info-box-content">
                <span class="info-box-text">DAU</span>
                <span class="info-box-number">{{ $fmt($act['dau']) }}</span>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="info-box bg-info"><div class="info-box-content">
                <span class="info-box-text">WAU</span>
                <span class="info-box-number">{{ $fmt($act['wau']) }}</span>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="info-box bg-primary"><div class="info-box-content">
                <span class="info-box-text">MAU</span>
                <span class="info-box-number">{{ $fmt($act['mau']) }}</span>
            </div></div>
        </div>
    </div>

    <h4 class="mb-3 mt-3">Funnel — % of users who reach each step</h4>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Step</th>
                            <th class="text-right">Business</th>
                            <th class="text-right">Community</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (['created' => 'Created', 'onboarded' => 'Onboarded', 'published_kolab' => 'Published a kolab', 'applied' => 'Applied', 'accepted' => 'Accepted somewhere', 'collaborated' => 'In a collaboration', 'completed' => 'Completed a collaboration', 'reviewed' => 'Left a review'] as $key => $label)
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="text-right">
                                    @if (isset($f['business'][$key]) && $f['business'][$key] !== null)
                                        {{ $fmt($f['business'][$key]['n']) }}
                                        <span class="text-muted small">({{ $pct($f['business'][$key]['pct']) }})</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if (isset($f['community'][$key]) && $f['community'][$key] !== null)
                                        {{ $fmt($f['community'][$key]['n']) }}
                                        <span class="text-muted small">({{ $pct($f['community'][$key]['pct']) }})</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <h4 class="mb-3 mt-4">Kolabs</h4>
    <div class="row">
        <div class="col-md-3">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">Total</span>
                <span class="info-box-number">{{ $fmt($k['total']) }}</span>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">Draft</span>
                <span class="info-box-number">{{ $fmt($k['draft']) }}</span>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">Published</span>
                <span class="info-box-number">{{ $fmt($k['published']) }}</span>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">Avg time-to-publish</span>
                <span class="info-box-number">{{ $k['avg_time_to_publish_days'] === null ? '—' : $k['avg_time_to_publish_days'].'d' }}</span>
            </div></div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Lifecycle distribution (live set)</h5></div>
                <div class="card-body">
                    @foreach ($k['lifecycle_distribution'] as $lifecycle => $count)
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small">
                                <span>{{ \App\Services\Admin\KolabLifecycleService::label($lifecycle) }}</span>
                                <span class="text-muted">{{ $count }}</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-primary" style="width: {{ $k['total'] > 0 ? round($count / $k['total'] * 100, 1) : 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Kolabs by intent</h5></div>
                <div class="card-body">
                    @foreach ($k['by_intent'] as $intent => $count)
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small">
                                <span>{{ ucfirst(str_replace('_', ' ', $intent)) }}</span>
                                <span class="text-muted">{{ $count }}</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-info" style="width: {{ $k['total'] > 0 ? round($count / $k['total'] * 100, 1) : 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <h4 class="mb-3 mt-4">Applications</h4>
    <div class="row">
        <div class="col-md-3">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">Total</span>
                <span class="info-box-number">{{ $fmt($apps['total']) }}</span>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">Acceptance rate</span>
                <span class="info-box-number">{{ $pct($apps['acceptance_rate_pct']) }}</span>
                <span class="text-muted small">{{ $fmt($apps['accepted']) }} accepted / {{ $fmt($apps['declined']) }} declined</span>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">Median per kolab</span>
                <span class="info-box-number">{{ $apps['median_per_kolab'] === null ? '—' : $apps['median_per_kolab'] }}</span>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">Avg time-to-accept</span>
                <span class="info-box-number">{{ $apps['avg_time_to_accept_hours'] === null ? '—' : $apps['avg_time_to_accept_hours'].'h' }}</span>
            </div></div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">By applicant type</h5></div>
                <div class="card-body">
                    <p class="mb-2"><strong>Community:</strong> {{ $fmt($apps['from_community']) }}</p>
                    <p class="mb-2"><strong>Business:</strong> {{ $fmt($apps['from_business']) }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">By status</h5></div>
                <div class="card-body">
                    <p class="mb-1"><span class="badge badge-warning">PENDING</span> {{ $fmt($apps['pending']) }}</p>
                    <p class="mb-1"><span class="badge badge-success">ACCEPTED</span> {{ $fmt($apps['accepted']) }}</p>
                    <p class="mb-1"><span class="badge badge-secondary">DECLINED</span> {{ $fmt($apps['declined']) }}</p>
                    <p class="mb-1"><span class="badge badge-light">WITHDRAWN</span> {{ $fmt($apps['withdrawn']) }}</p>
                </div>
            </div>
        </div>
    </div>

    <h4 class="mb-3 mt-4">Collaborations</h4>
    <div class="row">
        <div class="col-md-3">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">Completed</span>
                <span class="info-box-number">{{ $fmt($col['completed']) }}</span>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">Cancelled</span>
                <span class="info-box-number">{{ $fmt($col['cancelled']) }}</span>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">Completion rate</span>
                <span class="info-box-number">{{ $pct($col['completion_rate_pct']) }}</span>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">Avg time-to-complete</span>
                <span class="info-box-number">{{ $col['avg_time_to_complete_days'] === null ? '—' : $col['avg_time_to_complete_days'].'d' }}</span>
            </div></div>
        </div>
    </div>

    @if (! empty($col['top_cancellation_reasons']))
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Top cancellation reasons</h5></div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    @foreach ($col['top_cancellation_reasons'] as $row)
                        <li class="mb-1"><span class="badge badge-secondary mr-2">{{ $row['count'] }}</span>{{ $row['reason'] }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <h4 class="mb-3 mt-4">Quality</h4>
    <div class="row">
        <div class="col-md-3">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">Avg rating</span>
                <span class="info-box-number">★ {{ $q['avg_rating'] === null ? '—' : $q['avg_rating'] }}</span>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">Both sides reviewed</span>
                <span class="info-box-number">{{ $pct($q['both_sides_reviewed_pct']) }}</span>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">Business → Community</span>
                <span class="info-box-number">★ {{ $q['per_side']['business']['avg'] ?? '—' }}</span>
                <span class="text-muted small">{{ $q['per_side']['business']['count'] ?? 0 }} reviews</span>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">Community → Business</span>
                <span class="info-box-number">★ {{ $q['per_side']['community']['avg'] ?? '—' }}</span>
                <span class="text-muted small">{{ $q['per_side']['community']['count'] ?? 0 }} reviews</span>
            </div></div>
        </div>
    </div>

    @if (! empty($q['point_event_mix']))
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Engagement (point-ledger events)</h5></div>
            <div class="card-body">
                @foreach ($q['point_event_mix'] as $event => $count)
                    <p class="mb-1"><span class="badge badge-info mr-2">{{ $count }}</span>{{ ucfirst(str_replace('_', ' ', $event)) }}</p>
                @endforeach
            </div>
        </div>
    @endif

    <h4 class="mb-3 mt-4">Subscriptions</h4>
    <div class="row">
        <div class="col-md-3">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">Active subs</span>
                <span class="info-box-number">{{ $fmt($m['active_total']) }}</span>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">Paid penetration</span>
                <span class="info-box-number">{{ $pct($m['paid_penetration_pct']) }}</span>
                <span class="text-muted small">vs. all business profiles</span>
            </div></div>
        </div>
        <div class="col-md-6">
            <div class="info-box bg-light"><div class="info-box-content">
                <span class="info-box-text">By source</span>
                <span>
                    @foreach ($m['active_by_source'] as $source => $count)
                        <span class="badge badge-{{ $source === 'maintainer' ? 'warning' : 'success' }} mr-1">{{ $source }}: {{ $count }}</span>
                    @endforeach
                </span>
            </div></div>
        </div>
    </div>

    <p class="text-muted small mt-4">
        Range:
        @if ($range === 'all')
            all time
        @else
            since {{ $summary['since'] }}
        @endif
        · Funnel counts are lifetime values regardless of range.
        DAU/WAU/MAU use the <code>last_active_at</code> column populated by the API <code>touch_profile_activity</code> middleware; counts will build over time as users hit the API.
    </p>
@endsection
