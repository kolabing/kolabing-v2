@extends('admin.layout', ['title' => $account->name])

@section('page_title', $account->name)
@section('page_subtitle', ucfirst($account->type).' · '.($account->metrics['city'] ?? '—'))

@section('page_actions')
    <a href="{{ route('admin.crm.index', ['type' => $account->type]) }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Back to CRM
    </a>
    <a href="{{ route('admin.crm.edit', $account) }}" class="btn btn-outline-primary">
        <i class="fas fa-pen mr-1"></i> Edit
    </a>
@endsection

@section('admin_content')
    @php
        $stage = $account->currentStage();
        $next = $account->nextStage();
        $forward = \App\Models\CrmAccount::COMMUNITY_FORWARD_STAGES;
        $curIdx = array_search($stage, $forward, true);
        $m = $account->metrics ?? [];
    @endphp

    <div class="row">
        {{-- Pipeline + activity --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0">Pipeline</h3>
                    <span class="ml-auto">@include('admin.crm.partials.stage', ['stage' => $stage])</span>
                </div>
                <div class="card-body">
                    {{-- Forward-funnel stepper --}}
                    <div class="d-flex text-center mb-3" style="gap:.25rem">
                        @foreach ($forward as $i => $sg)
                            @php $state = $stage === 'Rejected' ? 'muted' : ($i < $curIdx ? 'done' : ($i === $curIdx ? 'cur' : 'muted')); @endphp
                            <div style="flex:1">
                                <div style="height:6px;border-radius:3px;background:{{ $state === 'done' ? '#1f9d57' : ($state === 'cur' ? '#f2c200' : '#e4e7ec') }}"></div>
                                <small class="{{ $state === 'cur' ? 'font-weight-bold' : 'text-muted' }}">{{ $sg }}</small>
                            </div>
                        @endforeach
                    </div>
                    @if ($stage === 'Rejected')
                        <p class="text-muted mb-3"><i class="fas fa-ban mr-1"></i> Rejected / Dead — removed from the active funnel.</p>
                    @endif

                    {{-- Advance --}}
                    <div class="d-flex flex-wrap align-items-center" style="gap:.5rem">
                        @if ($next)
                            <form method="POST" action="{{ route('admin.crm.stage', $account) }}">
                                @csrf
                                <input type="hidden" name="stage" value="{{ $next }}">
                                <button class="btn btn-primary"><i class="fas fa-arrow-right mr-1"></i> Advance to {{ $next }}</button>
                            </form>
                        @endif

                        {{-- Set any stage --}}
                        <div class="btn-group btn-group-sm ml-auto" role="group" aria-label="Set stage">
                            @foreach (\App\Models\CrmAccount::COMMUNITY_STAGES as $sg)
                                <form method="POST" action="{{ route('admin.crm.stage', $account) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="stage" value="{{ $sg }}">
                                    <button class="btn {{ $sg === $stage ? 'btn-dark' : ($sg === 'Rejected' ? 'btn-outline-danger' : 'btn-outline-secondary') }}"
                                        @disabled($sg === $stage)>{{ $sg }}</button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            @if ($firstTouch ?? false)
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0">First-touch draft</h3>
                        <form method="POST" action="{{ route('admin.crm.first-touch', $account) }}" class="ml-auto mb-0">
                            @csrf
                            <button class="btn btn-sm btn-outline-primary"><i class="fas fa-paper-plane mr-1"></i> Log as sent</button>
                        </form>
                    </div>
                    <div class="card-body">
                        <textarea class="form-control mb-2" rows="9" readonly onclick="this.select()">{{ $firstTouch }}</textarea>
                        <small class="text-muted">Personalised from this lead — copy, edit, send. “Log as sent” records it and moves Target → Contacted.</small>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header"><h3 class="card-title mb-0">Activity</h3></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.crm.activity', $account) }}" class="form-inline mb-3" style="gap:.5rem">
                        @csrf
                        <input type="text" name="body" class="form-control flex-grow-1" placeholder="Log a note (call, DM, reply…)" maxlength="2000" required>
                        <button class="btn btn-outline-primary">Add</button>
                    </form>

                    @forelse ($account->activities as $a)
                        <div class="d-flex mb-2 pb-2 border-bottom">
                            <div class="mr-2 text-muted" style="width:20px">
                                <i class="fas {{ ['stage_change' => 'fa-flag', 'first_touch' => 'fa-paper-plane', 'contact' => 'fa-comment', 'note' => 'fa-sticky-note'][$a->type] ?? 'fa-circle' }}"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div>{{ $a->body }}</div>
                                <small class="text-muted">{{ $a->actor ?? 'system' }} · {{ $a->created_at->format('d M Y, H:i') }}</small>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No activity yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Contact + facts --}}
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title mb-0">Contact</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr><th class="text-muted" style="width:38%">Instagram</th>
                                <td>@if ($account->instagram_handle ?? ($m['handle'] ?? null))
                                    @php $h = ltrim($account->instagram_handle ?? $m['handle'], '@'); @endphp
                                    <a href="https://instagram.com/{{ $h }}" target="_blank" rel="noopener">{{ '@'.$h }}</a>
                                @else — @endif</td></tr>
                            <tr><th class="text-muted">Audience</th><td>{{ $m['audience'] ?? '—' }}</td></tr>
                            <tr><th class="text-muted">City</th><td>{{ $m['city'] ?? '—' }}</td></tr>
                            <tr><th class="text-muted">Confidence</th><td>{{ $m['confidence'] ?? '—' }}</td></tr>
                            <tr><th class="text-muted">Fit score</th><td><span class="badge badge-{{ $account->score >= 60 ? 'success' : ($account->score >= 40 ? 'warning' : 'secondary') }}">{{ $account->score }}</span></td></tr>
                            <tr><th class="text-muted">Owner</th><td>{{ $account->owner ?? '—' }}</td></tr>
                            <tr><th class="text-muted">Email</th><td>{{ $account->email ?? '—' }}</td></tr>
                            <tr><th class="text-muted">WhatsApp</th><td>{{ $account->whatsapp ?? '—' }}</td></tr>
                            <tr><th class="text-muted">Evidence</th>
                                <td>@if ($ev = ($m['evidence_url'] ?? null))
                                    <a href="{{ $ev }}" target="_blank" rel="noopener">{{ parse_url($ev, PHP_URL_HOST) ?: 'link' }}</a>
                                @else — @endif</td></tr>
                            <tr><th class="text-muted">Collabs</th><td>{{ $m['collab_businesses'] ?? ($m['collabs'] ?? '—') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($m['reason'] ?? false)
                <div class="card">
                    <div class="card-header"><h3 class="card-title mb-0">Why this lead</h3></div>
                    <div class="card-body"><p class="mb-0 text-sm">{{ $m['reason'] }}</p></div>
                </div>
            @endif
        </div>
    </div>
@endsection
