@extends('admin.layout', ['title' => 'Types'])

@section('page_title', 'Types')
@section('page_subtitle', 'Community & business type taxonomies — the lists the app shows (and filters by).')

@section('page_actions')
    <a href="{{ route('admin.types.create', ['kind' => $kind]) }}" class="btn btn-primary">
        <i class="fas fa-plus mr-1"></i> New {{ $kind }} type
    </a>
@endsection

@section('admin_content')
    <ul class="nav nav-pills mb-3">
        @foreach (['community' => 'Community types', 'business' => 'Business types'] as $k => $label)
            <li class="nav-item">
                <a class="nav-link {{ $kind === $k ? 'active' : '' }}" href="{{ route('admin.types.index', ['kind' => $k]) }}">{{ $label }}</a>
            </li>
        @endforeach
    </ul>

    <p class="text-muted small">
        Drag rows to reorder. <strong>Deactivate</strong> hides a type from the app without deleting it; a type still
        in use can't be hard-deleted. Icon preview uses the Lucide set; uploaded SVGs show as-is.
    </p>

    {{-- hidden form SortableJS submits the new order through --}}
    <form id="reorderForm" method="POST" action="{{ route('admin.types.reorder', ['kind' => $kind]) }}" class="d-none">@csrf</form>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th style="width:32px"></th>
                        <th style="width:56px">Icon</th>
                        <th>Name</th>
                        <th>Slug</th>
                        @if ($kind === 'business')
                            <th>Shows for</th>
                        @endif
                        <th class="text-center">In use</th>
                        <th class="text-center">Active</th>
                        <th class="text-right pr-3">Action</th>
                    </tr>
                </thead>
                <tbody id="typeRows">
                    @forelse ($types as $t)
                        <tr data-id="{{ $t->id }}">
                            <td class="text-center text-muted" style="cursor:grab" title="Drag to reorder"><i class="fas fa-grip-vertical"></i></td>
                            <td>
                                @if ($t->icon_url)
                                    <img src="{{ $t->icon_url }}" alt="" style="width:24px;height:24px;object-fit:contain">
                                @elseif ($t->icon)
                                    <i data-lucide="{{ $t->icon }}" style="width:22px;height:22px"></i>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="font-weight-bold">{{ $t->name }}</td>
                            <td><code>{{ $t->slug }}</code></td>
                            @if ($kind === 'business')
                                <td>
                                    @php($a = $t->applies_to ?? 'both')
                                    <span class="badge badge-{{ $a === 'venue' ? 'info' : ($a === 'product' ? 'warning' : 'secondary') }}">
                                        {{ $a === 'venue' ? 'Venue' : ($a === 'product' ? 'Product / service' : 'Both') }}
                                    </span>
                                </td>
                            @endif
                            <td class="text-center">{{ $t->in_use }}</td>
                            <td class="text-center">
                                <form method="POST" action="{{ route('admin.types.toggle', ['kind' => $kind, 'id' => $t->id]) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm {{ $t->is_active ? 'btn-success' : 'btn-outline-secondary' }}">
                                        {{ $t->is_active ? 'Active' : 'Hidden' }}
                                    </button>
                                </form>
                            </td>
                            <td class="text-right pr-3 text-nowrap">
                                <a href="{{ route('admin.types.edit', ['kind' => $kind, 'id' => $t->id]) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.types.destroy', ['kind' => $kind, 'id' => $t->id]) }}" class="d-inline"
                                    onsubmit="return confirm('{{ $t->in_use ? 'In use — this will DEACTIVATE it. Continue?' : 'Delete this unused type?' }}');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">{{ $t->in_use ? 'Retire' : 'Delete' }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $kind === 'business' ? 8 : 7 }}" class="text-center text-muted py-4">No types yet.</td></tr>
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
            var rows = document.getElementById('typeRows');
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
