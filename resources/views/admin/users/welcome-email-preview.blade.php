@extends('admin.layout', ['title' => 'Preview Welcome Email'])

@section('page_title', 'Preview — Welcome Email')
@section('page_subtitle', 'Exactly what will be sent. Nothing has been sent yet.')

@section('page_actions')
    <a href="{{ route('admin.users.edit', $profile) }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left mr-1"></i>
        Back without sending
    </a>
@endsection

@section('admin_content')
    <div class="alert alert-warning">
        <i class="fas fa-eye mr-1"></i>
        This is a preview only — the create-password link below is a placeholder, not a real link. Nothing is sent until you confirm below.
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title">To: {{ $profile->email }}</h3>
        </div>
        <div class="card-body p-0">
            <iframe srcdoc="{{ $html }}" style="width:100%; height:800px; border:0;" sandbox=""></iframe>
        </div>
        <div class="card-footer">
            <form method="POST" action="{{ route('admin.users.send-welcome-email', $profile) }}" class="d-inline" onsubmit="return confirm('Send this email to {{ $profile->email }} now?');">
                @csrf
                <input type="hidden" name="locale" value="{{ $locale }}">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-paper-plane mr-1"></i>
                    Confirm &amp; send
                </button>
            </form>
            <a href="{{ route('admin.users.edit', $profile) }}" class="btn btn-default">Cancel</a>
        </div>
    </div>
@endsection
