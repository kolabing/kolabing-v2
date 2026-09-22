@extends('admin.layout', ['title' => 'Preview pitch'])

@section('page_title', 'Preview')
@section('page_subtitle', 'Exactly what the business receives. Nothing has been sent yet.')

@section('page_actions')
    <a href="{{ route('admin.sales-mailing.edit', $draft) }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Back to edit
    </a>
@endsection

@section('admin_content')
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <p class="mb-1"><strong>To:</strong> {{ $draft->business?->email }}</p>
            <p class="mb-1"><strong>Subject:</strong> {{ $draft->subject }}</p>
            <p class="mb-0"><strong>Language:</strong> {{ strtoupper($draft->locale) }}</p>
        </div>
    </div>

    {{-- Rendered inside a sandboxed iframe via srcdoc: the email's own stylesheet
         must not leak into the admin panel's, and the panel's must not flatter the
         email into looking better than it will in a real client. --}}
    <div class="card">
        <div class="card-body p-0">
            <iframe sandbox srcdoc="{{ $html }}" style="width:100%;height:760px;border:0;"></iframe>
        </div>
    </div>

    <div class="card">
        <div class="card-body d-flex align-items-center" style="gap:1rem">
            <form method="post" action="{{ route('admin.sales-mailing.send', $draft) }}" class="mb-0">
                @csrf
                <button class="btn btn-success btn-lg" @disabled($draft->isSent())>
                    <i class="fas fa-paper-plane mr-1"></i> Confirm &amp; send
                </button>
            </form>
            <span class="text-muted">
                This is the irreversible step. It goes to a real business under Kolabing's name —
                and the revenue figure in it is a claim they will remember.
            </span>
        </div>
    </div>
@endsection
