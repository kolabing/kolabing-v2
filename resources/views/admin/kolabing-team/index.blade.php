@extends('admin.layout', ['title' => 'Kolabing team offers'])

@section('page_title', 'Kolabing team offers')
@section('page_subtitle', 'Send one profile an offer from the "Kolabing team" chat. Draft it, read it, then press Send on its row: one row is one send.')

@section('admin_content')
    @if ($errors->has('send'))
        <div class="alert alert-danger">{{ $errors->first('send') }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">New draft</h3></div>
        <form method="post" action="{{ route('admin.kolabing-team.store') }}">
            @csrf
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="kind">Offer</label>
                            <select id="kind" name="kind" class="form-control" required>
                                <option value="community_apply" @selected(old('kind') === 'community_apply')>To a community: apply to a business Kolab</option>
                                <option value="business_propose" @selected(old('kind') === 'business_propose')>To a business: propose a Kolab to a community</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="recipient">Recipient <small class="text-muted">(profile id or email)</small></label>
                            <input type="text" id="recipient" name="recipient" class="form-control" required value="{{ old('recipient') }}">
                            @error('recipient')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="proposed_date">Date <small class="text-muted">(optional, YYYY-MM-DD)</small></label>
                            <input type="text" id="proposed_date" name="proposed_date" class="form-control" value="{{ old('proposed_date') }}">
                            @error('proposed_date')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="kolab">Kolab <small class="text-muted">(community offers)</small></label>
                            <select id="kolab" name="kolab" class="form-control">
                                <option value="">—</option>
                                @foreach ($kolabs as $k)
                                    <option value="{{ $k->id }}" @selected(old('kolab') === $k->id)>
                                        {{ $k->title }} — {{ $k->creatorProfile?->businessProfile?->name ?? '—' }} — {{ $k->preferred_city }}
                                    </option>
                                @endforeach
                            </select>
                            @error('kolab')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="community">Community <small class="text-muted">(business offers: profile id or email)</small></label>
                            <input type="text" id="community" name="community" class="form-control" value="{{ old('community') }}">
                            @error('community')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="title">Card title <small class="text-muted">(blank = the Kolab's title)</small></label>
                    <input type="text" id="title" name="title" class="form-control" maxlength="255" value="{{ old('title') }}">
                    <small class="text-muted">A business without a subscription is not shown the community's name on the card. Keep it out of the title too.</small>
                </div>
                <div class="form-group">
                    <label for="message">Message in the chat</label>
                    <textarea id="message" name="message" rows="3" class="form-control" maxlength="2000">{{ old('message') }}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="what_business_gets">What the business gets</label>
                            <input type="text" id="what_business_gets" name="what_business_gets" class="form-control" maxlength="500" value="{{ old('what_business_gets') }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="what_community_gets">What the community gets</label>
                            <input type="text" id="what_community_gets" name="what_community_gets" class="form-control" maxlength="500" value="{{ old('what_community_gets') }}">
                        </div>
                    </div>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="test_send" name="test_send" value="1" @checked(old('test_send'))>
                    <label class="form-check-label" for="test_send">Test send (the recipient must be a test account; nothing is emailed)</label>
                </div>
                <small class="text-muted d-block mt-2">Skipped for real sends: switched-off, deleted and test accounts; either side of a block; a recipient who already got {{ \App\Services\KolabingTeamService::DEFAULT_WEEKLY_CAP }} offers this week; the same offer twice. The push follows the recipient's own notification switch; the in-app message is always written.</small>
            </div>
            <div class="card-footer"><button type="submit" class="btn btn-outline-primary">Save draft</button></div>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Offers <small class="text-muted">({{ $unanswered }} thread{{ $unanswered === 1 ? '' : 's' }} waiting for an answer: <a href="{{ route('admin.kolabing-team.inbox') }}">inbox</a>)</small></h3></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Recipient</th><th>Offer</th><th>Status</th><th>Channel</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($offers as $o)
                            <tr>
                                <td>{{ $o->recipient?->businessProfile?->name ?? $o->recipient?->communityProfile?->name ?? $o->recipient?->email }}<br><small class="text-muted">{{ $o->recipient?->email }}{{ $o->is_test_send ? ' · test send' : '' }}</small></td>
                                <td>{{ $o->title }}<br><small class="text-muted">{{ $o->kind->value }}{{ $o->communityProfile ? ' → '.($o->communityProfile->communityProfile?->name ?? $o->communityProfile->email) : '' }}</small></td>
                                <td>{{ $o->status->value }}@if($o->sent_at)<br><small class="text-muted">{{ $o->sent_at->toDayDateTimeString() }}</small>@endif</td>
                                <td>{{ $o->channel }}@if (($whatsapp[$o->id] ?? null))<br><a href="{{ $whatsapp[$o->id] }}" target="_blank" rel="noopener">WhatsApp</a>@endif</td>
                                <td class="text-right">
                                    @if ($o->status === \App\Enums\KolabingTeamOfferStatus::Draft)
                                        @foreach ($problems[$o->id] as $problem)<div class="text-danger small">{{ $problem }}</div>@endforeach
                                        @if ($problems[$o->id] === [])
                                            <form method="post" action="{{ route('admin.kolabing-team.send', $o) }}" class="d-inline" onsubmit="return confirm('Send this offer to {{ $o->recipient?->email }}? This cannot be undone.');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success">Send</button>
                                            </form>
                                        @endif
                                        <form method="post" action="{{ route('admin.kolabing-team.cancel', $o) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Cancel</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No offers yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
