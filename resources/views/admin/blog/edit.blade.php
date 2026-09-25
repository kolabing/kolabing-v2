@extends('admin.layout', ['title' => $post->exists ? 'Edit post' : 'New post'])

@section('page_title', $post->exists ? 'Edit post' : 'New post')

@section('admin_content')
    <form method="POST" action="{{ $post->exists ? route('admin.blog.update', $post) : route('admin.blog.store') }}">
        @csrf
        @if ($post->exists) @method('PUT') @endif

        <div class="card"><div class="card-body">
            <div class="form-group">
                <label>Title</label>
                <input name="title" value="{{ old('title', $post->title) }}" class="form-control" required>
            </div>
            <div class="form-row">
                <div class="form-group col-md-8">
                    <label>Slug</label>
                    <input name="slug" value="{{ old('slug', $post->slug) }}" class="form-control" placeholder="how-to-get-foot-traffic-without-paid-ads" required>
                    <small class="form-text text-muted">Lowercase words separated by hyphens. This is the /blog/&lt;slug&gt; URL.</small>
                </div>
                <div class="form-group col-md-4">
                    <label>Locale</label>
                    <select name="locale" class="form-control">
                        @foreach (['en' => 'English', 'es' => 'Spanish'] as $k => $l)
                            <option value="{{ $k }}" @selected(old('locale', $post->locale ?? 'en') === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Description (meta / excerpt, max 500)</label>
                <textarea name="description" rows="2" class="form-control" maxlength="500" required>{{ old('description', $post->description) }}</textarea>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6"><label>Author name</label><input name="author_name" value="{{ old('author_name', $post->author_name ?? 'Kolabing Team') }}" class="form-control" required></div>
                <div class="form-group col-md-6"><label>Author title (optional)</label><input name="author_title" value="{{ old('author_title', $post->author_title) }}" class="form-control" placeholder="Co-founder, Kolabing"></div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-8"><label>Cover image URL (optional)</label><input name="cover_image_url" value="{{ old('cover_image_url', $post->cover_image_url) }}" class="form-control" placeholder="https://..."></div>
                <div class="form-group col-md-4"><label>Publish at (blank = draft)</label><input type="datetime-local" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}" class="form-control"></div>
            </div>
            <div class="form-group">
                <label>Body (HTML)</label>
                <textarea name="body" rows="18" class="form-control text-monospace" required>{{ old('body', $post->body) }}</textarea>
                <small class="form-text text-muted">Trusted HTML, rendered in a prose container. Use &lt;h2&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;a&gt;. Put FAQs in the FAQ box below, not here. Lead each section with a direct 40&ndash;60 word answer so answer-engines can extract it.</small>
            </div>
        </div></div>

        @php
            $faqRows = old('faq', $post->faq ?? []);
            $faqRows = is_array($faqRows) ? array_values($faqRows) : [];
        @endphp
        <div class="card"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="mb-0">FAQ (optional)</label>
                <button type="button" class="btn btn-sm btn-outline-primary" data-faq-add><i class="fas fa-plus mr-1"></i> Add question</button>
            </div>
            <small class="form-text text-muted mb-3">Shown as the article's FAQ section and emitted as FAQPage schema. Don't repeat it in the body. Empty rows are ignored.</small>
            <div data-faq-rows>
                @foreach ($faqRows as $i => $row)
                    <div class="border rounded p-2 mb-2" data-faq-row>
                        <div class="d-flex">
                            <input name="faq[{{ $i }}][question]" value="{{ $row['question'] ?? '' }}" class="form-control mr-2" placeholder="Question" maxlength="300">
                            <button type="button" class="btn btn-sm btn-outline-danger" data-faq-remove title="Remove">&times;</button>
                        </div>
                        <textarea name="faq[{{ $i }}][answer]" rows="2" class="form-control mt-2" placeholder="Answer (plain text)" maxlength="2000">{{ $row['answer'] ?? '' }}</textarea>
                    </div>
                @endforeach
            </div>
            <template data-faq-template>
                <div class="border rounded p-2 mb-2" data-faq-row>
                    <div class="d-flex">
                        <input name="faq[__i__][question]" class="form-control mr-2" placeholder="Question" maxlength="300">
                        <button type="button" class="btn btn-sm btn-outline-danger" data-faq-remove title="Remove">&times;</button>
                    </div>
                    <textarea name="faq[__i__][answer]" rows="2" class="form-control mt-2" placeholder="Answer (plain text)" maxlength="2000"></textarea>
                </div>
            </template>
        </div></div>

        <div class="d-flex justify-content-between">
            <a href="{{ route('admin.blog.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn btn-primary">{{ $post->exists ? 'Save' : 'Create' }}</button>
        </div>
    </form>

    <script>
        (function () {
            var rows = document.querySelector('[data-faq-rows]');
            var template = document.querySelector('[data-faq-template]');
            var next = rows.querySelectorAll('[data-faq-row]').length;
            document.querySelector('[data-faq-add]').addEventListener('click', function () {
                rows.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__i__/g, String(next++)));
            });
            rows.addEventListener('click', function (event) {
                var button = event.target.closest('[data-faq-remove]');
                if (button) {
                    button.closest('[data-faq-row]').remove();
                }
            });
        })();
    </script>
@endsection
