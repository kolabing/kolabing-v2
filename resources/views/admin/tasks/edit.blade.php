@extends('admin.layout', ['title' => $task->exists ? 'Edit task' : 'New task'])

@section('page_title', $task->exists ? 'Edit task' : 'New task')

@section('admin_content')
    <form method="POST" action="{{ $task->exists ? route('admin.tasks.update', $task) : route('admin.tasks.store') }}">
        @csrf
        @if ($task->exists) @method('PUT') @endif

        <div class="card"><div class="card-body">
            <div class="form-group">
                <label>Title</label>
                <input name="title" value="{{ old('title', $task->title) }}" class="form-control" required>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Area</label>
                    <select name="area" class="form-control">
                        @foreach (['sales' => 'Sales', 'marketing' => 'Marketing', 'dev' => 'Dev'] as $k => $l)
                            <option value="{{ $k }}" @selected(old('area', $task->area) === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label>Subarea</label>
                    <select name="subarea" class="form-control">
                        @foreach (['business' => 'Business', 'communities' => 'Communities', 'ambassadors' => 'Ambassadors (Sales/Marketing only)', 'community_members' => 'Community Members'] as $k => $l)
                            <option value="{{ $k }}" @selected(old('subarea', $task->subarea) === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        @foreach (['open' => 'Open', 'doing' => 'Doing', 'done' => 'Done'] as $k => $l)
                            <option value="{{ $k }}" @selected(old('status', $task->status ?? 'open') === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6"><label>Assignee</label><input name="assignee" value="{{ old('assignee', $task->assignee) }}" class="form-control"></div>
                <div class="form-group col-md-6"><label>Due date</label><input type="date" name="due_on" value="{{ old('due_on', $task->due_on?->format('Y-m-d')) }}" class="form-control"></div>
            </div>
            <div class="form-group"><label>Notes</label><textarea name="notes" rows="2" class="form-control">{{ old('notes', $task->notes) }}</textarea></div>
            @if ($task->crm_account_id)
                <p class="text-muted mb-0"><i class="fas fa-link mr-1"></i> Linked to CRM account: <strong>{{ $task->account?->name }}</strong></p>
            @endif
        </div></div>

        <div class="d-flex justify-content-between">
            <a href="{{ route('admin.tasks.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn btn-primary">{{ $task->exists ? 'Save' : 'Create' }}</button>
        </div>
    </form>
@endsection
