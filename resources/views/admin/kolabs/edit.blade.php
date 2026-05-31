@extends('admin.layout', ['title' => 'Edit Kolab'])

@section('page_title', 'Edit Kolab')
@section('page_subtitle', $kolab->title)

@section('page_actions')
    <a href="{{ route('admin.kolabs.index') }}" class="btn btn-outline-secondary mr-2">
        <i class="fas fa-arrow-left mr-1"></i>
        Back
    </a>
    <form method="POST" action="{{ route('admin.kolabs.destroy', $kolab) }}" class="d-inline" onsubmit="return confirm('Delete this Kolab? This cannot be undone.');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger">
            <i class="fas fa-trash mr-1"></i>
            Delete
        </button>
    </form>
@endsection

@section('admin_content')
    @php
        $creator = $kolab->creatorProfile;
        $creatorLabel = $creator?->businessProfile?->name
            ?? $creator?->communityProfile?->name
            ?? $creator?->email
            ?? '—';
    @endphp

    <div class="card mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Creator</dt>
                <dd class="col-sm-9">{{ $creatorLabel }} <small class="text-muted">({{ $creator?->user_type->value ?? '—' }})</small></dd>
                <dt class="col-sm-3">Intent</dt>
                <dd class="col-sm-9">{{ str_replace('_', ' ', $kolab->intent_type->value) }}</dd>
                <dt class="col-sm-3">Published at</dt>
                <dd class="col-sm-9">{{ $kolab->published_at?->toDayDateTimeString() ?? '—' }}</dd>
            </dl>
        </div>
    </div>

    @include('admin.kolabs._lifecycle', ['summary' => $summary, 'kolab' => $kolab])

    <form method="POST" action="{{ route('admin.kolabs.update', $kolab) }}">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-body">
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" name="title" id="title" value="{{ old('title', $kolab->title) }}" class="form-control" required maxlength="255">
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" rows="5" class="form-control" required maxlength="5000">{{ old('description', $kolab->description) }}</textarea>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="preferred_city">Preferred city</label>
                        <input type="text" name="preferred_city" id="preferred_city" value="{{ old('preferred_city', $kolab->preferred_city) }}" class="form-control" required maxlength="100">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="area">Area <small class="text-muted">(optional)</small></label>
                        <input type="text" name="area" id="area" value="{{ old('area', $kolab->area) }}" class="form-control" maxlength="100">
                    </div>
                </div>

                <div class="form-group">
                    <label for="offer_headline">Offer headline <small class="text-muted">(optional)</small></label>
                    <input type="text" name="offer_headline" id="offer_headline" value="{{ old('offer_headline', $kolab->offer_headline) }}" class="form-control" maxlength="255">
                </div>

                <div class="form-group">
                    <label for="base_offer">Base offer <small class="text-muted">(optional)</small></label>
                    <textarea name="base_offer" id="base_offer" rows="3" class="form-control" maxlength="2000">{{ old('base_offer', $kolab->base_offer) }}</textarea>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-control" required>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" {{ old('status', $kolab->status->value) === $status->value ? 'selected' : '' }}>
                                {{ ucfirst($status->value) }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">Moving a draft to "published" stamps the publication date automatically.</small>
                </div>
            </div>
            <div class="card-footer text-right">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save mr-1"></i>
                    Save changes
                </button>
            </div>
        </div>
    </form>
@endsection
