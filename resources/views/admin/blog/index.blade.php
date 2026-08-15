@extends('admin.layout', ['title' => 'Blog'])

@section('page_title', 'Blog')
@section('page_subtitle', 'Community Commerce articles published at /blog.')

@section('page_actions')
    <a href="{{ route('admin.blog.create') }}" class="btn btn-primary"><i class="fas fa-plus mr-1"></i> New post</a>
@endsection

@section('admin_content')
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover table-sm mb-0">
                <thead>
                    <tr><th style="width:120px">Status</th><th>Title</th><th>Author</th><th>Published</th><th class="text-right pr-3">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($posts as $post)
                        @php $live = $post->isPublished(); $scheduled = $post->published_at && $post->published_at->isFuture(); @endphp
                        <tr>
                            <td>
                                <span class="badge {{ $live ? 'badge-success' : ($scheduled ? 'badge-info' : 'badge-secondary') }}">{{ $live ? 'Published' : ($scheduled ? 'Scheduled' : 'Draft') }}</span>
                            </td>
                            <td class="font-weight-bold"><a href="{{ route('blog.show', $post) }}" target="_blank" rel="noopener">{{ $post->title }}</a></td>
                            <td>{{ $post->author_name }}</td>
                            <td>{{ $post->published_at?->format('d M Y') ?? '—' }}</td>
                            <td class="text-right pr-3 text-nowrap">
                                <a href="{{ route('admin.blog.edit', $post) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.blog.destroy', $post) }}" class="d-inline" onsubmit="return confirm('Delete post?');">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">&times;</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No posts yet. <a href="{{ route('admin.blog.create') }}">Write the first one</a>.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
