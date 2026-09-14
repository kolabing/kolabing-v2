@php
    $template = $template ?? null;
@endphp

<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label for="locale">Locale code</label>
            <input id="locale" type="text" name="locale" value="{{ old('locale', $template?->locale) }}" class="form-control @error('locale') is-invalid @enderror" placeholder="es" required>
            <small class="form-text text-muted">Short code, e.g. en, es, ca, pt-BR.</small>
        </div>
    </div>

    <div class="col-md-5">
        <div class="form-group">
            <label for="label">Language name (shown to maintainers)</label>
            <input id="label" type="text" name="label" value="{{ old('label', $template?->label) }}" class="form-control @error('label') is-invalid @enderror" placeholder="Español" required>
        </div>
    </div>

    <div class="col-md-4">
        <div class="form-group">
            <label for="is_active">Status</label>
            <div class="custom-control custom-switch mt-2">
                <input id="is_active" type="checkbox" name="is_active" value="1" class="custom-control-input" @checked(old('is_active', $template?->is_active ?? true))>
                <label class="custom-control-label" for="is_active">Active (selectable when sending)</label>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="form-group">
            <label for="subject">Subject line</label>
            <input id="subject" type="text" name="subject" value="{{ old('subject', $template?->subject) }}" class="form-control @error('subject') is-invalid @enderror" required>
        </div>
    </div>

    <div class="col-12">
        <div class="form-group">
            <label for="intro_markdown">Intro (welcome + what they get)</label>
            <textarea id="intro_markdown" name="intro_markdown" rows="8" class="form-control @error('intro_markdown') is-invalid @enderror" required>{{ old('intro_markdown', $template?->intro_markdown) }}</textarea>
            <small class="form-text text-muted">Markdown. Use <code>@{{name}}</code> and <code>@{{who}}</code> — filled in automatically per recipient. Rendered before the "View your listing" button, which is fixed and not editable here.</small>
        </div>
    </div>

    <div class="col-12">
        <div class="form-group">
            <label for="next_steps_markdown">What happens next</label>
            <textarea id="next_steps_markdown" name="next_steps_markdown" rows="4" class="form-control @error('next_steps_markdown') is-invalid @enderror" required>{{ old('next_steps_markdown', $template?->next_steps_markdown) }}</textarea>
            <small class="form-text text-muted">Rendered before the "Set your password" button, which is fixed and not editable here.</small>
        </div>
    </div>

    <div class="col-12">
        <div class="form-group">
            <label for="footer_markdown">Footer / sign-off</label>
            <textarea id="footer_markdown" name="footer_markdown" rows="4" class="form-control @error('footer_markdown') is-invalid @enderror" required>{{ old('footer_markdown', $template?->footer_markdown) }}</textarea>
        </div>
    </div>
</div>
