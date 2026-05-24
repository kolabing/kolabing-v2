@extends('admin.layout', ['title' => 'Edit User'])

@section('page_title', 'Edit User')
@section('page_subtitle', 'Update the selected application profile without changing API-side workflows.')

@section('page_actions')
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Users
    </a>
@endsection

@section('admin_content')
    <div class="card card-primary card-outline">
        <form method="post" action="{{ route('admin.users.update', $profile) }}">
            @csrf
            @method('PUT')
            <div class="card-body">
                @php($isEdit = true)
                @php($userTypes = [])
                @include('admin.users.form')
            </div>

            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-default">Back</a>
            </div>
        </form>
    </div>
@endsection
