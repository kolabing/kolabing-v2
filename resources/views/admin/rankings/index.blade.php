@extends('admin.layout', ['title' => 'Rankings'])

@section('page_title', 'Community rankings')
@section('page_subtitle', 'Publish the public /communities pages and moderate member testimonials. Re-rank in CRM.')

@section('admin_content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @foreach ($pages as $city => $cityPages)
        <div class="card">
            <div class="card-header"><strong>{{ $city }}</strong> <span class="text-muted">— {{ $cityPages->count() }} pages</span></div>
            <div class="card-body p-0">
                <table class="table table-hover table-sm mb-0">
                    <thead><tr><th style="width:110px">Status</th><th>Page</th><th>Topic</th><th class="text-right pr-3">Action</th></tr></thead>
                    <tbody>
                        @foreach ($cityPages as $page)
                            <tr>
                                <td><span class="badge {{ $page->published ? 'badge-success' : 'badge-secondary' }}">{{ $page->published ? 'Live' : 'Draft' }}</span></td>
                                <td class="font-weight-bold">
                                    <a href="{{ $page->topic ? route('directory.topic', [$page->city, $page->slug]) : route('directory.city', $page->city) }}" target="_blank" rel="noopener">{{ $page->title }}</a>
                                </td>
                                <td>{{ $page->topic ?? 'hub' }}</td>
                                <td class="text-right pr-3">
                                    <form method="POST" action="{{ route('admin.rankings.publish', $page) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm {{ $page->published ? 'btn-outline-secondary' : 'btn-primary' }}">{{ $page->published ? 'Unpublish' : 'Publish' }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    <div class="card">
        <div class="card-header"><strong>Pending testimonials</strong> <span class="text-muted">— {{ $pending->count() }} awaiting review</span></div>
        <div class="card-body p-0">
            <table class="table table-hover table-sm mb-0">
                <thead><tr><th>Community</th><th>Testimonial</th><th>By</th><th class="text-right pr-3">Action</th></tr></thead>
                <tbody>
                    @forelse ($pending as $t)
                        <tr>
                            <td>{{ optional($t->listing)->name ?? '—' }}</td>
                            <td>{{ $t->body }}</td>
                            <td>{{ $t->author_label ?? 'member' }} @if ($t->verified_member)<span class="badge badge-info">verified</span>@endif</td>
                            <td class="text-right pr-3">
                                <form method="POST" action="{{ route('admin.rankings.testimonials.moderate', [$t, 'approve']) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success">Approve</button></form>
                                <form method="POST" action="{{ route('admin.rankings.testimonials.moderate', [$t, 'reject']) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-danger">Reject</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted text-center py-3">No testimonials awaiting review.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
