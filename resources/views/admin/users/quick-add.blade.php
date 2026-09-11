@extends('admin.layout', ['title' => 'Quick Add'])

@section('page_title', 'Quick Add')
@section('page_subtitle', 'List a business or community sourced from outreach. They get a welcome email with a create-password link — no form for them to fill in.')

@section('page_actions')
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Users
    </a>
@endsection

@section('admin_content')
    <div class="card card-primary card-outline">
        <form method="post" action="{{ route('admin.users.quick-add.store') }}">
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
                            <small class="form-text text-muted">The welcome email with a create-password link goes here.</small>
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
                </div>
            </div>

            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Create listing &amp; send welcome email</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@endsection
