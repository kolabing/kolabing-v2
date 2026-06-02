@extends('admin.layout', ['title' => 'New challenge'])

@section('page_title', 'New challenge')
@section('page_actions')
    <a href="{{ route('admin.gamification.challenges.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left mr-1"></i>
        Back
    </a>
@endsection

@section('admin_content')
    <div class="card card-primary card-outline">
        <form method="POST" action="{{ route('admin.gamification.challenges.store') }}">
            @csrf
            <div class="card-body">
                @include('admin.gamification.challenges._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Create</button>
                <a href="{{ route('admin.gamification.challenges.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@endsection
