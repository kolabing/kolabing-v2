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
        <small class="form-text text-muted">Controls which side may add this challenge to a collaboration. <code>attendee</code> is for self-tracked missions.</small>
    </div>
</div>

{{-- How it is played: does the app open the camera when the pair agrees? (#248) --}}
<div class="form-row">
    <div class="form-group col-md-6">
        <label for="proof_type">When the pair agrees</label>
        <select name="proof_type" id="proof_type" class="form-control">
            @foreach ($proofTypes as $p)
                <option value="{{ $p->value }}" {{ old('proof_type', $challenge->proof_type?->value ?? 'text') === $p->value ? 'selected' : '' }}>
                    {{ $p->value === 'photo' ? 'Open the camera — they take a photo together' : 'No camera — the instruction is the game' }}
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">
            Picks the surface the app opens, and nothing more: a verification that arrives with no photo is still
            accepted, so a denied camera or a dead connection never costs anybody their points.
            <strong>&ldquo;Take a selfie together&rdquo; wants the camera; &ldquo;introduce yourselves&rdquo; does not.</strong>
        </small>
    </div>
</div>

<hr>
<h6 class="text-uppercase text-muted mb-1">Mission settings (optional)</h6>
<p class="small text-muted">
    Set a trigger to make this a self-tracked mission that auto-completes when the member performs the action.
    Leave the trigger empty for a peer-verified event challenge. (Trigger wiring is delivered in a later phase;
    some triggers are seeded but not yet firing.)
</p>

<div class="form-row">
    <div class="form-group col-md-5">
        <label for="trigger_action">Trigger action</label>
        <select name="trigger_action" id="trigger_action" class="form-control">
            <option value="">— None (peer-verified)</option>
            @foreach ($triggers as $t)
                <option value="{{ $t->value }}" {{ old('trigger_action', $challenge->trigger_action?->value ?? '') === $t->value ? 'selected' : '' }}>
                    {{ $t->label() }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-group col-md-3">
        <label for="target_value">Target value</label>
        <input type="number" name="target_value" id="target_value" min="1" max="100000" class="form-control"
               value="{{ old('target_value', $challenge->target_value ?? 1) }}">
    </div>
    <div class="form-group col-md-4">
        <label for="repeat_interval">Repeat interval</label>
        <select name="repeat_interval" id="repeat_interval" class="form-control">
            @foreach ($repeats as $r)
                <option value="{{ $r->value }}" {{ old('repeat_interval', $challenge->repeat_interval?->value ?? 'once') === $r->value ? 'selected' : '' }}>
                    {{ $r->label() }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-6">
        <label for="starts_at">Campaign starts at (optional)</label>
        <input type="datetime-local" name="starts_at" id="starts_at" class="form-control"
               value="{{ old('starts_at', $challenge->starts_at?->format('Y-m-d\TH:i') ?? '') }}">
    </div>
    <div class="form-group col-md-6">
        <label for="ends_at">Campaign ends at (optional)</label>
        <input type="datetime-local" name="ends_at" id="ends_at" class="form-control"
               value="{{ old('ends_at', $challenge->ends_at?->format('Y-m-d\TH:i') ?? '') }}">
    </div>
</div>
