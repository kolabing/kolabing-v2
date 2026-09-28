{{-- Organiser level + city league honours (incentives v1). Levels: app:evaluate-organiser-levels (nightly); seasons: app:close-league-seasons (1st of month). --}}
@php
    $levelLabels = collect(config('incentives.organiser_levels.levels'))->map(fn ($l) => $l['label'] ?? '');
    $introDue = $snapshot?->isIntroDue() ?? false;
    $leagueIntroDue = $honours->contains(fn ($s) => $s->isIntroDue());
@endphp
<div class="card {{ $introDue || $leagueIntroDue ? 'card-warning' : 'card-secondary' }} card-outline">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-medal mr-1"></i>
            Organiser level:
            <strong>{{ $snapshot ? $levelLabels[$snapshot->level->value] ?? $snapshot->level->value : 'Not evaluated yet' }}</strong>
            @if ($introDue || $leagueIntroDue)
                <span class="badge badge-warning ml-2">Intro due</span>
            @endif
        </h3>
    </div>
    <div class="card-body">
        @if ($snapshot)
            <p class="text-muted mb-2">
                Evaluated {{ $snapshot->evaluated_at->toDayDateTimeString() }} (UTC) for {{ $snapshot->criteria['metrics']['month'] ?? '' }}
                · venue ranking score {{ $snapshot->discovery_score }}
                @if ($snapshot->previous_level && $snapshot->previous_level !== $snapshot->level)
                    · was {{ $levelLabels[$snapshot->previous_level->value] ?? $snapshot->previous_level->value }}
                @endif
            </p>
            <ul class="mb-2">
                @foreach ($snapshot->criteria['criteria'] ?? [] as $criterion)
                    <li>
                        {{ $criterion['label'] }}:
                        <strong>{{ $criterion['value'] ?? 'none yet' }}</strong>
                        (target {{ $criterion['target'] }})
                        {!! $criterion['met'] ? '<span class="text-success">met</span>' : '<span class="text-danger">not met</span>' !!}
                    </li>
                @endforeach
            </ul>
            @if ($introDue)
                <p class="mb-2">Top organiser: owed a personal introduction from our pipeline (sports brands, fashion brands, venues).</p>
                <form method="POST" action="{{ route('admin.users.organiser-intro.done', $profile) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-warning btn-sm">
                        <i class="fas fa-handshake mr-1"></i>
                        Mark intro done
                    </button>
                </form>
            @elseif ($snapshot->intro_done_at)
                <p class="text-muted mb-0">Top intro done {{ $snapshot->intro_done_at->toFormattedDateString() }}.</p>
            @endif
        @else
            <p class="text-muted mb-0">The nightly level run has not reached this organiser yet.</p>
        @endif

        @if ($honours->isNotEmpty())
            <hr>
            <h5 class="mb-2">City league honours</h5>
            <ul class="mb-0">
                @foreach ($honours as $standing)
                    <li class="mb-1">
                        <strong>{{ $standing->badge === 'champion' ? 'Champion' : 'Top 3' }}</strong>
                        · {{ $standing->season->city?->name }} {{ $standing->season->month }}
                        · {{ config('incentives.city_league.division_labels.'.$standing->division, $standing->division) }}
                        · #{{ $standing->rank }}, {{ $standing->points }} pts
                        @if ($standing->isIntroDue())
                            <form method="POST" action="{{ route('admin.users.league-intro.done', [$profile, $standing]) }}" class="d-inline ml-2">
                                @csrf
                                <button type="submit" class="btn btn-warning btn-sm">
                                    <i class="fas fa-handshake mr-1"></i>
                                    Mark league intro done
                                </button>
                            </form>
                        @elseif ($standing->intro_done_at)
                            <span class="text-muted">intro done {{ $standing->intro_done_at->toFormattedDateString() }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
