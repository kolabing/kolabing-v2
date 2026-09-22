{{--
    Places autocomplete on the venue address — the same two endpoints the mobile
    onboarding uses, and the same ones the Maps import card above already calls
    (`/api/v1/places/{autocomplete,details}`). No new integration.

    Why it is needed even though the import card exists: the import card searches by
    BUSINESS NAME and fills the whole form. This field is the other half — a
    maintainer correcting or supplying just the address, which until now was a plain
    text input where a typo produced a venue nobody can find.

    Selecting a suggestion fills the address AND the coordinates/place id/city that
    came with it, so a hand-corrected address carries the same data as an imported
    one instead of being a lone string.
--}}
<input id="venue_address" type="text" name="venue[formatted_address]"
       value="{{ old('venue.formatted_address') }}" autocomplete="off"
       class="form-control @error('primary_venue.formatted_address') is-invalid @enderror"
       placeholder="Start typing an address…">

{{-- Carries what the chosen place knows beyond its address; merged into
     primary_venue by AdminBusinessOnboardingRequest. --}}
<input type="hidden" name="venue[place_id]" id="venue_place_id" value="{{ old('venue.place_id') }}">
<input type="hidden" name="venue[latitude]" id="venue_latitude" value="{{ old('venue.latitude') }}">
<input type="hidden" name="venue[longitude]" id="venue_longitude" value="{{ old('venue.longitude') }}">

<div id="venue-address-results" class="list-group position-absolute shadow"
     style="display:none; z-index:1000; max-height:240px; overflow-y:auto; width:calc(100% - 30px);"></div>

<small class="form-text text-muted">
    Type to search, or leave it — the Maps import above fills it in too.
</small>

@push('admin_scripts')
<script>
(function () {
    var input = document.getElementById('venue_address');
    var box = document.getElementById('venue-address-results');
    if (!input || !box) { return; }

    var debounce = null;
    var lastQuery = '';

    function hide() { box.style.display = 'none'; box.innerHTML = ''; }

    /**
     * No second request: /places/autocomplete already returns formatted_address,
     * city, city_id and coordinates on every row. Calling /places/details here
     * would spend a Places lookup to learn what we were just told.
     */
    function choose(place) {
        input.value = place.formatted_address || place.title || '';
        hide();

        setIfPresent('venue_place_id', place.place_id);
        setIfPresent('venue_latitude', place.latitude);
        setIfPresent('venue_longitude', place.longitude);

        // Only fill fields the maintainer left empty — the same rule the Maps
        // import card follows, so a curated value is never clobbered.
        fillIfEmpty('venue_city', place.city);
        fillIfEmpty('city_id', place.city_id);
    }

    function setIfPresent(id, value) {
        var el = document.getElementById(id);
        if (el && value !== undefined && value !== null && value !== '') { el.value = value; }
    }

    function fillIfEmpty(id, value) {
        var el = document.getElementById(id);
        if (el && !el.value && value) { el.value = value; }
    }

    function search(query) {
        fetch('/api/v1/places/autocomplete?query=' + encodeURIComponent(query), {
            headers: { 'Accept': 'application/json' },
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                var rows = (json && json.data) || [];
                if (!rows.length) { hide(); return; }

                box.innerHTML = '';
                rows.slice(0, 6).forEach(function (row) {
                    var item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'list-group-item list-group-item-action';

                    var name = document.createElement('strong');
                    name.textContent = row.title || '';
                    var detail = document.createElement('small');
                    detail.className = 'text-muted d-block';
                    detail.textContent = row.subtitle || row.formatted_address || '';

                    item.appendChild(name);
                    item.appendChild(detail);
                    item.addEventListener('click', function () { choose(row); });
                    box.appendChild(item);
                });
                box.style.display = 'block';
            })
            .catch(hide);
    }

    input.addEventListener('input', function () {
        var query = input.value.trim();
        clearTimeout(debounce);

        if (query.length < 3 || query === lastQuery) { hide(); return; }

        debounce = setTimeout(function () {
            lastQuery = query;
            search(query);
        }, 300);
    });

    // Clicking anywhere else dismisses the list; without this it stays open over
    // the fields below it.
    document.addEventListener('click', function (event) {
        if (event.target !== input && !box.contains(event.target)) { hide(); }
    });
})();
</script>
@endpush
