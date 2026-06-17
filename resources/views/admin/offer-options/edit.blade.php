@extends('admin.layout', ['title' => $option->exists ? 'Edit option' : 'New option'])

@section('page_title', $option->exists ? 'Edit '.$option->name : 'New '.$labels[$kind].' option')

@php
    // Primary picker = the personalised icon library (the real SVGs the app renders).
    // The selected library icon's PUBLIC URL is written to the hidden icon_url field;
    // that exact URL is served to the app, which renders it with top priority.
    $currentIconUrl = old('icon_url', $option->icon_url);
    // Optional minor fallback: a Lucide slug (icon column) for entries with no SVG.
    $currentIcon = old('icon', $option->icon);
@endphp

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

            {{-- The field actually submitted: the chosen library icon's public URL. --}}
            <input type="hidden" id="iconUrlValue" name="icon_url" value="{{ $currentIconUrl }}">

            <div class="form-group">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <label class="mb-0">Icon <small class="text-muted">(personalised app icon)</small></label>
                    <a href="{{ route('admin.icons.index') }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-upload mr-1"></i> Upload a new icon to the library
                    </a>
                </div>

                <div class="d-flex align-items-center mb-2">
                    <span id="iconPreview" class="d-inline-flex align-items-center justify-content-center mr-2"
                          style="width:48px;height:48px;border:1px solid #ced4da;border-radius:8px;background:#f8f9fa">
                        @if ($currentIconUrl)
                            <img src="{{ $currentIconUrl }}" alt="" style="max-width:32px;max-height:32px;object-fit:contain">
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </span>
                    <div>
                        <div class="text-muted small">Selected icon</div>
                        <code id="iconSelectedLabel">{{ $currentIconUrl ? basename($currentIconUrl) : '— none —' }}</code>
                    </div>
                    <button type="button" id="iconClear" class="btn btn-sm btn-link text-danger {{ $currentIconUrl ? '' : 'd-none' }}">Clear</button>
                </div>

                <input type="text" id="iconFilter" class="form-control mb-2" placeholder="Filter icons… (e.g. cafe, running, gym)">

                <div id="iconGallery"
                     style="max-height:260px;overflow-y:auto;border:1px solid #e9ecef;border-radius:8px;padding:8px;
                            display:grid;grid-template-columns:repeat(auto-fill,minmax(72px,1fr));gap:8px">
                    @forelse ($iconLibrary as $libIcon)
                        <button type="button" class="icon-cell btn btn-outline-secondary p-1 d-flex flex-column align-items-center justify-content-center"
                                data-url="{{ $libIcon->url }}" data-slug="{{ $libIcon->slug }}"
                                data-label="{{ $libIcon->label }}" title="{{ $libIcon->label }}"
                                style="height:72px {{ $currentIconUrl === $libIcon->url ? ';border-color:#007bff;background:#e7f1ff' : '' }}">
                            <img src="{{ $libIcon->url }}" alt="{{ $libIcon->label }}" style="max-width:28px;max-height:28px;object-fit:contain">
                            <span class="text-truncate small mt-1" style="max-width:64px">{{ $libIcon->slug }}</span>
                        </button>
                    @empty
                        <p class="text-muted small mb-0">No icons in the library yet. <a href="{{ route('admin.icons.index') }}" target="_blank">Upload one</a>.</p>
                    @endforelse
                </div>
                <small class="text-muted">Click an icon to select it — its public URL is sent to the app, which renders this exact SVG.</small>
            </div>

            <div class="form-group">
                <label>Optional fallback: Lucide slug <small class="text-muted">(used only when no personalised icon is set)</small></label>
                <input id="iconManual" name="icon" value="{{ $currentIcon }}" class="form-control" placeholder="e.g. utensils" autocomplete="off">
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

@endsection

@section('js')
    @parent
    <script>
        (function () {
            var hidden = document.getElementById('iconUrlValue');
            var gallery = document.getElementById('iconGallery');
            var filter = document.getElementById('iconFilter');
            var selectedLabel = document.getElementById('iconSelectedLabel');
            var preview = document.getElementById('iconPreview');
            var clearBtn = document.getElementById('iconClear');

            function setPreview(url, label) {
                preview.innerHTML = '';
                if (url) {
                    var img = document.createElement('img');
                    img.src = url;
                    img.alt = label || '';
                    img.style.maxWidth = '32px';
                    img.style.maxHeight = '32px';
                    img.style.objectFit = 'contain';
                    preview.appendChild(img);
                } else {
                    var dash = document.createElement('span');
                    dash.className = 'text-muted';
                    dash.textContent = '—';
                    preview.appendChild(dash);
                }
            }

            function select(url, label) {
                hidden.value = url || '';
                selectedLabel.textContent = url ? url.split('/').pop() : '— none —';
                setPreview(url, label);
                clearBtn.classList.toggle('d-none', !url);
                gallery.querySelectorAll('.icon-cell').forEach(function (c) {
                    var on = url && c.getAttribute('data-url') === url;
                    c.style.borderColor = on ? '#007bff' : '';
                    c.style.background = on ? '#e7f1ff' : '';
                });
            }

            gallery.querySelectorAll('.icon-cell').forEach(function (cell) {
                cell.addEventListener('click', function () {
                    select(cell.getAttribute('data-url'), cell.getAttribute('data-label'));
                });
            });

            if (clearBtn) {
                clearBtn.addEventListener('click', function () { select('', ''); });
            }

            if (filter) {
                filter.addEventListener('input', function () {
                    var q = filter.value.trim().toLowerCase();
                    gallery.querySelectorAll('.icon-cell').forEach(function (cell) {
                        var hay = (cell.getAttribute('data-slug') + ' ' + cell.getAttribute('data-label')).toLowerCase();
                        cell.style.display = hay.indexOf(q) !== -1 ? '' : 'none';
                    });
                });
            }
        })();
    </script>
@stop
