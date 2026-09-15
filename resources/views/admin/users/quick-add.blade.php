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
    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Couldn't save — please fix:</strong>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card card-primary card-outline">
        <form method="post" action="{{ route('admin.users.quick-add.store') }}" id="quick-add-form">
            @csrf

            @include('admin.users._places-import')

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

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="business_type">Category</label>
                            <select id="business_type" name="business_type" class="form-control @error('business_type') is-invalid @enderror">
                                <option value="">—</option>
                                @foreach ($businessTypes ?? [] as $type)
                                    <option value="{{ $type->slug }}" @selected(old('business_type') === $type->slug)>{{ $type->name }}</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">Used only for business profiles. Also set by the Maps import above, when it can resolve one.</small>
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
        </form>
    </div>
@endsection
