@extends('admin.layout', ['title' => 'Business visibility boost'])

@section('page_title', 'Business visibility boost')
@section('page_subtitle', 'Extra discovery-match points earned by Trusted Partner / Community Favourite businesses. This is the reward behind partner status — higher status means communities see the business\'s offers ranked higher.')

@section('admin_content')
    <div class="row">
        <div class="col-lg-7">
            <div class="card card-primary card-outline">
                <form method="POST" action="{{ route('admin.gamification.business-visibility-boost.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="trusted_partner_points">✓ Trusted Partner boost</label>
                                <input type="number" name="trusted_partner_points" id="trusted_partner_points"
                                       min="0" max="50" required class="form-control"
                                       value="{{ old('trusted_partner_points', $settings->trusted_partner_points) }}">
                                <small class="form-text text-muted">Points added to a Trusted Partner business's discovery match score for a community viewer.</small>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="community_favourite_points">★ Community Favourite boost</label>
                                <input type="number" name="community_favourite_points" id="community_favourite_points"
                                       min="0" max="50" required class="form-control"
                                       value="{{ old('community_favourite_points', $settings->community_favourite_points) }}">
                                <small class="form-text text-muted">Should be at least as large as the Trusted Partner boost.</small>
                            </div>
                        </div>

                        <div class="alert alert-info mb-0">
                            <strong>How this works:</strong> the boost is added on top of the existing category/location/value/recency
                            match score, capped at 100 total — it never overrides genuine fit, it just breaks ties in favour of
                            businesses with a stronger track record. New Partner and Active Partner businesses get no boost.
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
