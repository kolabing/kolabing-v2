@extends('admin.layout', ['title' => 'Gamification overview'])

@section('page_title', 'Gamification overview')
@section('page_subtitle', 'Platform-wide totals and a per-community breakdown. Goals, rewards and badges are leader-owned in-app; this is read-only oversight.')

@section('admin_content')
    <div class="row">
        <div class="col-md-4 col-lg-2">
            <div class="small-box bg-light">
                <div class="inner">
                    <h3>{{ number_format($totals['communities']) }}</h3>
                    <p>Communities</p>
                </div>
                <div class="icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="small-box bg-light">
                <div class="inner">
                    <h3>{{ number_format($totals['active_goals']) }}</h3>
                    <p>Active goals</p>
                </div>
                <div class="icon"><i class="fas fa-bullseye"></i></div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="small-box bg-light">
                <div class="inner">
                    <h3>{{ number_format($totals['active_rewards']) }}</h3>
                    <p>Active rewards</p>
                </div>
                <div class="icon"><i class="fas fa-gift"></i></div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="small-box bg-light">
                <div class="inner">
                    <h3>{{ number_format($totals['active_badges']) }}</h3>
                    <p>Active badges</p>
                </div>
                <div class="icon"><i class="fas fa-award"></i></div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="small-box bg-light">
                <div class="inner">
                    <h3>{{ number_format($totals['points_in_circulation']) }}</h3>
                    <p>Points in circulation</p>
                </div>
                <div class="icon"><i class="fas fa-coins"></i></div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="small-box bg-light">
                <div class="inner">
                    <h3>{{ number_format($totals['redemptions']) }}</h3>
                    <p>Redemptions</p>
                </div>
                <div class="icon"><i class="fas fa-receipt"></i></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Per-community breakdown</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Community</th>
                            <th class="text-center">Active goals</th>
                            <th class="text-center">Active rewards</th>
                            <th class="text-center">Active badges</th>
                            <th class="text-center">Points</th>
                            <th class="text-center">Redemptions</th>
                            <th class="text-right pr-4">Leaderboard</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($communities as $row)
                            <tr>
                                <td class="font-weight-bold">{{ $row['community']->name }}</td>
                                <td class="text-center">{{ $row['goals'] }}</td>
                                <td class="text-center">{{ $row['rewards'] }}</td>
                                <td class="text-center">{{ $row['badges'] }}</td>
                                <td class="text-center">{{ number_format($row['points']) }}</td>
                                <td class="text-center">{{ $row['redemptions'] }}</td>
                                <td class="text-right pr-4">
                                    <a href="{{ route('admin.gamification.leaderboards.communities.show', $row['community']) }}" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No communities yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($communities->hasPages())
            <div class="card-footer clearfix">
                {{ $communities->links('pagination::bootstrap-4') }}
            </div>
        @endif
    </div>
@endsection
