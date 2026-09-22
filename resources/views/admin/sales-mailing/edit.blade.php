@extends('admin.layout', ['title' => 'Pitch'])

@section('page_title', 'Pitch')
@section('page_subtitle', 'Pick the idea, draw a cover, fix the copy. Nothing leaves here until you preview and confirm.')

@section('page_actions')
    <a href="{{ route('admin.sales-mailing.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Back
    </a>
@endsection

@section('admin_content')
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($draft->isSent())
        <div class="alert alert-info">
            <i class="fas fa-paper-plane mr-1"></i>
            Sent {{ $draft->sent_at?->diffForHumans() }} — this pitch is now read-only.
        </div>
    @endif

    <div class="row">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">The pair</h3></div>
                <div class="card-body">
                    <p class="mb-1"><strong>To:</strong> {{ $draft->business?->businessProfile?->name ?? '—' }}
                        <br><small class="text-muted">{{ $draft->business?->email }}</small></p>
                    <p class="mb-1"><strong>Pitching:</strong> {{ $draft->community?->communityProfile?->name ?? '—' }}</p>
                    <p class="mb-0"><strong>Language:</strong> {{ strtoupper($draft->locale) }}</p>
                </div>
            </div>

            {{-- What the research found, and which opening the model picked from it.
                 Shown because a maintainer about to send a claim about someone's
                 business should be able to see what that claim rests on. --}}
            @if ($draft->angle || filled($draft->intel))
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Research</h3></div>
                    <div class="card-body">
                        @if ($draft->angle)
                            <p class="mb-2">
                                <strong>Angle:</strong>
                                <span class="badge badge-info">{{ str_replace('_', ' ', $draft->angle) }}</span>
                            </p>
                        @endif

                        @php($places = $draft->intel['places'] ?? [])
                        @if (filled($places))
                            <ul class="list-unstyled mb-2 small">
                                @isset($places['google_rating'])
                                    <li><i class="fas fa-star text-warning mr-1"></i>
                                        {{ $places['google_rating'] }} from {{ $places['google_review_count'] ?? '?' }} Google reviews</li>
                                @endisset
                                @isset($places['opening_hours'])
                                    <li><i class="fas fa-clock text-muted mr-1"></i> {{ count($places['opening_hours']) }} days of opening hours on file</li>
                                @endisset
                                @isset($places['price_level'])
                                    <li><i class="fas fa-tag text-muted mr-1"></i> Price level: {{ $places['price_level'] }}</li>
                                @endisset
                            </ul>
                        @endif

                        @isset($draft->intel['website']['url'])
                            <p class="small mb-1">
                                <i class="fas fa-globe text-muted mr-1"></i>
                                Read their site: <a href="{{ $draft->intel['website']['url'] }}" target="_blank" rel="noopener noreferrer">{{ $draft->intel['website']['url'] }}</a>
                            </p>
                        @endisset

                        @isset($draft->intel['weather']['next_7_days'])
                            <p class="small mb-0">
                                <i class="fas fa-cloud-rain text-muted mr-1"></i>
                                7-day forecast at the venue was available.
                            </p>
                        @endisset

                        @if (blank($places) && blank($draft->intel['website'] ?? null))
                            <p class="text-muted small mb-0">
                                Nothing was found for this business — the pitch is written from the profile alone.
                            </p>
                        @endif
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header"><h3 class="card-title">The estimate</h3></div>
                <div class="card-body">
                    {{-- Shown as its own arithmetic here for the same reason it is in the
                         email: a maintainer about to send a revenue claim should see
                         where it came from, not just the total. --}}
                    <p class="mb-1">{{ $draft->expected_attendees }} attendees
                        × {{ $currency }} {{ number_format($draft->avg_spend_cents / 100, 2) }} per head</p>
                    <h4 class="mb-0">{{ $currency }} {{ number_format($draft->estimated_revenue_cents / 100, 0) }}</h4>
                    <small class="text-muted">Sent as an estimate, with the sum shown.</small>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Cover image</h3></div>
                <div class="card-body text-center">
                    @if ($draft->cover_image_url)
                        <img src="{{ $draft->cover_image_url }}" alt="" class="img-fluid rounded mb-2">
                    @elseif (! $draft->isCoverPending())
                        <p class="text-muted mb-2">No cover yet.</p>
                    @endif

                    {{-- Generation is queued (BE-FX-61), so this page has to report a
                         state the maintainer is no longer watching happen. The
                         auto-refresh is what turns "pending" into a result without
                         asking them to guess when to reload. --}}
                    @if ($draft->isCoverPending())
                        <div class="alert alert-info mb-2">
                            <i class="fas fa-spinner fa-spin mr-1"></i>
                            Drawing the cover — this takes up to a minute.
                            <br><small>This page refreshes itself.</small>
                        </div>
                    @endif

                    @if ($draft->coverFailed())
                        <div class="alert alert-danger text-left mb-2">
                            <strong>The cover could not be generated.</strong>
                            <br><small>{{ $draft->cover_image_error }}</small>
                        </div>
                    @endif

                    @unless ($draft->isSent() || $draft->isCoverPending())
                        <form method="post" action="{{ route('admin.sales-mailing.image', $draft) }}">
                            @csrf
                            <button class="btn btn-sm btn-info">
                                <i class="fas fa-image mr-1"></i>
                                {{ $draft->cover_image_url ? 'Regenerate' : 'Generate' }} cover
                            </button>
                        </form>
                        <small class="form-text text-muted">Costs an image credit each time.</small>
                    @endunless
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Kolab ideas</h3></div>
                <div class="card-body">
                    @foreach ($draft->kolab_ideas as $i => $idea)
                        <div class="border rounded p-3 mb-2 {{ $i === $draft->selected_idea_index ? 'border-primary' : '' }}">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong>{{ $idea['title'] ?? '' }}</strong>
                                    @if ($i === $draft->selected_idea_index)
                                        <span class="badge badge-primary ml-1">selected</span>
                                    @endif
                                    <div class="text-muted small mt-1">{{ $idea['format'] ?? '' }}</div>
                                </div>
                                @unless ($draft->isSent() || $i === $draft->selected_idea_index)
                                    <form method="post" action="{{ route('admin.sales-mailing.idea', $draft) }}">
                                        @csrf
                                        <input type="hidden" name="index" value="{{ $i }}">
                                        <button class="btn btn-sm btn-outline-primary">Use this</button>
                                    </form>
                                @endunless
                            </div>
                            <dl class="row mb-0 mt-2 small">
                                <dt class="col-sm-4">Business provides</dt><dd class="col-sm-8">{{ $idea['business_provides'] ?? '' }}</dd>
                                <dt class="col-sm-4">Community delivers</dt><dd class="col-sm-8">{{ $idea['community_delivers'] ?? '' }}</dd>
                                <dt class="col-sm-4">Why it works</dt><dd class="col-sm-8">{{ $idea['why_it_works'] ?? '' }}</dd>
                            </dl>
                        </div>
                    @endforeach
                    <p class="text-muted small mb-0">
                        Switching the idea rewrites the email around it and clears the cover —
                        a wine-cellar photo does not belong on a run-club pitch.
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">The email</h3></div>
                <form method="post" action="{{ route('admin.sales-mailing.copy', $draft) }}">
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        <div class="form-group">
                            <label for="subject">Subject</label>
                            <input id="subject" name="subject" class="form-control"
                                   value="{{ old('subject', $draft->subject) }}" @disabled($draft->isSent())>
                        </div>
                        <div class="form-group mb-0">
                            <label for="body_markdown">Body (markdown)</label>
                            <textarea id="body_markdown" name="body_markdown" rows="12" class="form-control"
                                      @disabled($draft->isSent())>{{ old('body_markdown', $draft->body_markdown) }}</textarea>
                            <small class="form-text text-muted">
                                A model wrote this. Read every line — the greeting, the estimate box,
                                the button and the sign-off are added by the template, not by it.
                            </small>
                        </div>
                    </div>
                    @unless ($draft->isSent())
                        <div class="card-footer">
                            <button class="btn btn-secondary">Save copy</button>
                            <a href="{{ route('admin.sales-mailing.preview', $draft) }}" class="btn btn-primary">
                                <i class="fas fa-eye mr-1"></i> Preview &amp; send
                            </a>
                        </div>
                    @endunless
                </form>
            </div>

            {{-- WhatsApp: a link the maintainer clicks, not an API we send through.
                 The WhatsApp Business Platform requires pre-approved templates for
                 business-initiated conversations and bans messaging people who never
                 opted in, so sending from the server would get the number blocked.
                 This opens their own WhatsApp with the text already typed. --}}
            @if ($draft->whatsapp_message)
                <div class="card">
                    <div class="card-header"><h3 class="card-title">WhatsApp</h3></div>
                    <div class="card-body">
                        <pre class="bg-light p-3 rounded" style="white-space:pre-wrap">{{ $draft->whatsapp_message }}</pre>
                        @if ($link = $draft->whatsappLink())
                            <a href="{{ $link }}" target="_blank" rel="noopener noreferrer" class="btn btn-success">
                                <i class="fab fa-whatsapp mr-1"></i> Open in WhatsApp
                            </a>
                            <small class="form-text text-muted">
                                Opens your own WhatsApp with this text filled in. You press send.
                            </small>
                        @else
                            <p class="text-muted small mb-0">
                                No phone number on file for this business, so there is no link to open —
                                copy the text above instead.
                            </p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($draft->isCoverPending())
        {{-- Poll by reloading rather than adding an endpoint and a fetch loop: the
             page is already cheap, a maintainer has one of these open at a time, and
             the job finishes in well under a minute. Stops as soon as the status
             leaves `pending`, because this block stops rendering. --}}
        <script>
            setTimeout(function () { window.location.reload(); }, 6000);
        </script>
    @endif
@endsection
