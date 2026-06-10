@extends('admin.layout', ['title' => 'Tasks'])

@section('page_title', 'Tasks')
@section('page_subtitle', 'Area (Sales · Marketing · Dev) × Subarea, filterable by person.')

@section('page_actions')
    <a href="{{ route('admin.tasks.create') }}" class="btn btn-primary"><i class="fas fa-plus mr-1"></i> New task</a>
@endsection

@section('admin_content')
    @php
        $areaLabels = ['sales' => 'Sales', 'marketing' => 'Marketing', 'dev' => 'Dev'];
        $subLabels = ['business' => 'Business', 'communities' => 'Communities', 'ambassadors' => 'Ambassadors', 'community_members' => 'Community Members'];
    @endphp

    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ route('admin.tasks.index') }}" class="form-inline" style="gap:.5rem">
                <select name="assignee" class="form-control form-control-sm">
                    <option value="">All people</option>
                    @foreach ($assignees as $p)<option value="{{ $p }}" @selected(($filters['assignee'] ?? '') === $p)>{{ $p }}</option>@endforeach
                </select>
                <select name="area" class="form-control form-control-sm">
                    <option value="">All areas</option>
                    @foreach ($areaLabels as $k => $l)<option value="{{ $k }}" @selected(($filters['area'] ?? '') === $k)>{{ $l }}</option>@endforeach
                </select>
                <select name="subarea" class="form-control form-control-sm">
                    <option value="">All subareas</option>
                    @foreach ($subLabels as $k => $l)<option value="{{ $k }}" @selected(($filters['subarea'] ?? '') === $k)>{{ $l }}</option>@endforeach
                </select>
                <select name="status" class="form-control form-control-sm">
                    @foreach (['active' => 'Open + Doing', 'open' => 'Open', 'doing' => 'Doing', 'done' => 'Done'] as $k => $l)<option value="{{ $k }}" @selected(($filters['status'] ?? 'active') === $k)>{{ $l }}</option>@endforeach
                </select>
                <button class="btn btn-sm btn-outline-secondary">Filter</button>
            </form>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover table-sm mb-0">
                <tbody>
                    @forelse ($grouped as $area => $subs)
                        <tr class="bg-dark text-white"><td colspan="6" class="font-weight-bold text-uppercase">{{ $areaLabels[$area] ?? $area }}</td></tr>
                        @foreach ($subs as $sub => $tasks)
                            <tr class="bg-light"><td colspan="6" class="pl-4 text-muted">{{ $subLabels[$sub] ?? $sub }} ({{ $tasks->count() }})</td></tr>
                            @foreach ($tasks as $task)
                                @php $overdue = $task->due_on && $task->due_on->isPast() && $task->status !== 'done'; @endphp
                                <tr>
                                    <td style="width:36px" class="text-center">
                                        <span class="badge {{ $task->status === 'done' ? 'badge-success' : ($task->status === 'doing' ? 'badge-info' : 'badge-secondary') }}">{{ ucfirst($task->status) }}</span>
                                    </td>
                                    <td class="font-weight-bold">{{ $task->title }}</td>
                                    <td>{{ $task->account?->name ?? '—' }}</td>
                                    <td>{{ $task->assignee ?: '—' }}</td>
                                    <td class="{{ $overdue ? 'text-danger font-weight-bold' : '' }}">{{ $task->due_on?->format('d M') ?? '—' }}{!! $overdue ? ' ‼️' : '' !!}</td>
                                    <td class="text-right pr-3 text-nowrap">
                                        <a href="{{ route('admin.tasks.edit', $task) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form method="POST" action="{{ route('admin.tasks.destroy', $task) }}" class="d-inline" onsubmit="return confirm('Delete task?');">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">×</button></form>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    @empty
                        <tr><td class="text-center text-muted py-4">No tasks match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
