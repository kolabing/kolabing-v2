@extends('admin.layout', ['title' => 'Community leaderboards'])

@section('page_title', 'Community leaderboards')
@section('page_subtitle', 'Members ranked by their per-community points balance, with tier and badge count.')

@section('admin_content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.gamification.leaderboards.communities') }}" class="form-row align-items-end" id="community-picker">
                <div class="form-group col-md-8 mb-md-0">
                    <label for="community" class="small text-muted mb-1">Community</label>
                    <select name="community" id="community" class="form-control"
                            onchange="if (this.value) { window.location = '{{ url('admin/gamification/leaderboards/communities') }}/' + this.value; }">
                        <option value="">Select a community…</option>
                        @foreach ($communities as $c)
                            <option value="{{ $c->id }}" {{ $selected && $selected->id === $c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
    </div>

    @if ($selected)
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ $selected->name }}</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="text-center">Rank</th>
                                <th>Member</th>
                                <th>Tier</th>
                                <th class="text-center">Badges</th>
                                <th class="text-right pr-4">Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="text-center font-weight-bold">#{{ $row['rank'] }}</td>
                                    <td>{{ $row['display_name'] }}</td>
                                    <td>
                                        @if ($row['tier'])
                                            <span class="badge badge-light">
                                                @if ($row['tier']['color'])
                                                    <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:{{ $row['tier']['color'] }};"></span>
                                                @endif
                                                {{ $row['tier']['name'] }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $row['badge_count'] }}</td>
                                    <td class="text-right pr-4 font-weight-bold">{{ number_format($row['points']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No active members with points in this community yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                Pick a community above to view its leaderboard.
            </div>
        </div>
    @endif
@endsection
