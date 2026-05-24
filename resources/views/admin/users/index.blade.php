@extends('admin.layout', ['title' => 'Manage Users'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Users</h1>
            <p>Manage application profiles from one maintainer-only screen.</p>
        </div>

        <a class="button" href="/admin/users/create">Create User</a>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Type</th>
                    <th>Phone</th>
                    <th>Verified</th>
                    <th></th>
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
                            <strong>{{ $label }}</strong>
                            <div class="muted">{{ $profile->id }}</div>
                        </td>
                        <td>{{ $profile->email }}</td>
                        <td><span class="pill">{{ $profile->user_type->value }}</span></td>
                        <td>{{ $profile->phone_number ?: '—' }}</td>
                        <td>{{ $profile->email_verified_at ? 'Yes' : 'No' }}</td>
                        <td>
                            <a class="button secondary" href="/admin/users/{{ $profile->id }}/edit">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="muted">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="actions">
        {{ $profiles->links() }}
    </div>
@endsection
