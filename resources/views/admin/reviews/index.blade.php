@extends('admin.layout', ['title' => 'Reviews'])

@section('page_title', 'Kolab reviews')
@section('page_subtitle', 'Submitted 5-star Kolab reviews — view ratings and moderate public comments.')

@section('admin_content')
    <form method="GET" class="form-inline mb-3">
        <select name="reviewer_role" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
            <option value="">All reviewer roles</option>
            <option value="business" {{ $filters['reviewer_role'] === 'business' ? 'selected' : '' }}>Business</option>
            <option value="community" {{ $filters['reviewer_role'] === 'community' ? 'selected' : '' }}>Community</option>
        </select>
    </form>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th>Kolab</th>
                        <th>Reviewer</th>
                        <th>Reviewed</th>
                        <th class="text-center">Comm.</th>
                        <th class="text-center">Reliab.</th>
                        <th class="text-center">Fit</th>
                        <th class="text-center">Value</th>
                        <th class="text-center">Repeat</th>
                        <th class="text-center">Overall</th>
                        <th>Public comment</th>
                        <th>Created</th>
                        <th class="text-right pr-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reviews as $review)
                        <tr>
                            <td>
                                {{ $review->collaboration?->kolab?->title ?? 'Collab #'.$review->collaboration_id }}
                            </td>
                            <td>
                                {{ $review->reviewerProfile?->getExtendedProfile()?->name ?? '—' }}
                                <span class="badge badge-secondary">{{ $review->reviewer_role }}</span>
                            </td>
                            <td>
                                {{ $review->reviewed?->getExtendedProfile()?->name ?? '—' }}
                            </td>
                            <td class="text-center">{{ $review->communication_rating ?? '—' }}</td>
                            <td class="text-center">{{ $review->reliability_rating ?? '—' }}</td>
                            <td class="text-center">{{ $review->fit_rating ?? '—' }}</td>
                            <td class="text-center">{{ $review->value_rating ?? '—' }}</td>
                            <td class="text-center">{{ $review->repeat_rating ?? '—' }}</td>
                            <td class="text-center font-weight-bold">{{ $review->overall_rating ?? '—' }}</td>
                            <td style="max-width:280px">
                                @if ($review->public_comment)
                                    <div class="text-truncate" title="{{ $review->public_comment }}">{{ $review->public_comment }}</div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $review->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="text-right pr-3 text-nowrap">
                                @if ($review->public_comment)
                                    <form method="POST" action="{{ route('admin.reviews.toggle-comment', $review) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm {{ $review->public_comment_visible ? 'btn-success' : 'btn-outline-secondary' }}">
                                            {{ $review->public_comment_visible ? 'Visible' : 'Hidden' }}
                                        </button>
                                    </form>
                                @else
                                    <span class="text-muted">No comment</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="text-center text-muted py-4">No reviews yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $reviews->links() }}</div>
@endsection
