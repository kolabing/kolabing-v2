@extends('admin.layout', ['title' => 'Invite communities'])

@section('page_title', 'Invite communities')
@section('page_subtitle', 'Send communities an in-app + push invite to apply to an open business offer. Tapping it opens the Kolab with its Apply button.')

@section('admin_content')
    @php($current = $preview ?? null)
    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">Who to invite</h3></div>
        <form method="post" action="{{ route('admin.kolab-invites.preview') }}">
            @csrf
            <div class="card-body">
                <div class="form-group">
                    <label for="kolab">Kolab <small class="text-muted">(open business offers; auto listings marked)</small></label>
                    <select id="kolab" name="kolab" class="form-control" required>
                        <option value="">—</option>
                        @foreach ($kolabs as $k)
                            <option value="{{ $k->id }}" @selected(old('kolab', $selectedKolabId) === $k->id)>
                                {{ $k->title }} — {{ $k->creatorProfile?->businessProfile?->name ?? '—' }} — {{ $k->preferred_city }}{{ $k->is_auto_listing ? ' (auto listing)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="city">City <small class="text-muted">(blank = the Kolab's city)</small></label>
                            <input type="text" id="city" name="city" class="form-control" maxlength="120" value="{{ old('city', request('city')) }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="category">Community type</label>
                            <select id="category" name="category" class="form-control">
                                <option value="">All types</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->slug }}" @selected(old('category', request('category')) === $category->slug)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="limit">Max this send</label>
                            <input type="number" id="limit" name="limit" class="form-control" min="1" max="{{ \App\Services\KolabInviteService::MAX_LIMIT }}" value="{{ old('limit', request('limit', $defaults['limit'])) }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="weekly_cap">Max per community / week</label>
                            <input type="number" id="weekly_cap" name="weekly_cap" class="form-control" min="1" max="{{ \App\Services\KolabInviteService::MAX_WEEKLY_CAP }}" value="{{ old('weekly_cap', request('weekly_cap', $defaults['weekly_cap'])) }}">
                        </div>
                    </div>
                </div>
                <small class="text-muted">A community is never invited twice to the same Kolab, and skipped if it already applied, is switched off, is a test account, or blocked / was blocked by the business. Push follows each community's own notification settings.</small>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-outline-primary">Preview</button>
                <small class="text-muted ml-2">Nothing is sent until you confirm the preview.</small>
            </div>
        </form>
    </div>

    @if ($current !== null)
        <div class="card {{ $current['problems'] === [] && $current['recipients']->isNotEmpty() ? 'card-success' : 'card-danger' }} card-outline">
            <div class="card-header">
                <h3 class="card-title">Preview — "{{ $previewKolab->title }}"</h3>
            </div>
            <div class="card-body">
                @foreach ($current['problems'] as $problem)
                    <div class="alert alert-danger">Cannot invite: {{ $problem }}</div>
                @endforeach

                @if ($current['problems'] === [])
                    <p class="mb-2">
                        <strong>{{ $current['recipients']->count() }}</strong> communit{{ $current['recipients']->count() === 1 ? 'y' : 'ies' }} will be invited
                        ({{ $current['total_eligible'] }} eligible in {{ $current['city'] }}{{ $current['category'] ? ', type '.$current['category'] : '' }}; largest first).
                    </p>
                    @if ($current['recipients']->isEmpty())
                        <div class="alert alert-warning mb-0">No community matches. Try another city or type, or raise the weekly cap.</div>
                    @else
                        <ul class="mb-0">
                            @foreach ($current['recipients'] as $community)
                                <li>{{ $community->communityProfile?->name ?? $community->email }}{{ $community->communityProfile?->community_size ? ' — '.$community->communityProfile->community_size.' members' : '' }}</li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </div>
            @if ($current['problems'] === [] && $current['recipients']->isNotEmpty())
                <div class="card-footer">
                    <form method="post" action="{{ route('admin.kolab-invites.store') }}" onsubmit="return confirm('Send {{ $current['recipients']->count() }} invite(s)? This cannot be undone.');">
                        @csrf
                        <input type="hidden" name="kolab" value="{{ $previewKolab->id }}">
                        <input type="hidden" name="city" value="{{ $current['city'] }}">
                        <input type="hidden" name="category" value="{{ $current['category'] }}">
                        <input type="hidden" name="limit" value="{{ $current['limit'] }}">
                        <input type="hidden" name="weekly_cap" value="{{ $current['weekly_cap'] }}">
                        <button type="submit" class="btn btn-success">Send {{ $current['recipients']->count() }} invite{{ $current['recipients']->count() === 1 ? '' : 's' }}</button>
                    </form>
                </div>
            @endif
        </div>
    @endif

    <div class="card">
        <div class="card-header"><h3 class="card-title">Recent invites</h3></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Kolab</th>
                            <th>Business</th>
                            <th class="text-center">Communities invited</th>
                            <th>Last sent</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recent as $row)
                            @php($k = $recentKolabs[$row->kolab_id] ?? null)
                            <tr>
                                <td>
                                    @if ($k)
                                        <a href="{{ route('admin.kolabs.edit', $k) }}">{{ $k->title }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $k?->creatorProfile?->businessProfile?->name ?? '—' }}</td>
                                <td class="text-center">{{ $row->invites }}</td>
                                <td>{{ $row->last_sent_at ? \Illuminate\Support\Carbon::parse($row->last_sent_at)->toDayDateTimeString() : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No invites sent yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
