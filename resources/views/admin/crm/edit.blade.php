@extends('admin.layout', ['title' => $account->exists ? 'Edit account' : 'New account'])

@section('page_title', $account->exists ? 'Edit '.$account->name : 'New '.ucfirst($type))

@section('admin_content')
    @php $m = old('metrics', $account->metrics ?? []); @endphp
    <form method="POST" action="{{ $account->exists ? route('admin.crm.update', $account) : route('admin.crm.store') }}">
        @csrf
        @if ($account->exists) @method('PUT') @endif
        <input type="hidden" name="type" value="{{ $type }}">

        <div class="card">
            <div class="card-header font-weight-bold">Details</div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Name</label>
                        <input name="name" value="{{ old('name', $account->name) }}" class="form-control" required>
                    </div>
                    <div class="form-group col-md-3">
                        <label>Status</label>
                        <input name="status" value="{{ old('status', $account->status) }}" class="form-control" placeholder="Target / Contacted / Interested / Active">
                    </div>
                    <div class="form-group col-md-3">
                        <label>Owner</label>
                        <input name="owner" value="{{ old('owner', $account->owner) }}" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4"><label>Email</label><input name="email" value="{{ old('email', $account->email) }}" class="form-control"></div>
                    <div class="form-group col-md-2"><label>Phone</label><input name="phone" value="{{ old('phone', $account->phone) }}" class="form-control"></div>
                    <div class="form-group col-md-3"><label>Instagram</label><input name="instagram_handle" value="{{ old('instagram_handle', $account->instagram_handle) }}" class="form-control"></div>
                    <div class="form-group col-md-3"><label>WhatsApp</label><input name="whatsapp" value="{{ old('whatsapp', $account->whatsapp) }}" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-8"><label>Next action</label><input name="next_action" value="{{ old('next_action', $account->next_action) }}" class="form-control"></div>
                    <div class="form-group col-md-4"><label>Last activity</label><input type="date" name="last_activity_at" value="{{ old('last_activity_at', $account->last_activity_at?->format('Y-m-d')) }}" class="form-control"></div>
                </div>
                <div class="form-group"><label>Notes</label><textarea name="notes" class="form-control" rows="2">{{ old('notes', $account->notes) }}</textarea></div>
            </div>
        </div>

        @if ($type === 'business')
            <div class="card">
                <div class="card-header font-weight-bold">Fit factors (drive the score)</div>
                <div class="card-body">
                    <div class="form-row">
                        @foreach (['active_ig' => 'Active IG (+10)', 'hosts_events' => 'Hosts events (+20)', 'community_friendly' => 'Community friendly (+20)', 'multiple_locations' => 'Multiple locations (+10)', 'good_fit' => 'Good Kolab fit (+20)', 'responsive' => 'Responsive (+20)'] as $k => $lbl)
                            <div class="form-group col-md-4 form-check ml-3">
                                <input type="hidden" name="metrics[{{ $k }}]" value="0">
                                <input class="form-check-input" type="checkbox" name="metrics[{{ $k }}]" value="1" id="f-{{ $k }}" @checked(! empty($m[$k]))>
                                <label class="form-check-label" for="f-{{ $k }}">{{ $lbl }}</label>
                            </div>
                        @endforeach
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-3"><label>Category</label><input name="metrics[category]" value="{{ $m['category'] ?? '' }}" class="form-control"></div>
                        <div class="form-group col-md-3"><label>Neighborhood</label><input name="metrics[neighborhood]" value="{{ $m['neighborhood'] ?? '' }}" class="form-control"></div>
                        <div class="form-group col-md-2"><label>Followers</label><input name="metrics[followers]" value="{{ $m['followers'] ?? '' }}" class="form-control"></div>
                        <div class="form-group col-md-2"><label>IG language</label><input name="metrics[instagram_language]" value="{{ $m['instagram_language'] ?? '' }}" class="form-control"></div>
                        <div class="form-group col-md-2"><label>Event potential</label><input name="metrics[event_potential]" value="{{ $m['event_potential'] ?? '' }}" class="form-control"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-8"><label>Potential Kolabs</label><input name="metrics[potential_kolabs]" value="{{ $m['potential_kolabs'] ?? '' }}" class="form-control"></div>
                        <div class="form-group col-md-4"><label>Address</label><input name="metrics[address]" value="{{ $m['address'] ?? '' }}" class="form-control"></div>
                    </div>
                </div>
            </div>
        @elseif ($type === 'ambassador')
            <div class="card">
                <div class="card-header font-weight-bold">Contribution (drives the score)</div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group col-md-4"><label>Sector</label><input name="metrics[sector]" value="{{ $m['sector'] ?? '' }}" class="form-control"></div>
                        <div class="form-group col-md-4"><label>Community (if FP)</label><input name="metrics[community_if_fp]" value="{{ $m['community_if_fp'] ?? '' }}" class="form-control"></div>
                    </div>
                    <div class="form-row">
                        @foreach (['businesses_referred' => 'Biz referred (×20)', 'communities_referred' => 'Comm referred (×15)', 'kolabs_generated' => 'Kolabs generated (×30)', 'product_feedback' => 'Product feedback (×10)', 'feature_suggestions' => 'Feature suggestions (×5)', 'businesses_converted' => 'Biz converted', 'communities_activated' => 'Comm activated', 'kolabs_this_month' => 'Kolabs this month', 'monthly_call_attendance' => 'Monthly calls'] as $k => $lbl)
                            <div class="form-group col-md-3"><label>{{ $lbl }}</label><input type="number" min="0" name="metrics[{{ $k }}]" value="{{ $m[$k] ?? 0 }}" class="form-control"></div>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-header font-weight-bold">Community</div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group col-md-4"><label>Sector / niche</label><input name="metrics[sector]" value="{{ $m['sector'] ?? '' }}" class="form-control"></div>
                        <div class="form-group col-md-3"><label>Members</label><input type="number" min="0" name="metrics[member_count]" value="{{ $m['member_count'] ?? '' }}" class="form-control"></div>
                        <div class="form-group col-md-5"><label>Leader contact</label><input name="metrics[leader_contact]" value="{{ $m['leader_contact'] ?? '' }}" class="form-control"></div>
                    </div>
                </div>
            </div>
        @endif

        <div class="d-flex justify-content-between">
            <a href="{{ route('admin.crm.index', ['type' => $type]) }}" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn btn-primary">{{ $account->exists ? 'Save' : 'Create' }}</button>
        </div>
    </form>
@endsection
