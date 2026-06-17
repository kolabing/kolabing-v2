@extends('admin.layout', ['title' => 'Icons'])

@section('page_title', 'Icons')
@section('page_subtitle', 'The personalised SVG icon library the mobile app renders. Bundled app icons + admin uploads. Offer options and other taxonomies pick from here.')

@section('admin_content')
    <div class="card">
        <div class="card-header"><strong>Upload a new icon</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.icons.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-row align-items-end">
                    <div class="form-group col-md-4">
                        <label>Label <small class="text-muted">(optional; blank = from filename)</small></label>
                        <input name="label" value="{{ old('label') }}" class="form-control" placeholder="e.g. Brunch">
                    </div>
                    <div class="form-group col-md-5">
                        <label>SVG file <small class="text-muted">(.svg only, ≤128 KB)</small></label>
                        <input type="file" name="icon_svg" accept="image/svg+xml,.svg" class="form-control-file" required>
                    </div>
                    <div class="form-group col-md-3">
                        <button class="btn btn-primary btn-block">
                            <i class="fas fa-upload mr-1"></i> Upload icon
                        </button>
                    </div>
                </div>
                <small class="text-muted">After upload the icon appears in the gallery below and is immediately pickable in the offer-options editor.</small>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Library</strong> <span class="text-muted">({{ $icons->count() }} icons)</span></div>
        <div class="card-body">
            @if ($icons->isEmpty())
                <p class="text-center text-muted py-4 mb-0">No icons yet. Upload one above.</p>
            @else
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:12px">
                    @foreach ($icons as $icon)
                        <div class="border rounded text-center p-2" style="background:#fff">
                            <div class="d-flex align-items-center justify-content-center mb-2"
                                 style="height:56px">
                                <img src="{{ $icon->url }}" alt="{{ $icon->label }}"
                                     style="max-width:48px;max-height:48px;object-fit:contain">
                            </div>
                            <div class="font-weight-bold text-truncate" title="{{ $icon->label }}">{{ $icon->label }}</div>
                            <code class="small d-block text-truncate" title="{{ $icon->slug }}">{{ $icon->slug }}</code>
                            <span class="badge badge-{{ $icon->source === \App\Models\Icon::SOURCE_BUNDLED ? 'secondary' : 'info' }} mt-1">
                                {{ $icon->source }}
                            </span>
                            @if ($icon->source !== \App\Models\Icon::SOURCE_BUNDLED)
                                <form method="POST" action="{{ route('admin.icons.destroy', $icon) }}" class="mt-1"
                                      onsubmit="return confirm('Delete this uploaded icon?');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger btn-block">Delete</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
