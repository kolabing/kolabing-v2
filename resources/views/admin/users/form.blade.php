@php
    $currentUserType = $profile->user_type?->value;
    $userType = old('user_type', $currentUserType ?? 'business');
    $detailProfile = $profile->businessProfile ?? $profile->communityProfile ?? null;
@endphp

<div class="grid">
    @if (! $isEdit)
        <div class="field">
            <label for="user_type">User Type</label>
            <select id="user_type" name="user_type" required>
                @foreach ($userTypes as $type)
                    <option value="{{ $type->value }}" @selected($userType === $type->value)>{{ ucfirst($type->value) }}</option>
                @endforeach
            </select>
        </div>
    @else
        <div class="field">
            <label>User Type</label>
            <input value="{{ ucfirst($profile->user_type->value) }}" disabled>
        </div>
    @endif

    <div class="field">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email', $profile->email) }}" required>
    </div>

    <div class="field">
        <label for="password">{{ $isEdit ? 'Password (leave blank to keep current)' : 'Password' }}</label>
        <input id="password" type="password" name="password" {{ $isEdit ? '' : 'required' }}>
    </div>

    <div class="field">
        <label for="phone_number">Phone Number</label>
        <input id="phone_number" type="text" name="phone_number" value="{{ old('phone_number', $profile->phone_number) }}">
    </div>
@if (($profile->isCommunity() ?? false) || ! $isEdit || $userType === 'community')
    <div class="field">
        <label for="tiktok">TikTok</label>
        <input id="tiktok" type="text" name="tiktok" value="{{ old('tiktok', $profile->communityProfile?->tiktok) }}">
    </div>
@endif
</div>

<div class="field">
    <label for="name">Display Name</label>
    <input id="name" type="text" name="name" value="{{ old('name', $detailProfile?->name) }}">
</div>

<div class="field">
    <label for="about">About</label>
    <textarea id="about" name="about">{{ old('about', $detailProfile?->about) }}</textarea>
</div>

<div class="grid">
    <div class="field">
        <label for="instagram">Instagram</label>
        <input id="instagram" type="text" name="instagram" value="{{ old('instagram', $detailProfile?->instagram) }}">
    </div>

    <div class="field">
        <label for="website">Website</label>
        <input id="website" type="url" name="website" value="{{ old('website', $detailProfile?->website) }}">
    </div>
</div>

<label class="checkbox" for="email_verified">
    <input id="email_verified" type="checkbox" name="email_verified" value="1" @checked(old('email_verified', (bool) $profile->email_verified_at))>
    <span>Email verified</span>
</label>
