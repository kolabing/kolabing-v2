@extends('admin.layout', ['title' => 'Challenge defaults'])

@section('page_title', 'Challenge defaults')
@section('page_subtitle', 'Pick the challenges that auto-seed a new collaboration based on participant business / community type.')

@section('admin_content')
    @php
        $renderTypeRow = function (string $appliesTo, string $typeValue, string $typeLabel) use ($systemChallenges, $matrix) {
            $key = $appliesTo.':'.$typeValue;
            $selected = $matrix[$key] ?? [];

            return compact('appliesTo', 'typeValue', 'typeLabel', 'selected', 'systemChallenges');
        };
    @endphp

    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title mb-0">Business types</h3></div>
        <div class="card-body p-0">
            @foreach ($businessTypes as $businessType)
                @php $context = $renderTypeRow('business_type', $businessType->slug, $businessType->name); @endphp
                <div class="border-bottom p-3">
                    <form method="POST" action="{{ route('admin.gamification.challenges.defaults.update') }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="applies_to" value="business_type">
                        <input type="hidden" name="type_value" value="{{ $context['typeValue'] }}">

                        <div class="form-row align-items-start">
                            <div class="col-md-3">
                                <h5 class="mb-0">{{ $context['typeLabel'] }}</h5>
                                <code class="small text-muted">{{ $context['typeValue'] }}</code>
                            </div>
                            <div class="col-md-7">
                                <div class="d-flex flex-wrap" style="gap: 4px;">
                                    @forelse ($context['systemChallenges'] as $challenge)
                                        <label class="badge badge-light p-2 mb-0" style="font-weight: normal; cursor: pointer;">
                                            <input type="checkbox" name="challenge_ids[]" value="{{ $challenge->id }}"
                                                   {{ in_array($challenge->id, $context['selected'], true) ? 'checked' : '' }}>
                                            {{ $challenge->name }}
                                        </label>
                                    @empty
                                        <span class="text-muted small">No system challenges to choose from.</span>
                                    @endforelse
                                </div>
                            </div>
                            <div class="col-md-2 text-right">
                                <button type="submit" class="btn btn-sm btn-primary">Save row</button>
                            </div>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title mb-0">Community types</h3></div>
        <div class="card-body p-0">
            @foreach ($communityTypes as $communityType)
                @php $context = $renderTypeRow('community_type', $communityType->slug, $communityType->name); @endphp
                <div class="border-bottom p-3">
                    <form method="POST" action="{{ route('admin.gamification.challenges.defaults.update') }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="applies_to" value="community_type">
                        <input type="hidden" name="type_value" value="{{ $context['typeValue'] }}">

                        <div class="form-row align-items-start">
                            <div class="col-md-3">
                                <h5 class="mb-0">{{ $context['typeLabel'] }}</h5>
                                <code class="small text-muted">{{ $context['typeValue'] }}</code>
                            </div>
                            <div class="col-md-7">
                                <div class="d-flex flex-wrap" style="gap: 4px;">
                                    @forelse ($context['systemChallenges'] as $challenge)
                                        <label class="badge badge-light p-2 mb-0" style="font-weight: normal; cursor: pointer;">
                                            <input type="checkbox" name="challenge_ids[]" value="{{ $challenge->id }}"
                                                   {{ in_array($challenge->id, $context['selected'], true) ? 'checked' : '' }}>
                                            {{ $challenge->name }}
                                        </label>
                                    @empty
                                        <span class="text-muted small">No system challenges to choose from.</span>
                                    @endforelse
                                </div>
                            </div>
                            <div class="col-md-2 text-right">
                                <button type="submit" class="btn btn-sm btn-primary">Save row</button>
                            </div>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endsection
