@extends('webapp.layout')
@section('title', 'Kolabing')

@section('body')
<div class="min-h-screen flex items-center justify-center">
    <p class="text-off-black/50">Loading…</p>
</div>
@push('scripts')
<script>
    // Route to the app or the sign-in page based on the stored session.
    location.replace(window.kb.token ? '/dashboard' : '/login');
</script>
@endpush
@endsection
