@extends('admin.layout', ['title' => 'Edit Language'])

@section('page_title', 'Edit — ' . $template->label)
@section('page_subtitle', 'Quick-add welcome email content for this language.')

@section('page_actions')
    <a href="{{ route('admin.email-templates.index') }}" class="btn btn-outline-secondary mr-2">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Email Templates
    </a>

    <form method="POST" action="{{ route('admin.email-templates.destroy', $template) }}" class="d-inline" onsubmit="return confirm('Remove this language? Any listing already sent in this language keeps its history, but you will not be able to send new ones in it.');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger">
            <i class="fas fa-trash mr-1"></i>
            Remove language
        </button>
    </form>
@endsection

@section('admin_content')
    <div class="card card-primary card-outline">
        <form method="post" action="{{ route('admin.email-templates.update', $template) }}">
            @csrf
            @method('PUT')
            <div class="card-body">
                @include('admin.email-templates._form')
            </div>

            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="{{ route('admin.email-templates.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@endsection
