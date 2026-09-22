@extends('admin.layout', ['title' => 'Full Onboarding'])

@php($isBusiness = $userType === \App\Enums\UserType::Business)

@section('page_title', 'Full Onboarding')
@section('page_subtitle', 'Every step the mobile wizard asks for. The profile this creates is indistinguishable from one the owner onboarded themselves — same service, same rules, same auto-provisioned first Kolab.')

@section('page_actions')
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Users
    </a>
@endsection

@section('admin_content')
    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Couldn't onboard — please fix:</strong>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- The wizard's first question, asked the same way: business or community.
         Past this point the two flows share almost nothing, so they are two forms
         posting to two routes rather than one form with a type field. --}}
    <div class="btn-group mb-3" role="group">
        <a href="{{ route('admin.users.onboard', ['type' => 'business']) }}"
           class="btn {{ $isBusiness ? 'btn-primary' : 'btn-outline-primary' }}">
            <i class="fas fa-store mr-1"></i> Business
        </a>
        <a href="{{ route('admin.users.onboard', ['type' => 'community']) }}"
           class="btn {{ $isBusiness ? 'btn-outline-primary' : 'btn-primary' }}">
            <i class="fas fa-users mr-1"></i> Community
        </a>
    </div>

    <div class="card card-primary card-outline">
        <form method="post"
              action="{{ $isBusiness ? route('admin.users.onboard.business') : route('admin.users.onboard.community') }}"
              id="onboard-form">
            @csrf

            @include('admin.users._places-import')

            <div class="card-body">

                {{-- ── Account ────────────────────────────────────────────── --}}
                <h5 class="mb-3 text-muted"><i class="fas fa-envelope mr-1"></i> Account</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}"
                                   class="form-control @error('email') is-invalid @enderror" required>
                            <small class="form-text text-muted">
                                No password is set here. The owner creates one from the welcome email,
                                which you send from their edit page in the right language.
                            </small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="phone_number">Phone Number</label>
                            <input id="phone_number" type="text" name="phone_number" value="{{ old('phone_number') }}"
                                   class="form-control @error('phone_number') is-invalid @enderror"
                                   placeholder="+34612345678">
                            <small class="form-text text-muted">E.164 format, or leave empty.</small>
                        </div>
                    </div>
                </div>

                @if ($isBusiness)
                    {{-- ── Goal ───────────────────────────────────────────── --}}
                    <hr>
                    <h5 class="mb-3 text-muted"><i class="fas fa-bullseye mr-1"></i> Goal</h5>
                    <p class="text-muted small">
                        The wizard's branching question. A venue gets a venue-promotion Kolab built
                        from its address and capacity; a product gets a product-promotion Kolab that
                        needs a city to target instead.
                    </p>
                    <div class="form-group">
                        @php($hasVenue = old('has_venue', '1'))
                        <div class="custom-control custom-radio">
                            <input class="custom-control-input" type="radio" id="has_venue_yes" name="has_venue" value="1" @checked($hasVenue === '1')>
                            <label class="custom-control-label" for="has_venue_yes">Has a venue — promote the place</label>
                        </div>
                        <div class="custom-control custom-radio">
                            <input class="custom-control-input" type="radio" id="has_venue_no" name="has_venue" value="0" @checked($hasVenue === '0')>
                            <label class="custom-control-label" for="has_venue_no">No venue — promote a product</label>
                        </div>
                    </div>

                    {{-- ── Identity ───────────────────────────────────────── --}}
                    <hr>
                    <h5 class="mb-3 text-muted"><i class="fas fa-id-card mr-1"></i> Identity</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name">Business Name</label>
                                <input id="name" type="text" name="name" value="{{ old('name') }}"
                                       class="form-control @error('name') is-invalid @enderror" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="business_type">Primary Category</label>
                                <select id="business_type" name="business_type" class="form-control @error('business_type') is-invalid @enderror">
                                    <option value="">—</option>
                                    @foreach ($businessTypes as $type)
                                        <option value="{{ $type->slug }}" @selected(old('business_type') === $type->slug)>{{ $type->name }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Auto-filled by the Maps import above when it can resolve one.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="categories">All Categories</label>
                                <select id="categories" name="categories[]" multiple size="6"
                                        class="form-control @error('categories') is-invalid @enderror">
                                    @foreach ($businessTypes as $type)
                                        <option value="{{ $type->slug }}" @selected(in_array($type->slug, old('categories', []), true))>{{ $type->name }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Up to 3. Leave empty to use the primary category alone.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="about">About</label>
                                <textarea id="about" name="about" rows="6"
                                          class="form-control @error('about') is-invalid @enderror"
                                          maxlength="1000">{{ old('about') }}</textarea>
                                <small class="form-text text-muted">Max 1000 characters.</small>
                            </div>
                        </div>
                    </div>

                    {{-- ── Where ──────────────────────────────────────────── --}}
                    <hr>
                    <h5 class="mb-3 text-muted"><i class="fas fa-map-marker-alt mr-1"></i> Where</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="city_id">City</label>
                                <select id="city_id" name="city_id" class="form-control @error('city_id') is-invalid @enderror">
                                    <option value="">—</option>
                                    @foreach ($cities as $city)
                                        <option value="{{ $city->id }}" @selected(old('city_id') === $city->id)>{{ $city->name }} ({{ $city->country }})</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Required when there is no venue.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="city_name">City Name (unlisted)</label>
                                <input id="city_name" type="text" name="city_name" value="{{ old('city_name') }}"
                                       class="form-control @error('city_name') is-invalid @enderror">
                                <small class="form-text text-muted">Only for a city that is not in the picker yet.</small>
                            </div>
                        </div>
                        <div class="col-md-12 js-product-only">
                            <div class="form-group">
                                <label for="target_city_ids">Target Cities</label>
                                <select id="target_city_ids" name="target_city_ids[]" multiple size="5"
                                        class="form-control @error('target_city_ids') is-invalid @enderror">
                                    @foreach ($cities as $city)
                                        <option value="{{ $city->id }}" @selected(in_array($city->id, old('target_city_ids', []), true))>{{ $city->name }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Where a product-promoting business wants to reach communities.</small>
                            </div>
                        </div>
                    </div>

                    {{-- ── Venue ──────────────────────────────────────────── --}}
                    <div class="js-venue-only">
                        <hr>
                        <h5 class="mb-3 text-muted"><i class="fas fa-store-alt mr-1"></i> Venue</h5>
                        <p class="text-muted small">
                            Address, hours and photos come from the Maps import above. Type and capacity
                            do not — Google does not publish either — so the wizard asks a human, and so
                            does this form.
                        </p>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="venue_name">Venue Name</label>
                                    <input id="venue_name" type="text" name="venue[name]" value="{{ old('venue.name') }}"
                                           class="form-control @error('primary_venue.name') is-invalid @enderror">
                                    <small class="form-text text-muted">Defaults to the imported place's name.</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="venue_type">Venue Type</label>
                                    <select id="venue_type" name="venue[venue_type]"
                                            class="form-control @error('primary_venue.venue_type') is-invalid @enderror">
                                        <option value="">—</option>
                                        @foreach ($venueTypes as $venueType)
                                            <option value="{{ $venueType }}" @selected(old('venue.venue_type') === $venueType)>{{ ucwords(str_replace('_', ' ', $venueType)) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="venue_capacity">Capacity</label>
                                    <input id="venue_capacity" type="number" min="1" name="venue[capacity]"
                                           value="{{ old('venue.capacity') }}"
                                           class="form-control @error('primary_venue.capacity') is-invalid @enderror">
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="venue_address">Address</label>
                                    <input id="venue_address" type="text" name="venue[formatted_address]"
                                           value="{{ old('venue.formatted_address') }}"
                                           class="form-control @error('primary_venue.formatted_address') is-invalid @enderror">
                                    <small class="form-text text-muted">Defaults to the imported address.</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="venue_city">Venue City</label>
                                    <input id="venue_city" type="text" name="venue[city]" value="{{ old('venue.city') }}"
                                           class="form-control @error('primary_venue.city') is-invalid @enderror">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="venue_description">Venue Description</label>
                                    <textarea id="venue_description" name="venue[description]" rows="2"
                                              class="form-control @error('primary_venue.description') is-invalid @enderror">{{ old('venue.description') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ── Offer ──────────────────────────────────────────── --}}
                    <hr>
                    <h5 class="mb-3 text-muted"><i class="fas fa-gift mr-1"></i> What they offer</h5>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="offering">Offering</label>
                                <textarea id="offering" name="offering" rows="3" maxlength="2000"
                                          class="form-control @error('offering') is-invalid @enderror">{{ old('offering') }}</textarea>
                                <small class="form-text text-muted">
                                    Becomes the first Kolab's description when About is empty.
                                </small>
                            </div>
                        </div>
                        <div class="col-md-4 js-product-only">
                            <div class="form-group">
                                <label for="product_type">Product Type</label>
                                <select id="product_type" name="product_type" class="form-control @error('product_type') is-invalid @enderror">
                                    <option value="">—</option>
                                    @foreach ($productTypes as $productType)
                                        <option value="{{ $productType }}" @selected(old('product_type') === $productType)>{{ ucwords(str_replace('_', ' ', $productType)) }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Defaults to "other".</small>
                            </div>
                        </div>
                    </div>
                @else
                    {{-- ── Identity ───────────────────────────────────────── --}}
                    <hr>
                    <h5 class="mb-3 text-muted"><i class="fas fa-id-card mr-1"></i> Identity</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name">Community Name</label>
                                <input id="name" type="text" name="name" value="{{ old('name') }}"
                                       class="form-control @error('name') is-invalid @enderror" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="community_type">Community Type</label>
                                <select id="community_type" name="community_type"
                                        class="form-control @error('community_type') is-invalid @enderror" required>
                                    <option value="">—</option>
                                    @foreach ($communityTypes as $type)
                                        <option value="{{ $type->slug }}" @selected(old('community_type') === $type->slug)>{{ $type->name }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">
                                    Required. This is what businesses filter on, so a community without
                                    one is listed but effectively unfindable.
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="community_size">Community Size</label>
                                <input id="community_size" type="number" min="1" name="community_size"
                                       value="{{ old('community_size') }}"
                                       class="form-control @error('community_size') is-invalid @enderror">
                                <small class="form-text text-muted">Roughly how many members they reach.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="city_id">City</label>
                                <select id="city_id" name="city_id"
                                        class="form-control @error('city_id') is-invalid @enderror" required>
                                    <option value="">—</option>
                                    @foreach ($cities as $city)
                                        <option value="{{ $city->id }}" @selected(old('city_id') === $city->id)>{{ $city->name }} ({{ $city->country }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="about">About</label>
                                <textarea id="about" name="about" rows="4" maxlength="1000"
                                          class="form-control @error('about') is-invalid @enderror">{{ old('about') }}</textarea>
                                <small class="form-text text-muted">Max 1000 characters.</small>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ── Contact ────────────────────────────────────────────── --}}
                <hr>
                <h5 class="mb-3 text-muted"><i class="fas fa-link mr-1"></i> Contact &amp; socials</h5>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="instagram">Instagram</label>
                            <input id="instagram" type="text" name="instagram" value="{{ old('instagram') }}"
                                   class="form-control @error('instagram') is-invalid @enderror" placeholder="@handle">
                        </div>
                    </div>
                    @unless ($isBusiness)
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="tiktok">TikTok</label>
                                <input id="tiktok" type="text" name="tiktok" value="{{ old('tiktok') }}"
                                       class="form-control @error('tiktok') is-invalid @enderror" placeholder="@handle">
                            </div>
                        </div>
                    @endunless
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="website">Website</label>
                            <input id="website" type="url" name="website" value="{{ old('website') }}"
                                   class="form-control @error('website') is-invalid @enderror">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer">
                <button type="submit" class="btn btn-primary">
                    {{ $isBusiness ? 'Onboard business' : 'Onboard community' }}
                </button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-default">Cancel</a>
                @if ($isBusiness)
                    <span class="text-muted ml-2 small">
                        Also publishes their first Kolab, exactly as the app does.
                    </span>
                @endif
            </div>
        </form>
    </div>

    @if ($isBusiness)
        <script>
        (function () {
            // Venue and product are alternatives, not a spectrum: showing both sets
            // at once is how a maintainer ends up filling in a capacity for a product
            // that has no venue, which then fails validation with no obvious cause.
            var yes = document.getElementById('has_venue_yes');
            var no = document.getElementById('has_venue_no');

            function apply() {
                var hasVenue = yes.checked;
                document.querySelectorAll('.js-venue-only').forEach(function (el) {
                    el.style.display = hasVenue ? '' : 'none';
                });
                document.querySelectorAll('.js-product-only').forEach(function (el) {
                    el.style.display = hasVenue ? 'none' : '';
                });
            }

            yes.addEventListener('change', apply);
            no.addEventListener('change', apply);
            apply();
        })();
        </script>
    @endif
@endsection
