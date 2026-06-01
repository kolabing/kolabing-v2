@extends('admin.layout', ['title' => 'Edit XP level'])

@section('page_title', 'Edit XP level')
@section('page_subtitle', 'Level ' . $level->number . ' — ' . $level->title)

@section('page_actions')
    <a href="{{ route('admin.gamification.levels.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left mr-1"></i>
        Back
    </a>
@endsection

@section('admin_content')
    <div class="card card-primary card-outline">
        <form method="POST" action="{{ route('admin.gamification.levels.update', $level) }}">
            @csrf
            @method('PUT')

            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label>Number <small class="text-muted">(immutable)</small></label>
                        <input type="text" value="{{ $level->number }}" class="form-control" disabled readonly>
                    </div>
                    <div class="form-group col-md-9">
                        <label for="title">Title</label>
                        <input type="text" name="title" id="title" value="{{ old('title', $level->title) }}" maxlength="120" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="min_xp">min_xp</label>
                        <input type="number" name="min_xp" id="min_xp" min="0" max="100000" value="{{ old('min_xp', $level->min_xp) }}" class="form-control" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="max_xp">max_xp <small class="text-muted">(leave blank for the open-ended top)</small></label>
                        <input type="number" name="max_xp" id="max_xp" min="0" max="100000" value="{{ old('max_xp', $level->max_xp) }}" class="form-control">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="color">Color (hex)</label>
                        <input type="text" name="color" id="color" value="{{ old('color', $level->color) }}" pattern="^#[0-9A-Fa-f]{6}$" maxlength="7" class="form-control" required>
                        <small class="form-text text-muted">e.g. #FFD361</small>
                    </div>
                </div>
            </div>

            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('admin.gamification.levels.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@endsection
