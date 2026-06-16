@extends('admin.layout', ['title' => $option->exists ? 'Edit option' : 'New option'])

@section('page_title', $option->exists ? 'Edit '.$option->name : 'New '.$labels[$kind].' option')

@php
    // Curated Lucide slug gallery. These are real Lucide icon names so the slug an
    // admin picks here renders identically in the mobile app. Includes every slug
    // currently seeded across all kinds plus a broad set covering food/drink, retail,
    // sport, beauty, tech, venue, travel, community, events and generic UI.
    $iconGallery = [
        // food & drink
        'utensils', 'coffee', 'wine', 'beer', 'martini', 'croissant', 'pizza', 'cake',
        'ice-cream', 'cookie', 'apple', 'sandwich', 'soup', 'salad', 'fish', 'cup-soda',
        'milk', 'wheat', 'egg',
        // retail & products
        'shopping-bag', 'shopping-cart', 'store', 'package', 'gift', 'tag', 'shirt',
        'percent', 'receipt', 'credit-card', 'wallet', 'box', 'truck', 'glasses',
        // sport & wellness
        'dumbbell', 'footprints', 'bike', 'trophy', 'medal', 'activity', 'heart',
        'heart-pulse', 'flame', 'mountain', 'waves', 'tent',
        // beauty & lifestyle
        'scissors', 'sparkles', 'palette', 'brush', 'flower', 'flower-2', 'leaf', 'spray-can',
        // tech
        'laptop', 'smartphone', 'monitor', 'wifi', 'camera', 'video', 'headphones',
        'gamepad-2', 'mouse-pointer', 'qr-code', 'cpu', 'battery',
        // venue & places
        'building', 'building-2', 'home', 'door-open', 'map-pin', 'warehouse', 'hotel',
        'bed', 'bed-double', 'umbrella', 'sun', 'armchair', 'lamp', 'key',
        // travel
        'plane', 'car', 'bus', 'train-front', 'ship', 'luggage', 'compass', 'globe',
        // community, events & social
        'users', 'user', 'user-plus', 'handshake', 'party-popper', 'ticket', 'music',
        'music-2', 'mic', 'calendar', 'calendar-days', 'megaphone', 'share-2', 'message-circle',
        'thumbs-up', 'graduation-cap', 'book', 'book-open', 'briefcase', 'presentation',
        // generic / status
        'star', 'award', 'badge-check', 'bookmark', 'bell', 'clock', 'check', 'check-circle',
        'circle', 'square', 'hexagon', 'zap', 'lightbulb', 'rocket', 'crown', 'shield',
        'pin', 'flag', 'image', 'file', 'folder', 'link', 'ellipsis', 'plus', 'minus',
        'help-circle', 'info', 'settings', 'eye', 'hand', 'smile',
    ];
    // Ensure the currently-saved icon is always in the gallery, even if not curated.
    $current = old('icon', $option->icon);
    if ($current && ! in_array($current, $iconGallery, true)) {
        array_unshift($iconGallery, $current);
    }
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

            {{-- Hidden field that actually gets submitted; the gallery + advanced text box both write to it. --}}
            <input type="hidden" id="iconValue" name="icon" value="{{ $current }}">

            <div class="form-group">
                <label class="d-block mb-2">Icon</label>
                <div class="d-flex align-items-center mb-2">
                    <span id="iconPreview" class="d-inline-flex align-items-center justify-content-center mr-2"
                          style="width:48px;height:48px;border:1px solid #ced4da;border-radius:8px;background:#f8f9fa">
                        @include('admin.partials.lucide', ['slug' => $current ?: 'help-circle', 'class' => 'lucide-28'])
                    </span>
                    <div>
                        <div class="text-muted small">Selected slug</div>
                        <code id="iconSelectedLabel">{{ $current ?: '— none —' }}</code>
                    </div>
                </div>

                <input type="text" id="iconFilter" class="form-control mb-2" placeholder="Filter icons… (e.g. coffee, gift, users)">

                <div id="iconGallery"
                     style="max-height:240px;overflow-y:auto;border:1px solid #e9ecef;border-radius:8px;padding:8px;
                            display:grid;grid-template-columns:repeat(auto-fill,minmax(48px,1fr));gap:6px">
                    @foreach ($iconGallery as $slug)
                        <button type="button" class="icon-cell btn btn-outline-secondary p-1 d-flex align-items-center justify-content-center"
                                data-slug="{{ $slug }}" title="{{ $slug }}"
                                style="aspect-ratio:1/1;height:44px {{ $current === $slug ? ';border-color:#007bff;background:#e7f1ff' : '' }}">
                            @include('admin.partials.lucide', ['slug' => $slug, 'class' => 'lucide-22'])
                        </button>
                    @endforeach
                </div>
                <small class="text-muted">Click an icon to select it. Icons are real Lucide names — exactly what the app renders.</small>
            </div>

            <div class="form-row align-items-end">
                <div class="form-group col-md-6">
                    <label>Advanced: type any Lucide slug <small class="text-muted">(fallback)</small></label>
                    <input id="iconManual" value="{{ $current }}" class="form-control" placeholder="e.g. utensils" autocomplete="off">
                    <small class="text-muted">Any valid Lucide slug works, even if it isn't in the gallery above.</small>
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

@endsection

@section('js')
    @parent
    <script>
        (function () {
            // Icons are server-rendered inline SVG (no CDN, no createIcons). The
            // preview is updated by CLONING the matching gallery cell's SVG, so it
            // always shows a real icon. Manual slugs not in the gallery fall back to
            // the neutral placeholder SVG (the preview's initial help-circle).
            var hidden = document.getElementById('iconValue');
            var manual = document.getElementById('iconManual');
            var filter = document.getElementById('iconFilter');
            var gallery = document.getElementById('iconGallery');
            var selectedLabel = document.getElementById('iconSelectedLabel');
            var preview = document.getElementById('iconPreview');

            // Stash the original placeholder SVG (help-circle) for fallback.
            var placeholderSvg = preview.querySelector('svg');
            placeholderSvg = placeholderSvg ? placeholderSvg.cloneNode(true) : null;

            function cellFor(slug) {
                if (!slug) { return null; }
                return gallery.querySelector('.icon-cell[data-slug="' + (window.CSS && CSS.escape ? CSS.escape(slug) : slug) + '"]');
            }

            function setPreviewSvg(svg) {
                preview.innerHTML = '';
                if (svg) {
                    var clone = svg.cloneNode(true);
                    clone.classList.remove('lucide-22');
                    clone.classList.add('lucide-28');
                    preview.appendChild(clone);
                }
            }

            function setIcon(slug, opts) {
                opts = opts || {};
                slug = (slug || '').trim();
                hidden.value = slug;
                selectedLabel.textContent = slug || '— none —';
                if (!opts.fromManual) { manual.value = slug; }

                var cell = cellFor(slug);
                var svg = cell ? cell.querySelector('svg') : null;
                setPreviewSvg(svg || placeholderSvg);

                // Highlight the matching gallery cell.
                gallery.querySelectorAll('.icon-cell').forEach(function (c) {
                    var on = c.getAttribute('data-slug') === slug && slug !== '';
                    c.style.borderColor = on ? '#007bff' : '';
                    c.style.background = on ? '#e7f1ff' : '';
                });
            }

            gallery.querySelectorAll('.icon-cell').forEach(function (cell) {
                cell.addEventListener('click', function () { setIcon(cell.getAttribute('data-slug')); });
            });

            manual.addEventListener('input', function () { setIcon(manual.value, { fromManual: true }); });

            filter.addEventListener('input', function () {
                var q = filter.value.trim().toLowerCase();
                gallery.querySelectorAll('.icon-cell').forEach(function (cell) {
                    var match = cell.getAttribute('data-slug').indexOf(q) !== -1;
                    cell.style.display = match ? '' : 'none';
                });
            });
        })();
    </script>
@stop
