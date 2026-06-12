@extends('admin.layout', ['title' => 'Edit partner reward'])

@section('page_title', 'Edit partner reward')
@section('page_actions')
    <a href="{{ route('admin.gamification.partner-rewards.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left mr-1"></i>
        Back
    </a>
@endsection

@section('admin_content')
    <div class="card card-primary card-outline">
        <form method="POST" action="{{ route('admin.gamification.partner-rewards.update', $reward) }}">
            @csrf
            @method('PUT')
            <div class="card-body">
                @include('admin.gamification.partner-rewards._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('admin.gamification.partner-rewards.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@endsection
