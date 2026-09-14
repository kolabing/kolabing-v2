@extends('admin.layout', ['title' => 'Add Language'])

@section('page_title', 'Add Language')
@section('page_subtitle', 'A new language for the quick-add welcome email.')

@section('page_actions')
    <a href="{{ route('admin.email-templates.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Email Templates
    </a>
@endsection

@section('admin_content')
    <div class="card card-primary card-outline">
        <form method="post" action="{{ route('admin.email-templates.store') }}">
            @csrf
            <div class="card-body">
                @include('admin.email-templates._form')
            </div>

            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Add language</button>
                <a href="{{ route('admin.email-templates.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@endsection
