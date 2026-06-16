@extends('admin.layout', ['title' => $option->exists ? 'Edit option' : 'New option'])

@section('page_title', $option->exists ? 'Edit '.$option->name : 'New '.$labels[$kind].' option')

@section('admin_content')
    <form method="POST"
        action="{{ $option->exists ? route('admin.offer-options.update', ['kind' => $kind, 'id' => $option->id]) : route('admin.offer-options.store') }}"
        enctype="multipart/form-data">
        @csrf
        @if ($option->exists) @method('PUT') @endif
        <input type="hidden" name="kind" value="{{ $kind }}">

        <div class="card"><div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Name</label>
                    <input name="name" value="{{ old('name', $option->name) }}" class="form-control" required>
                </div>
                <div class="form-group col-md-4">
                    <label>Slug <small class="text-muted">(lowercase, underscores; blank = from name)</small></label>
                    <input name="slug" value="{{ old('slug', $option->slug) }}" class="form-control" placeholder="auto"
                        {{ $option->exists ? 'readonly' : '' }}>
                    @if ($option->exists)
                        <small class="text-muted">Slug is the wire contract — locked once created.</small>
                    @endif
                </div>
                <div class="form-group col-md-2">
                    <label>Order</label>
                    <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $option->sort_order ?? 0) }}" class="form-control">
                </div>
            </div>

            <div class="form-row align-items-end">
                <div class="form-group col-md-4">
                    <label>Icon name <small class="text-muted">(Lucide / bundled)</small></label>
                    <input id="iconName" name="icon" value="{{ old('icon', $option->icon) }}" class="form-control" placeholder="e.g. utensils">
                </div>
                <div class="form-group col-md-2 text-center">
                    <label class="d-block">Preview</label>
                    <i id="iconPreview" data-lucide="{{ old('icon', $option->icon) ?: 'help-circle' }}" style="width:30px;height:30px"></i>
                </div>
                <div class="form-group col-md-6">
                    <label>Or upload an SVG <small class="text-muted">(for a new icon; ≤128 KB)</small></label>
                    <input type="file" name="icon_svg" accept="image/svg+xml" class="form-control-file">
                    @if ($option->icon_url)
                        <small class="text-muted">Current uploaded: <img src="{{ $option->icon_url }}" style="width:20px;height:20px;object-fit:contain"> {{ $option->icon_url }}</small>
                    @endif
                </div>
            </div>

            <div class="form-group form-check">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" @checked(old('is_active', $option->is_active ?? true))>
                <label class="form-check-label" for="isActive">Active (visible in the app)</label>
            </div>
        </div></div>

        <div class="d-flex justify-content-between">
            <a href="{{ route('admin.offer-options.index', ['kind' => $kind]) }}" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn btn-primary">{{ $option->exists ? 'Save' : 'Create' }}</button>
        </div>
    </form>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>
        (function () {
            function render() { if (window.lucide) window.lucide.createIcons(); }
            render();
            var input = document.getElementById('iconName');
            var prev = document.getElementById('iconPreview');
            if (input && prev) {
                input.addEventListener('input', function () {
                    var icon = input.value.trim() || 'help-circle';
                    var fresh = document.createElement('i');
                    fresh.id = 'iconPreview'; fresh.setAttribute('data-lucide', icon);
                    fresh.style.width = '30px'; fresh.style.height = '30px';
                    prev.replaceWith(fresh); prev = fresh; render();
                });
            }
        })();
    </script>
@endsection
