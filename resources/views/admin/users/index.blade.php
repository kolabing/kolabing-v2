@extends('admin.layout', ['title' => 'Users'])

@section('page_title', 'Users')
@section('page_subtitle', 'Manage application profiles from a fixed maintainer panel.')

@section('page_actions')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
        <i class="fas fa-user-plus mr-1"></i>
        Create User
    </a>
@endsection

@section('admin_content')
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Phone</th>
                            <th>Verified</th>
                            <th class="text-right pr-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($profiles as $profile)
                            @php
                                $label = $profile->businessProfile?->name
                                    ?? $profile->communityProfile?->name
                                    ?? $profile->email;
                            @endphp
                            <tr>
                                <td>
                                    <div class="font-weight-bold">{{ $label }}</div>
                                    <small class="text-muted">{{ $profile->id }}</small>
                                </td>
                                <td>{{ $profile->email }}</td>
                                <td>
                                    <span class="badge badge-light text-uppercase">{{ $profile->user_type->value }}</span>
                                </td>
                                <td>{{ $profile->phone_number ?: '—' }}</td>
                                <td>
                                    <span class="badge {{ $profile->email_verified_at ? 'badge-success' : 'badge-secondary' }}">
                                        {{ $profile->email_verified_at ? 'Verified' : 'Pending' }}
                                    </span>
                                </td>
                                <td class="text-right pr-4">
                                    <a href="{{ route('admin.users.edit', $profile) }}" class="btn btn-sm btn-outline-primary">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No users found.</td>
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
