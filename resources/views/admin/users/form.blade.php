@php
    $currentUserType = $profile->user_type?->value;
    $userType = old('user_type', $currentUserType ?? 'business');
    $detailProfile = $profile->businessProfile ?? $profile->communityProfile ?? null;
@endphp

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="user_type">User Type</label>
            @if (! $isEdit)
                <select id="user_type" name="user_type" class="form-control @error('user_type') is-invalid @enderror" required>
                    @foreach ($userTypes as $type)
                        <option value="{{ $type->value }}" @selected($userType === $type->value)>{{ ucfirst($type->value) }}</option>
                    @endforeach
                </select>
            @else
                <input value="{{ ucfirst($profile->user_type->value) }}" class="form-control" disabled>
            @endif
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $profile->email) }}" class="form-control @error('email') is-invalid @enderror" required>
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-group">
            <label for="password">{{ $isEdit ? 'Password (leave blank to keep current)' : 'Password' }}</label>
            <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" {{ $isEdit ? '' : 'required' }}>
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-group">
            <label for="phone_number">Phone Number</label>
            <input id="phone_number" type="text" name="phone_number" value="{{ old('phone_number', $profile->phone_number) }}" class="form-control @error('phone_number') is-invalid @enderror">
        </div>
    </div>

    <div class="col-12">
        <div class="form-group">
            <label for="name">Display Name</label>
            <input id="name" type="text" name="name" value="{{ old('name', $detailProfile?->name) }}" class="form-control @error('name') is-invalid @enderror">
        </div>
    </div>

    <div class="col-12">
        <div class="form-group">
            <label for="about">About</label>
            <textarea id="about" name="about" rows="4" class="form-control @error('about') is-invalid @enderror">{{ old('about', $detailProfile?->about) }}</textarea>
        </div>
    </div>

    <div class="col-md-4">
        <div class="form-group">
            <label for="instagram">Instagram</label>
            <input id="instagram" type="text" name="instagram" value="{{ old('instagram', $detailProfile?->instagram) }}" class="form-control @error('instagram') is-invalid @enderror">
        </div>
    </div>

    <div class="col-md-4">
        <div class="form-group">
            <label for="website">Website</label>
            <input id="website" type="url" name="website" value="{{ old('website', $detailProfile?->website) }}" class="form-control @error('website') is-invalid @enderror">
        </div>
    </div>

    <div class="col-md-4">
        <div class="form-group">
            <label for="tiktok">TikTok</label>
            <input id="tiktok" type="text" name="tiktok" value="{{ old('tiktok', $profile->communityProfile?->tiktok) }}" class="form-control @error('tiktok') is-invalid @enderror">
            <small class="form-text text-muted">Used only for community profiles.</small>
        </div>
    </div>

    <div class="col-12">
        <div class="custom-control custom-switch mt-2">
            <input id="email_verified" type="checkbox" name="email_verified" value="1" class="custom-control-input" @checked(old('email_verified', (bool) $profile->email_verified_at))>
            <label class="custom-control-label" for="email_verified">Email verified</label>
        </div>
    </div>
</div>
