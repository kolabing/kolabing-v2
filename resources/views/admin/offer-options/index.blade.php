@extends('admin.layout', ['title' => 'Offer options'])

@section('page_title', 'Offer options')
@section('page_subtitle', 'Kolab offer taxonomies — what businesses offer, what is offered in return, and what communities need.')

@section('page_actions')
    <a href="{{ route('admin.offer-options.create', ['kind' => $kind]) }}" class="btn btn-primary">
        <i class="fas fa-plus mr-1"></i> New {{ $labels[$kind] }} option
    </a>
@endsection

@section('admin_content')
    <ul class="nav nav-pills mb-3">
        @foreach ($labels as $k => $label)
            <li class="nav-item">
                <a class="nav-link {{ $kind === $k ? 'active' : '' }}" href="{{ route('admin.offer-options.index', ['kind' => $k]) }}">{{ $label }}</a>
            </li>
        @endforeach
    </ul>

    <p class="text-muted small">
        <strong>Offerings</strong> = what a business offers. <strong>Deliverables</strong> = what's offered in return
        (community gives / business expects). <strong>Needs</strong> = what a community asks for.
        <strong>Product types</strong> / <strong>Venue types</strong> = the product- and venue-promotion pickers. Drag rows to reorder.
        <strong>Deactivate</strong> hides an option from the app without deleting it; an option still in use can't be hard-deleted.
        Slugs are the wire contract — changing one breaks existing kolabs, so edit the name, not the slug.
    </p>

    {{-- hidden form SortableJS submits the new order through --}}
    <form id="reorderForm" method="POST" action="{{ route('admin.offer-options.reorder', ['kind' => $kind]) }}" class="d-none">@csrf</form>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th style="width:32px"></th>
                        <th style="width:56px">Icon</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th class="text-center">In use</th>
                        <th class="text-center">Active</th>
                        <th class="text-right pr-3">Action</th>
                    </tr>
                </thead>
                <tbody id="optionRows">
                    @forelse ($options as $o)
                        <tr data-id="{{ $o->id }}">
                            <td class="text-center text-muted" style="cursor:grab" title="Drag to reorder"><i class="fas fa-grip-vertical"></i></td>
                            <td>
                                @if ($o->icon_url)
                                    <img src="{{ $o->icon_url }}" alt="" style="width:24px;height:24px;object-fit:contain">
                                @elseif ($o->icon)
                                    <i data-lucide="{{ $o->icon }}" style="width:22px;height:22px"></i>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="font-weight-bold">{{ $o->name }}</td>
                            <td><code>{{ $o->slug }}</code></td>
                            <td class="text-center">{{ $o->in_use }}</td>
                            <td class="text-center">
                                <form method="POST" action="{{ route('admin.offer-options.toggle', ['kind' => $kind, 'id' => $o->id]) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm {{ $o->is_active ? 'btn-success' : 'btn-outline-secondary' }}">
                                        {{ $o->is_active ? 'Active' : 'Hidden' }}
                                    </button>
                                </form>
                            </td>
                            <td class="text-right pr-3 text-nowrap">
                                <a href="{{ route('admin.offer-options.edit', ['kind' => $kind, 'id' => $o->id]) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.offer-options.destroy', ['kind' => $kind, 'id' => $o->id]) }}" class="d-inline"
                                    onsubmit="return confirm('{{ $o->in_use ? 'In use — this will DEACTIVATE it. Continue?' : 'Delete this unused option?' }}');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">{{ $o->in_use ? 'Retire' : 'Delete' }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No options yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>
        (function () {
            if (window.lucide) window.lucide.createIcons();
            var rows = document.getElementById('optionRows');
            var form = document.getElementById('reorderForm');
            if (rows && window.Sortable) {
                new Sortable(rows, {
                    handle: '.fa-grip-vertical', animation: 150,
                    onEnd: function () {
                        form.querySelectorAll('input[name="order[]"]').forEach(function (n) { n.remove(); });
                        rows.querySelectorAll('tr[data-id]').forEach(function (tr) {
                            var i = document.createElement('input');
                            i.type = 'hidden'; i.name = 'order[]'; i.value = tr.getAttribute('data-id');
                            form.appendChild(i);
                        });
                        form.submit();
                    }
                });
            }
        })();
    </script>
@endsection
