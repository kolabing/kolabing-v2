@extends('webapp.layout')
@section('title', __('webapp.meta.home_title'))

@section('body')
<div class="min-h-screen flex items-center justify-center">
    <p class="text-off-black/50">{{ __('webapp.common.loading') }}</p>
</div>
@push('scripts')
<script>
    // Route to the app or the sign-in page based on the stored session (locale-aware).
    location.replace((window.KB_BASE || '') + (window.kb.token ? '/dashboard' : '/login'));
</script>
@endpush
@endsection
