@extends('admin.layout', ['title' => 'Community Verification'])

@section('page_title', 'Community Verification')
@section('page_subtitle', 'Review submitted proof channels and verify or reject communities.')

@section('admin_content')
    @php
        $filters = [
            '' => 'All',
            'pending' => 'Pending',
            'flagged' => 'Flagged',
            'verified' => 'Verified',
            'rejected' => 'Rejected',
            'unverified' => 'Unverified',
        ];
    @endphp

    <div class="mb-3">
        @foreach ($filters as $key => $label)
            <a href="{{ route('admin.community-verification.index', array_filter(['filter' => $key])) }}"
               class="btn btn-sm {{ $filter === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Community</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Channels</th>
                            <th class="text-right pr-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($profiles as $profile)
                            @php
                                $cp = $profile->communityProfile;
                                $statusEnum = \App\Enums\VerificationStatus::tryFrom($cp?->verification_status ?? 'unverified')
                                    ?? \App\Enums\VerificationStatus::Unverified;
                                $channelCount = is_array($cp?->verification_channels) ? count($cp->verification_channels) : 0;
                            @endphp
                            <tr>
                                <td>
                                    <div class="font-weight-bold">{{ $cp?->name ?? $profile->email }}</div>
                                    <small class="text-muted">{{ $profile->id }}</small>
                                </td>
                                <td>{{ $profile->email }}</td>
                                <td>
                                    <span class="badge badge-{{ $statusEnum->badgeClass() }}">{{ $statusEnum->label() }}</span>
                                    @if ($cp?->verification_flagged_at)
                                        <span class="badge badge-warning" title="Channels changed since verified">Flagged</span>
                                    @endif
                                </td>
                                <td>{{ $channelCount }}</td>
                                <td class="text-right pr-4">
                                    <a href="{{ route('admin.users.edit', $profile) }}" class="btn btn-sm btn-outline-primary">Review</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No communities found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($profiles->hasPages())
            <div class="card-footer clearfix">
                {{ $profiles->links('pagination::bootstrap-4') }}
            </div>
        @endif
    </div>
@endsection
