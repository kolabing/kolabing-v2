{{--
    Choice chips backed by an admin-managed lookup endpoint — the same control the
    mobile app renders, against the same data.

    The app's onboarding does not use <select> for these: venue type is a row of
    tappable chips (`business_step2_screen.dart`) and offerings are multi-select
    toggle cards (`offering_screen.dart`), both fed by `/api/v1/lookup/*`, which
    reads the `offer_options` table. This form used a hardcoded enum instead, so an
    option a maintainer added in /admin/offer-options appeared on phones and not
    here. Reading the endpoint means there is one source of truth again.

    Params:
      $chipId    — unique DOM id prefix
      $chipName  — form field name; `[]` is appended automatically when multiple
      $endpoint  — /api/v1/lookup/… path
      $multiple  — bool, default false
      $selected  — array<string>|string|null, pre-checked slugs (old() input)

    The endpoints sit in the public API group (same as the Maps import card already
    calls), so no token is needed here.
--}}
@php
    $chipMultiple = $multiple ?? false;
    $chipSelected = collect((array) ($selected ?? []))->filter()->values()->all();
    $chipField = $chipMultiple ? $chipName.'[]' : $chipName;
@endphp

<div id="{{ $chipId }}-wrap" class="kb-chips" data-endpoint="{{ $endpoint }}"
     data-field="{{ $chipField }}" data-multiple="{{ $chipMultiple ? '1' : '0' }}"
     data-selected="{{ json_encode($chipSelected) }}">
    <div class="kb-chips-list d-flex flex-wrap" style="gap:.5rem"></div>
    <div class="kb-chips-inputs"></div>
    <small class="form-text text-muted kb-chips-status">Loading options…</small>
</div>

@once
    @push('admin_styles')
        <style>
            .kb-chip {
                display: inline-flex; align-items: center; gap: .4rem;
                padding: .45rem .85rem; border-radius: 12px; cursor: pointer;
                border: 1px solid #ced4da; background: #f4f6f9; font-size: .9rem;
                user-select: none; transition: background .15s, border-color .15s;
            }
            .kb-chip:hover { border-color: #adb5bd; }
            .kb-chip.is-selected { background: #ffd43b; border-color: #f0b400; font-weight: 700; }
            .kb-chip img { width: 18px; height: 18px; object-fit: contain; }
        </style>
    @endpush

    @push('admin_scripts')
        <script>
        (function () {
            // One initialiser for every chip group on the page.
            document.querySelectorAll('.kb-chips').forEach(function (wrap) {
                var list = wrap.querySelector('.kb-chips-list');
                var inputs = wrap.querySelector('.kb-chips-inputs');
                var status = wrap.querySelector('.kb-chips-status');
                var multiple = wrap.dataset.multiple === '1';
                var field = wrap.dataset.field;
                var chosen = [];

                try { chosen = JSON.parse(wrap.dataset.selected || '[]'); } catch (e) { chosen = []; }

                function syncInputs() {
                    inputs.innerHTML = '';
                    chosen.forEach(function (value) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = field;
                        input.value = value;
                        inputs.appendChild(input);
                    });
                }

                function render(options) {
                    list.innerHTML = '';
                    options.forEach(function (option) {
                        var chip = document.createElement('span');
                        chip.className = 'kb-chip' + (chosen.indexOf(option.value) !== -1 ? ' is-selected' : '');
                        chip.dataset.value = option.value;

                        if (option.icon_url) {
                            var img = document.createElement('img');
                            img.src = option.icon_url;
                            img.alt = '';
                            chip.appendChild(img);
                        }

                        chip.appendChild(document.createTextNode(option.label || option.value));

                        chip.addEventListener('click', function () {
                            var at = chosen.indexOf(option.value);
                            if (multiple) {
                                if (at === -1) { chosen.push(option.value); } else { chosen.splice(at, 1); }
                            } else {
                                // Single select: clicking the chosen one clears it, so a
                                // field that is not required can be un-answered.
                                chosen = at === -1 ? [option.value] : [];
                            }
                            syncInputs();
                            render(options);
                        });

                        list.appendChild(chip);
                    });

                    status.textContent = multiple ? 'Select all that apply.' : 'Pick one.';
                }

                syncInputs();

                fetch(wrap.dataset.endpoint, { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (json) {
                        var options = (json && json.data) || [];
                        if (!options.length) {
                            status.textContent = 'No options are configured for this list.';
                            return;
                        }
                        render(options);
                    })
                    .catch(function () {
                        // Say so rather than render an empty row that looks like
                        // "there is nothing to choose".
                        status.textContent = "Couldn't load the options — reload the page.";
                    });
            });
        })();
        </script>
    @endpush
@endonce
