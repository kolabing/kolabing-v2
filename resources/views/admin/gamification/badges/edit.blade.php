@extends('admin.layout', ['title' => 'Edit Badge'])

@section('page_title', 'Edit Badge')
@section('page_subtitle', $badge->name)

@section('admin_content')
    <div class="card">
        <form method="POST" action="{{ route('admin.gamification.badges.update', $badge) }}">
            @csrf
            @method('PUT')

            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="form-group">
                    <label>Milestone type</label>
                    <input type="text" class="form-control" value="{{ $badge->milestone_type->value }}" disabled>
                    <small class="form-text text-muted">Identity — not editable. Awarded once the milestone value is reached.</small>
                </div>

                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $badge->name) }}" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3" required>{{ old('description', $badge->description) }}</textarea>
                </div>

                <div class="form-group">
                    <label for="icon">Icon</label>
                    <input type="text" id="icon" name="icon" class="form-control" value="{{ old('icon', $badge->icon) }}" required>
                    <small class="form-text text-muted">Identifier the app uses to render the badge artwork.</small>
                </div>

                <div class="form-group">
                    <label for="milestone_value">Milestone value</label>
                    <input type="number" id="milestone_value" name="milestone_value" class="form-control" value="{{ old('milestone_value', $badge->milestone_value) }}" min="1" required>
                </div>
            </div>

            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="{{ route('admin.gamification.badges.index') }}" class="btn btn-link">Cancel</a>
            </div>
        </form>
    </div>
@endsection
