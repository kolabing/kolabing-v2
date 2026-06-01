@extends('admin.layout', ['title' => 'Edit XP earn rule'])

@section('page_title', 'Edit XP earn rule')
@section('page_subtitle', $rule->event_type)

@section('page_actions')
    <a href="{{ route('admin.gamification.earn-rules.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left mr-1"></i>
        Back
    </a>
@endsection

@section('admin_content')
    <div class="card card-primary card-outline">
        <form method="POST" action="{{ route('admin.gamification.earn-rules.update', $rule) }}">
            @csrf
            @method('PUT')

            <div class="card-body">
                <div class="form-group">
                    <label>Event type <small class="text-muted">(immutable — set by the PointEventType enum)</small></label>
                    <input type="text" value="{{ $rule->event_type }}" class="form-control" disabled readonly>
                </div>

                <div class="form-group">
                    <label for="label">Label</label>
                    <input type="text" name="label" id="label" value="{{ old('label', $rule->label) }}" maxlength="120" class="form-control" required>
                    <small class="form-text text-muted">Display text the app shows next to the points value (e.g. "Complete a Kolab").</small>
                </div>

                <div class="form-group">
                    <label for="points">Points awarded</label>
                    <input type="number" name="points" id="points" min="0" max="10000" value="{{ old('points', $rule->points) }}" class="form-control" required>
                    <small class="form-text text-muted">Single source of truth — both the ledger write and the displayed "+N XP" use this value.</small>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" name="is_active" id="is_active" value="1" class="custom-control-input" {{ old('is_active', $rule->is_active) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="is_active">Active</label>
                    </div>
                    <small class="form-text text-muted">If inactive, the award path falls back to <code>PointEventType::defaultPoints()</code> so users still earn the baked-in amount.</small>
                </div>
            </div>

            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('admin.gamification.earn-rules.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@endsection
