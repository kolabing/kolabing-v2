@extends('admin.layout', ['title' => 'Global XP leaderboard'])

@section('page_title', 'Global XP leaderboard')
@section('page_subtitle', 'All users ranked by global XP (total points across the platform).')

@section('admin_content')
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="text-center">Rank</th>
                            <th>User</th>
                            <th class="text-right pr-4">XP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td class="text-center font-weight-bold">#{{ $row['rank'] }}</td>
                                <td>{{ $row['display_name'] }}</td>
                                <td class="text-right pr-4 font-weight-bold">{{ number_format($row['total_points']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">No users have earned XP yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
