@extends('admin.layout', ['title' => 'Edit User'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Edit User</h1>
            <p>Update the selected application profile without touching API-side workflows.</p>
        </div>
    </div>

    <div class="card">
        <form method="post" action="/admin/users/{{ $profile->id }}">
            @csrf
            @method('PUT')
            @php($isEdit = true)
            @php($userTypes = [])
            @include('admin.users.form')

            <div class="actions">
                <button type="submit">Save Changes</button>
                <a class="button secondary" href="/admin/users">Back</a>
            </div>
        </form>
    </div>
@endsection
