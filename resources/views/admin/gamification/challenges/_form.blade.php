{{-- Shared form partial for create + edit --}}
<div class="form-row">
    <div class="form-group col-md-8">
        <label for="name">Name</label>
        <input type="text" name="name" id="name" maxlength="150" required class="form-control"
               value="{{ old('name', $challenge->name ?? '') }}">
    </div>
    <div class="form-group col-md-4">
        <label for="points">Points</label>
        <input type="number" name="points" id="points" min="0" max="10000" required class="form-control"
               value="{{ old('points', $challenge->points ?? 10) }}">
    </div>
</div>

<div class="form-group">
    <label for="description">Description</label>
    <textarea name="description" id="description" rows="3" maxlength="1000" class="form-control">{{ old('description', $challenge->description ?? '') }}</textarea>
</div>

<div class="form-row">
    <div class="form-group col-md-4">
        <label for="category">Category</label>
        <select name="category" id="category" class="form-control">
            <option value="">—</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->value }}" {{ old('category', $challenge->category?->value ?? '') === $cat->value ? 'selected' : '' }}>
                    {{ ucfirst(str_replace('_', ' ', $cat->value)) }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-group col-md-4">
        <label for="difficulty">Difficulty</label>
        <select name="difficulty" id="difficulty" required class="form-control">
            @foreach ($difficulties as $d)
                <option value="{{ $d->value }}" {{ old('difficulty', $challenge->difficulty?->value ?? '') === $d->value ? 'selected' : '' }}>
                    {{ ucfirst($d->value) }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-group col-md-4">
        <label for="audience">Audience</label>
        <select name="audience" id="audience" required class="form-control">
            @foreach ($audiences as $a)
                <option value="{{ $a->value }}" {{ old('audience', $challenge->audience?->value ?? 'both') === $a->value ? 'selected' : '' }}>
                    {{ $a->label() }}
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">Controls which side may add this challenge to a collaboration.</small>
    </div>
</div>
