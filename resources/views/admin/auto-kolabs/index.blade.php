@extends('admin.layout', ['title' => 'Auto-kolabs'])

@section('page_title', 'Auto-kolabs')
@section('page_subtitle', 'Set up a Kolab that is already matched: both sides see it as a confirmed collaboration, as if the community applied and the business accepted.')

@section('admin_content')
    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">New auto-kolab</h3></div>
        <form method="post" action="{{ route('admin.auto-kolabs.preview') }}">
            @csrf
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="business">Business <small class="text-muted">(the Kolab is their offer)</small></label>
                            <select id="business" name="business" class="form-control" required>
                                <option value="">—</option>
                                @foreach ($businesses as $b)
                                    @php($city = $b->businessProfile?->city_name)
                                    <option value="{{ $b->id }}" @selected(old('business', $planBusiness?->id) === $b->id)>
                                        {{ $b->businessProfile?->name ?? $b->email }}{{ $city ? ' — '.$city : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="community">Community <small class="text-muted">(matched to it)</small></label>
                            <select id="community" name="community" class="form-control" required>
                                <option value="">—</option>
                                @foreach ($communities as $c)
                                    @php($size = $c->communityProfile?->community_size)
                                    <option value="{{ $c->id }}" @selected(old('community', $planCommunity?->id) === $c->id)>
                                        {{ $c->communityProfile?->name ?? $c->email }}{{ $size ? ' — '.$size.' members' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="date">Date <small class="text-muted">(optional)</small></label>
                            <input type="date" id="date" name="date" class="form-control" value="{{ old('date', $plan['date'] ?? '') }}" min="{{ now()->toDateString() }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="title">Title <small class="text-muted">(optional — default: the business's listing title)</small></label>
                            <input type="text" id="title" name="title" class="form-control" maxlength="255" value="{{ old('title', request('title')) }}">
                        </div>
                    </div>
                    <div class="col-md-3 d-flex align-items-center">
                        <div class="form-check mt-3">
                            <input type="hidden" name="notify" value="0">
                            <input type="checkbox" id="notify" name="notify" value="1" class="form-check-input" @checked(old('notify', request()->has('notify') ? request('notify') : '1') === '1')>
                            <label for="notify" class="form-check-label">Notify both sides</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-outline-primary">Preview</button>
                <small class="text-muted ml-2">Nothing is created until you confirm the preview.</small>
            </div>
        </form>
    </div>

    @if ($plan !== null)
        <div class="card {{ $plan['problems'] === [] ? 'card-success' : 'card-danger' }} card-outline">
            <div class="card-header"><h3 class="card-title">Preview</h3></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Business</dt><dd class="col-sm-9">{{ $plan['business_name'] ?? '—' }}</dd>
                    <dt class="col-sm-3">Community</dt><dd class="col-sm-9">{{ $plan['community_name'] ?? '—' }}</dd>
                    <dt class="col-sm-3">Title</dt><dd class="col-sm-9">{{ $plan['title'] ?? '—' }}</dd>
                    <dt class="col-sm-3">City · type</dt><dd class="col-sm-9">{{ $plan['city'] ?? '—' }} · {{ str_replace('_', ' ', (string) ($plan['intent_type'] ?? '—')) }}</dd>
                    <dt class="col-sm-3">Date</dt><dd class="col-sm-9">{{ $plan['date'] ?? 'Not set — they agree it in chat' }}</dd>
                    <dt class="col-sm-3">Notifications</dt><dd class="col-sm-9">{{ request('notify') === '1' ? 'In-app + push + email to both owners, each per their own settings' : 'None' }}</dd>
                </dl>

                @foreach ($plan['warnings'] as $warning)
                    <div class="alert alert-warning mt-3 mb-0">{{ $warning }}</div>
                @endforeach
                @foreach ($plan['problems'] as $problem)
                    <div class="alert alert-danger mt-3 mb-0">Cannot create: {{ $problem }}</div>
                @endforeach
            </div>
            @if ($plan['problems'] === [])
                <div class="card-footer">
                    <form method="post" action="{{ route('admin.auto-kolabs.store') }}" onsubmit="return confirm('Create this Kolab? Both sides will see it as confirmed.');">
                        @csrf
                        <input type="hidden" name="business" value="{{ $planBusiness->id }}">
                        <input type="hidden" name="community" value="{{ $planCommunity->id }}">
                        <input type="hidden" name="date" value="{{ $plan['date'] }}">
                        <input type="hidden" name="title" value="{{ $plan['title'] }}">
                        <input type="hidden" name="notify" value="{{ request('notify') === '1' ? '1' : '0' }}">
                        <button type="submit" class="btn btn-success">Create auto-kolab</button>
                    </form>
                </div>
            @endif
        </div>
    @endif

    <div class="card">
        <div class="card-header"><h3 class="card-title">Recent auto-kolabs</h3></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Business</th>
                            <th>Community</th>
                            <th>Date</th>
                            <th>Collaboration</th>
                            <th>Set up by</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recent as $kolab)
                            @php($collaboration = $kolab->collaborations->first())
                            <tr>
                                <td><a href="{{ route('admin.kolabs.edit', $kolab) }}">{{ $kolab->title }}</a></td>
                                <td>{{ $kolab->creatorProfile?->businessProfile?->name ?? '—' }}</td>
                                <td>{{ $kolab->recipientCommunity?->communityProfile?->name ?? '—' }}</td>
                                <td>{{ $collaboration?->scheduled_date?->toDateString() ?? '—' }}</td>
                                <td><span class="badge badge-light text-uppercase">{{ $collaboration?->status->value ?? '—' }}</span></td>
                                <td>{{ $kolab->createdByAdmin?->name ?? $kolab->createdByAdmin?->email ?? 'command' }}</td>
                                <td>{{ $kolab->created_at?->toDateString() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No auto-kolabs yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
