@extends('admin.layout', ['title' => 'Quick Add'])

@section('page_title', 'Quick Add')
@section('page_subtitle', 'List a business or community sourced from outreach. You send the welcome email separately, in the right language, from their edit page.')

@section('page_actions')
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Users
    </a>
@endsection

@section('admin_content')
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

    <div class="card card-primary card-outline">
        <form method="post" action="{{ route('admin.users.quick-add.store') }}" id="quick-add-form">
            @csrf
            <div class="card-body">
                @php($userType = old('user_type', 'business'))
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="user_type">User Type</label>
                            <select id="user_type" name="user_type" class="form-control @error('user_type') is-invalid @enderror" required>
                                @foreach ($userTypes as $type)
                                    <option value="{{ $type->value }}" @selected($userType === $type->value)>{{ ucfirst($type->value) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required>
                            <small class="form-text text-muted">Where the welcome email goes once you send it from their edit page.</small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name">Display Name</label>
                            <input id="name" type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="city_id">City</label>
                            <select id="city_id" name="city_id" class="form-control @error('city_id') is-invalid @enderror">
                                <option value="">—</option>
                                @foreach ($cities as $city)
                                    <option value="{{ $city->id }}" @selected(old('city_id') === $city->id)>{{ $city->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="instagram">Instagram</label>
                            <input id="instagram" type="text" name="instagram" value="{{ old('instagram') }}" class="form-control @error('instagram') is-invalid @enderror">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="phone_number">Phone Number</label>
                            <input id="phone_number" type="text" name="phone_number" value="{{ old('phone_number') }}" class="form-control @error('phone_number') is-invalid @enderror">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="website">Website</label>
                            <input id="website" type="url" name="website" value="{{ old('website') }}" class="form-control @error('website') is-invalid @enderror">
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-group">
                            <label for="about">About</label>
                            <textarea id="about" name="about" rows="3" class="form-control @error('about') is-invalid @enderror">{{ old('about') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Create listing</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-default">Cancel</a>
            </div>

            <input type="hidden" name="profile_photo" id="profile_photo">
            <div id="offer-photos-inputs"></div>
            <input type="hidden" name="primary_venue" id="primary_venue">
        </form>
    </div>

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
                document.getElementById('name').value = d.name || '';
                document.getElementById('about').value = d.about || '';
                document.getElementById('website').value = d.website || '';
                if (d.phone_number) { document.getElementById('phone_number').value = d.phone_number; }
                if (d.city_id) { document.getElementById('city_id').value = d.city_id; }

                document.getElementById('primary_venue').value = JSON.stringify(d.primary_venue || {});

                resultsBox.style.display = 'none';
                selectedBox.style.display = 'block';
                selectedName.textContent = d.name || '';

                chosenPhotos = [];
                photosBox.innerHTML = '';
                var photos = (d.primary_venue && d.primary_venue.photos) || [];
                photos.slice(0, 6).forEach(function (photo, idx) {
                    if (!photo.resource_name) { return; }
                    var url = '/api/v1/places/photo?name=' + encodeURIComponent(photo.resource_name) + '&max_width=800';
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
                    if (idx === 0) { chosenPhotos.push(url); }
                    photosBox.appendChild(wrap);
                });
                syncPhotoInputs();
            })
            .catch(function () {
                alert("Couldn't import that place — fill in the form manually.");
            });
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
@endsection
