{{-- Shared form partial for create + edit --}}
<div class="form-row">
    <div class="form-group col-md-8">
        <label for="title">Title</label>
        <input type="text" name="title" id="title" maxlength="255" required class="form-control"
               value="{{ old('title', $reward->title ?? '') }}">
    </div>
    <div class="form-group col-md-4">
        <label for="cost_xp">Cost (XP)</label>
        <input type="number" name="cost_xp" id="cost_xp" min="0" max="1000000" required class="form-control"
               value="{{ old('cost_xp', $reward->cost_xp ?? 500) }}">
    </div>
</div>

<div class="form-group">
    <label for="partner">Partner</label>
    <input type="text" name="partner" id="partner" maxlength="255" required class="form-control"
           value="{{ old('partner', $reward->partner ?? '') }}">
</div>

<div class="form-group">
    <label for="description">Description</label>
    <textarea name="description" id="description" rows="3" maxlength="1000" class="form-control">{{ old('description', $reward->description ?? '') }}</textarea>
</div>

<div class="form-group">
    <label for="image_url">Image URL</label>
    <input type="url" name="image_url" id="image_url" maxlength="2048" class="form-control"
           value="{{ old('image_url', $reward->image_url ?? '') }}">
</div>

<div class="form-group">
    <div class="custom-control custom-switch">
        <input type="checkbox" name="is_active" id="is_active" value="1" class="custom-control-input"
               {{ old('is_active', $reward->is_active ?? true) ? 'checked' : '' }}>
        <label class="custom-control-label" for="is_active">Active (visible in the catalogue)</label>
    </div>
</div>
