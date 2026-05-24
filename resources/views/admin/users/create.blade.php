@extends('admin.layout', ['title' => 'Create User'])

@section('page_title', 'Create User')
@section('page_subtitle', 'Create a new application profile for business, community, or attendee users.')

@section('page_actions')
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Users
    </a>
@endsection

@section('admin_content')
    <div class="card card-primary card-outline">
        <form method="post" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="card-body">
                @php($profile = new \App\Models\Profile())
                @php($isEdit = false)
                @include('admin.users.form')
            </div>

            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Create User</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@endsection
