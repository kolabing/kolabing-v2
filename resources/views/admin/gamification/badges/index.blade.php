@extends('admin.layout', ['title' => 'Badges'])

@section('page_title', 'Badges')
@section('page_subtitle', 'Unified view over both badge systems, sectioned by audience.')

@section('admin_content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <p class="text-muted">
        Attendee badges live in the <code>badges</code> table and can be edited here. Business and community badges
        are enum-backed (<code>GamificationBadgeSlug</code>) — copy is hardcoded in the enum; changing it requires a
        deploy. Award counts are live.
    </p>

    @php $sections = [
        ['key' => 'attendees', 'label' => 'Attendees', 'icon' => 'fa-user', 'rows' => $attendeeBadges],
        ['key' => 'business', 'label' => 'Business', 'icon' => 'fa-briefcase', 'rows' => $businessBadges],
        ['key' => 'community', 'label' => 'Community', 'icon' => 'fa-users', 'rows' => $communityBadges],
    ]; @endphp

    @foreach ($sections as $section)
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas {{ $section['icon'] }} mr-2"></i>
                    {{ $section['label'] }}
                    <span class="badge badge-light ml-2">{{ count($section['rows']) }}</span>
                </h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Trigger</th>
                                <th class="text-right">Awarded</th>
                                <th class="text-right pr-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($section['rows'] as $row)
                                <tr>
                                    <td>
                                        <div class="font-weight-bold">{{ $row['name'] }}</div>
                                        @if (isset($row['icon']))
                                            <small class="text-muted">{{ $row['icon'] }}</small>
                                        @elseif (isset($row['slug']))
                                            <small class="text-muted">{{ $row['slug'] }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $row['description'] }}</td>
                                    <td>
                                        @if (isset($row['milestone_type']))
                                            <code>{{ $row['milestone_type'] }}</code>
                                            <span class="text-muted">≥ {{ $row['milestone_value'] }}</span>
                                        @else
                                            <span class="text-muted">Enum-defined</span>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ number_format($row['award_count']) }}</td>
                                    <td class="text-right pr-4">
                                        @php
                                            $editUrl = $row['edit_route']
                                                ?? (isset($row['id']) ? route('admin.gamification.badges.edit', $row['id']) : null);
                                        @endphp
                                        @if ($editUrl)
                                            <a href="{{ $editUrl }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        @else
                                            <span class="badge badge-light text-uppercase">Read-only</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No badges in this section.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach
@endsection
