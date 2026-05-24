@extends('admin.layout', ['title' => 'Create User'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Create User</h1>
            <p>Create a new application profile for business, community, or attendee users.</p>
        </div>
    </div>

    <div class="card">
        <form method="post" action="/admin/users">
            @csrf
            @php($profile = new \App\Models\Profile())
            @php($isEdit = false)
            @include('admin.users.form')

            <div class="actions">
                <button type="submit">Create User</button>
                <a class="button secondary" href="/admin/users">Cancel</a>
            </div>
        </form>
    </div>
@endsection
