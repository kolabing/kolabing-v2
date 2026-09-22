@extends('admin.layout', ['title' => 'Sales Mailing'])

@section('page_title', 'Sales Mailing')
@section('page_subtitle', 'Pitch a business on hosting one specific community, with a Kolab idea, a cover image and what the night is plausibly worth to them.')

@section('admin_content')
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @unless ($isConfigured)
        {{-- Say why the button will not work, rather than offering one that always
             fails and reports it as a generic error. --}}
        <div class="alert alert-warning">
            <i class="fas fa-triangle-exclamation mr-1"></i>
            <strong>No OpenAI key configured.</strong> Set <code>OPENAI_API_KEY</code> in the
            environment — here and in Laravel Cloud — before generating a pitch.
        </div>
    @endunless

    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">New pitch</h3></div>
        <form method="post" action="{{ route('admin.sales-mailing.generate') }}">
            @csrf
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="business_profile_id">Business <small class="text-muted">(receives the email)</small></label>
                            <select id="business_profile_id" name="business_profile_id" class="form-control" required>
                                <option value="">—</option>
                                @foreach ($businesses as $b)
                                    @php($city = $b->businessProfile?->city_name)
                                    <option value="{{ $b->id }}" @selected(old('business_profile_id') === $b->id)>
                                        {{ $b->businessProfile?->name ?? $b->email }}{{ $city ? ' — '.$city : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="community_profile_id">Community <small class="text-muted">(who we pitch them)</small></label>
                            <select id="community_profile_id" name="community_profile_id" class="form-control" required>
                                <option value="">—</option>
                                @foreach ($communities as $c)
                                    @php($size = $c->communityProfile?->community_size)
                                    <option value="{{ $c->id }}" @selected(old('community_profile_id') === $c->id)>
                                        {{ $c->communityProfile?->name ?? $c->email }}{{ $size ? ' — '.$size.' members' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="locale">Language</label>
                            <select id="locale" name="locale" class="form-control" required>
                                @foreach ($locales as $loc)
                                    <option value="{{ $loc }}" @selected(old('locale', 'en') === $loc)>{{ strtoupper($loc) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="expected_attendees">Expected attendees</label>
                            <input id="expected_attendees" type="number" min="1" name="expected_attendees"
                                   value="{{ old('expected_attendees') }}" class="form-control" placeholder="auto">
                            <small class="form-text text-muted">Empty = estimated from the community's size.</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="avg_spend_cents">Average spend per head <small class="text-muted">({{ $currency }} cents)</small></label>
                            <input id="avg_spend_cents" type="number" min="1" name="avg_spend_cents"
                                   value="{{ old('avg_spend_cents') }}" class="form-control"
                                   placeholder="{{ $defaultAvgSpendCents }}">
                            <small class="form-text text-muted">
                                A café and a cocktail bar are nowhere near each other — set it per pitch.
                            </small>
                        </div>
                    </div>
                </div>
                <p class="text-muted small mb-0">
                    The revenue figure is arithmetic done here, not by the model: attendees × average spend.
                    The email shows the sum so the business can check it, and calls it an estimate.
                </p>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary" @disabled(! $isConfigured)>
                    <i class="fas fa-wand-magic-sparkles mr-1"></i> Generate pitch
                </button>
                <span class="text-muted small ml-2">Generates ideas and copy. Sends nothing.</span>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Pitches</h3></div>
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Business</th><th>Community</th><th>Idea</th>
                        <th>Estimate</th><th>Lang</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($drafts as $draft)
                    <tr>
                        <td>{{ $draft->business?->businessProfile?->name ?? $draft->business?->email }}</td>
                        <td>{{ $draft->community?->communityProfile?->name ?? '—' }}</td>
                        <td>{{ $draft->selectedIdea()['title'] ?? '—' }}</td>
                        <td>{{ $currency }} {{ number_format($draft->estimated_revenue_cents / 100, 0) }}</td>
                        <td>{{ strtoupper($draft->locale) }}</td>
                        <td>
                            @if ($draft->isWriting())
                                <span class="badge badge-info"><i class="fas fa-spinner fa-spin mr-1"></i>writing</span>
                            @elseif ($draft->writingFailed())
                                <span class="badge badge-danger">failed</span>
                            @elseif ($draft->isSent())
                                <span class="badge badge-success">sent {{ $draft->sent_at?->diffForHumans() }}</span>
                            @else
                                <span class="badge badge-secondary">draft</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <a href="{{ route('admin.sales-mailing.edit', $draft) }}" class="btn btn-sm btn-outline-primary">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No pitches yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $drafts->links() }}</div>
    </div>
@endsection
