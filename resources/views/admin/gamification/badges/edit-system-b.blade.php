@extends('admin.layout', ['title' => 'Edit Badge'])

@section('page_title', 'Edit Badge')
@section('page_subtitle', $defaults['name'])

@section('admin_content')
    <div class="card">
        <form method="POST" action="{{ route('admin.gamification.badges.system-b.update', $slug->value) }}">
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
                    <label>Slug</label>
                    <input type="text" class="form-control" value="{{ $slug->value }}" disabled>
                    <small class="form-text text-muted">Identity — not editable. Awarded via earned_badges when the trigger fires in code.</small>
                </div>

                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" class="form-control"
                           value="{{ old('name', $override?->name) }}"
                           placeholder="{{ $defaults['name'] }}">
                    <small class="form-text text-muted">Leave blank to use the enum default: <code>{{ $defaults['name'] }}</code></small>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"
                              placeholder="{{ $defaults['description'] }}">{{ old('description', $override?->description) }}</textarea>
                    <small class="form-text text-muted">Leave blank to use the enum default: <code>{{ $defaults['description'] }}</code></small>
                </div>

                <div class="form-group">
                    <label for="icon">Icon</label>
                    <input type="text" id="icon" name="icon" class="form-control"
                           value="{{ old('icon', $override?->icon) }}">
                    <small class="form-text text-muted">Identifier the app uses to render the badge artwork. Optional.</small>
                </div>

                <div class="form-group">
                    <label>Audiences</label>
                    @php
                        $selected = old('audiences', $override?->audiences ?? $defaults['audiences']);
                    @endphp
                    <div class="form-check">
                        <input type="checkbox" id="aud-business" name="audiences[]" value="business"
                               class="form-check-input" {{ in_array('business', $selected, true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="aud-business">Business</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" id="aud-community" name="audiences[]" value="community"
                               class="form-check-input" {{ in_array('community', $selected, true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="aud-community">Community</label>
                    </div>
                    <small class="form-text text-muted">
                        Enum default: <code>{{ implode(', ', $defaults['audiences']) }}</code>
                    </small>
                </div>
            </div>

            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save overrides</button>
                <a href="{{ route('admin.gamification.badges.index') }}" class="btn btn-link">Cancel</a>
            </div>
        </form>
    </div>
@endsection
