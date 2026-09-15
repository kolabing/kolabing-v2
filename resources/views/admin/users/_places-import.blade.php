{{--
    Shared "Pull from Google Maps" import card, used by both the quick-add form and the
    regular create/edit form (see admin.users.form). Previously duplicated only in
    quick-add.blade.php, which meant the edit page could never add photos/logo/venue data
    to a profile after creation -- Daniel 2026-09-14: "it used the normal add ... merge
    them". Single source of truth now; fix it once, both forms get the fix.

    Writes into whatever form fields with these ids exist on the page: name, about,
    website, phone_number, city_id (optional -- guarded, not every form has one),
    primary_venue, profile_photo, offer-photos-inputs.

    Defaults the hidden fields to the profile's CURRENT photo/venue data (when
    $detailProfile is in scope, i.e. included from admin.users.form on the edit page) so
    saving the edit form without touching this card doesn't null out existing photos --
    upsertDetailProfile() treats an empty submitted value as "clear this field".
--}}
@php
    $existingOfferPhotos = $detailProfile->offer_photos ?? [];
    $existingBusinessType = $detailProfile->business_type ?? '';
@endphp
<div class="card card-outline card-info mb-3" id="places-import-card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-map-marker-alt mr-1"></i> Pull from Google Maps</h3>
    </div>
    <div class="card-body">
        <div class="form-group">
            <label for="places-query">Search by name + city/colonia</label>
            <div class="input-group">
                <input type="text" id="places-query" class="form-control" placeholder="e.g. Exploradores de Café Cuajimalpa CDMX" autocomplete="off">
                <div class="input-group-append">
                    <button type="button" id="places-search-btn" class="btn btn-info">Search</button>
                </div>
            </div>
            <small class="form-text text-muted">Fills in the form below — name, about, website, address and photos. Review before submitting.</small>
        </div>
        <div id="places-results" class="list-group" style="display:none; max-height: 260px; overflow-y: auto;"></div>
        <div id="places-selected" style="display:none;">
            <hr>
            <strong id="places-selected-name"></strong>
            <div id="places-photos" class="d-flex flex-wrap mt-2" style="gap: 8px;"></div>
            <small class="form-text text-muted mt-1">First photo becomes the profile photo. Click a thumbnail to remove it.</small>
        </div>
    </div>
</div>

<input type="hidden" name="profile_photo" id="profile_photo" value="{{ old('profile_photo', $detailProfile->profile_photo ?? '') }}">
<div id="offer-photos-inputs">
    @foreach (old('offer_photos', $existingOfferPhotos) as $photoUrl)
        <input type="hidden" name="offer_photos[]" value="{{ $photoUrl }}">
    @endforeach
</div>
<input type="hidden" name="primary_venue" id="primary_venue" value="{{ old('primary_venue', isset($detailProfile) && $detailProfile->primary_venue ? json_encode($detailProfile->primary_venue) : '') }}">
<input type="hidden" name="opening_hours" id="opening_hours" value="{{ old('opening_hours', isset($detailProfile) && $detailProfile->opening_hours ? json_encode($detailProfile->opening_hours) : '') }}">
{{--
    opening_hours: GooglePlacesService already resolves this (a plain array of
    "Weekday: open – close" strings) and nests it inside every /places/details
    response's `primary_venue.opening_hours` -- captured here the same way
    business_type was (2026-09-15 benchmark pass): every benchmarked platform
    (Yelp/Google Business Profile/TripAdvisor) shows hours on every listing.
--}}
<input type="hidden" name="business_type" id="business_type" value="{{ old('business_type', $existingBusinessType) }}">
{{--
    business_type: GooglePlacesService already maps Google's place `types` to a
    business_types.slug (see mapBusinessCategories()) and returns it as `business_type`
    on every /places/details response -- this field was simply never captured into the
    form. Without it every imported listing falls back to the generic "Business"/
    "Community" label on the public page (PublicProfilePageController::typeLabel())
    instead of a real category like "Coffee Shop". Caught 2026-09-15 benchmarking
    against Yelp/Google Business Profile/TripAdvisor, all of which show a real category
    label, never a generic one.
--}}

<script>
(function () {
    var queryInput = document.getElementById('places-query');
    var searchBtn = document.getElementById('places-search-btn');
    var resultsBox = document.getElementById('places-results');
    var selectedBox = document.getElementById('places-selected');
    var selectedName = document.getElementById('places-selected-name');
    var photosBox = document.getElementById('places-photos');
    var chosenPhotos = [];

    function runSearch() {
        var q = queryInput.value.trim();
        if (q.length < 2) { return; }

        fetch('/api/v1/places/autocomplete?query=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json' },
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                resultsBox.innerHTML = '';
                var places = (json && json.data) || [];
                if (places.length === 0) {
                    resultsBox.innerHTML = '<div class="list-group-item text-muted">No matches — try a more specific search, or fill in the form manually.</div>';
                    resultsBox.style.display = 'block';
                    return;
                }
                places.forEach(function (place) {
                    var item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'list-group-item list-group-item-action';
                    item.innerHTML = '<strong>' + escapeHtml(place.title || '') + '</strong><br><small class="text-muted">' + escapeHtml(place.subtitle || place.formatted_address || '') + '</small>';
                    item.addEventListener('click', function () { selectPlace(place.place_id); });
                    resultsBox.appendChild(item);
                });
                resultsBox.style.display = 'block';
            })
            .catch(function () {
                resultsBox.innerHTML = '<div class="list-group-item text-danger">Search failed — fill in the form manually.</div>';
                resultsBox.style.display = 'block';
            });
    }

    function selectPlace(placeId) {
        fetch('/api/v1/places/details?place_id=' + encodeURIComponent(placeId), {
            headers: { 'Accept': 'application/json' },
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json || !json.success) {
                    alert("Couldn't import that place — fill in the form manually.");
                    return;
                }
                var d = json.data;
                // Only fill fields that are currently EMPTY -- on a fresh quick-add
                // form this is every field, so behaviour there is unchanged. On an
                // edit page (re-importing just to refresh photos/venue on an EXISTING
                // listing), this stops the import from silently clobbering a name/
                // about/website a maintainer already curated. Caught live 2026-09-15:
                // a re-import intended to just grab more photos overwrote a listing's
                // chosen display name with Google's own listing name.
                setValueIfEmpty('name', d.name || '');
                setValueIfEmpty('about', d.about || '');
                setValueIfEmpty('website', d.website || '');
                if (d.phone_number) { setValueIfEmpty('phone_number', d.phone_number); }
                if (d.city_id) { setValueIfEmpty('city_id', d.city_id); }
                if (d.business_type) { setValueIfEmpty('business_type', d.business_type); }

                document.getElementById('primary_venue').value = JSON.stringify(d.primary_venue || {});
                var hoursField = document.getElementById('opening_hours');
                if (hoursField && !hoursField.value && d.primary_venue && Array.isArray(d.primary_venue.opening_hours) && d.primary_venue.opening_hours.length) {
                    hoursField.value = JSON.stringify(d.primary_venue.opening_hours);
                }

                resultsBox.style.display = 'none';
                selectedBox.style.display = 'block';
                selectedName.textContent = d.name || '';

                chosenPhotos = [];
                photosBox.innerHTML = '';
                var photos = (d.primary_venue && d.primary_venue.photos) || [];
                photos.slice(0, 6).forEach(function (photo, idx) {
                    if (!photo.resource_name) { return; }
                    // Absolute URL required: the request classes validate profile_photo/
                    // offer_photos.* with the url rule, which rejects a bare relative path.
                    var url = window.location.origin + '/api/v1/places/photo?name=' + encodeURIComponent(photo.resource_name) + '&max_width=800';
                    var wrap = document.createElement('div');
                    wrap.style.cssText = 'width:100px;height:100px;cursor:pointer;border:3px solid ' + (idx === 0 ? '#17a2b8' : 'transparent') + ';border-radius:4px;overflow:hidden;';
                    var img = document.createElement('img');
                    img.src = url;
                    img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
                    wrap.appendChild(img);
                    wrap.addEventListener('click', function () {
                        var pos = chosenPhotos.indexOf(url);
                        if (pos === -1) {
                            chosenPhotos.push(url);
                            wrap.style.opacity = '1';
                        } else {
                            chosenPhotos.splice(pos, 1);
                            wrap.style.opacity = '0.3';
                        }
                        syncPhotoInputs();
                    });
                    // Help text says "click a thumbnail to remove it" -- every fetched photo
                    // must actually start selected to match, not just idx 0. Bug caught live
                    // 2026-09-15: only idx 0 was auto-pushed, so a maintainer who didn't
                    // individually click all 5 remaining thumbnails ended up with a listing
                    // that had only 1 photo in the DB no matter how many the gallery rendering
                    // fix (offer_photos wiring) could show.
                    chosenPhotos.push(url);
                    photosBox.appendChild(wrap);
                });
                syncPhotoInputs();
            })
            .catch(function () {
                alert("Couldn't import that place — fill in the form manually.");
            });
    }

    function setValueIfEmpty(id, value) {
        var el = document.getElementById(id);
        if (el && !el.value) { el.value = value; }
    }

    function syncPhotoInputs() {
        document.getElementById('profile_photo').value = chosenPhotos[0] || '';
        var container = document.getElementById('offer-photos-inputs');
        container.innerHTML = '';
        chosenPhotos.forEach(function (url) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'offer_photos[]';
            input.value = url;
            container.appendChild(input);
        });
    }

    function escapeHtml(s) {
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    searchBtn.addEventListener('click', runSearch);
    queryInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); runSearch(); }
    });
})();
</script>
