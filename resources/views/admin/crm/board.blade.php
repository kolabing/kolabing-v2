@extends('admin.layout', ['title' => 'CRM — Pipeline'])

@section('page_title', 'CRM — Pipeline')
@section('page_subtitle', 'Community supply-side funnel. Drag a card to move its stage.')

@section('page_actions')
    <a href="{{ route('admin.crm.index', ['type' => 'community']) }}" class="btn btn-outline-secondary">
        <i class="fas fa-table mr-1"></i> Table view
    </a>
@endsection

@section('admin_content')
    @php
        $stageColors = [
            'Target' => '#6c757d', 'Contacted' => '#3d7fd6', 'Interested' => '#8b3fd0',
            'Negotiating' => '#e07b00', 'Onboarded' => '#1f9d57', 'Rejected' => '#c0392b',
        ];
    @endphp

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.crm.board') }}" class="form-inline mb-3" style="gap:.5rem">
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Search name…">
        @if ($cities->isNotEmpty())
            <select name="city" class="form-control form-control-sm">
                <option value="">All cities</option>
                @foreach ($cities as $c)<option value="{{ $c }}" @selected(($filters['city'] ?? '') === $c)>{{ $c }}</option>@endforeach
            </select>
        @endif
        <select name="confidence" class="form-control form-control-sm">
            <option value="">All confidence</option>
            @foreach (['High', 'Med', 'Low'] as $cf)<option value="{{ $cf }}" @selected(($filters['confidence'] ?? '') === $cf)>{{ $cf }}</option>@endforeach
        </select>
        @if ($owners->isNotEmpty())
            <select name="owner" class="form-control form-control-sm">
                <option value="">All owners</option>
                @foreach ($owners as $o)<option value="{{ $o }}" @selected(($filters['owner'] ?? '') === $o)>{{ $o }}</option>@endforeach
            </select>
        @endif
        <button class="btn btn-sm btn-outline-secondary">Filter</button>
        <span class="text-muted ml-2">{{ $total }} communities</span>
    </form>

    <div id="crm-board" data-move-url-base="{{ url('/admin/crm') }}" style="display:flex;gap:.75rem;overflow-x:auto;padding-bottom:.5rem">
        @foreach ($stages as $stage)
            @php $cards = $byStage[$stage] ?? collect(); @endphp
            <div class="crm-col" data-stage="{{ $stage }}"
                style="flex:1 0 210px;min-width:210px;background:#eef1f5;border:1px solid #e4e7ec;border-radius:8px;display:flex;flex-direction:column">
                <div style="padding:.5rem .65rem;border-bottom:1px solid #e4e7ec;font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em;display:flex;align-items:center">
                    <span style="width:9px;height:9px;border-radius:50%;background:{{ $stageColors[$stage] }};display:inline-block;margin-right:.4rem"></span>
                    {{ $stage }}
                    <span class="crm-count badge badge-light ml-auto">{{ $cards->count() }}</span>
                </div>
                <div class="crm-cards" style="padding:.5rem;display:flex;flex-direction:column;gap:.4rem;min-height:60px;overflow-y:auto;max-height:66vh">
                    @foreach ($cards as $a)
                        <div class="crm-card" draggable="true" data-id="{{ $a->id }}"
                            data-stage-url="{{ route('admin.crm.stage', $a) }}"
                            data-show-url="{{ route('admin.crm.show', $a) }}"
                            style="background:#fff;border:1px solid #e4e7ec;border-radius:6px;padding:.5rem .6rem;cursor:grab;box-shadow:0 1px 2px #0000000a">
                            <div style="font-weight:600;font-size:.85rem;margin-bottom:.2rem">{{ $a->name }}</div>
                            <div style="font-size:.75rem;color:#6b7480;display:flex;gap:.4rem;align-items:center">
                                <span>{{ $a->metrics['city'] ?? '—' }}</span>
                                @if ($cf = ($a->metrics['confidence'] ?? null))<span class="badge badge-light">{{ \Illuminate\Support\Str::ucfirst(explode(' ', $cf)[0]) }}</span>@endif
                                <span class="ml-auto font-weight-bold">{{ $a->score }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <script>
            (function () {
                var board = document.getElementById('crm-board');
                if (!board) { return; }
                var csrf = '{{ csrf_token() }}';
                var dragEl = null;

                board.querySelectorAll('.crm-card').forEach(function (card) {
                    card.addEventListener('dragstart', function (e) { dragEl = card; card.style.opacity = '.4'; e.dataTransfer.effectAllowed = 'move'; });
                    card.addEventListener('dragend', function () { card.style.opacity = ''; });
                    // Click (not drag) opens the lead.
                    card.addEventListener('click', function () { window.location = card.dataset.showUrl; });
                });

                board.querySelectorAll('.crm-col').forEach(function (col) {
                    var zone = col.querySelector('.crm-cards');
                    col.addEventListener('dragover', function (e) { e.preventDefault(); col.style.outline = '2px dashed #3d7fd6'; });
                    col.addEventListener('dragleave', function () { col.style.outline = ''; });
                    col.addEventListener('drop', function (e) {
                        e.preventDefault();
                        col.style.outline = '';
                        if (!dragEl) { return; }
                        var toStage = col.dataset.stage;
                        var fromCol = dragEl.closest('.crm-col');
                        if (fromCol === col) { return; }
                        var moving = dragEl;
                        zone.appendChild(moving);
                        recount();
                        fetch(moving.dataset.stageUrl, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                            body: JSON.stringify({ stage: toStage })
                        }).then(function (r) {
                            if (!r.ok) { throw new Error('move failed'); }
                        }).catch(function () {
                            fromCol.querySelector('.crm-cards').appendChild(moving);
                            recount();
                            alert('Could not move the lead — please retry.');
                        });
                    });
                });

                function recount() {
                    board.querySelectorAll('.crm-col').forEach(function (col) {
                        col.querySelector('.crm-count').textContent = col.querySelectorAll('.crm-card').length;
                    });
                }
            })();
    </script>
@endsection
