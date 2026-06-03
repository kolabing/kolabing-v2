@extends('admin.layout', ['title' => 'Reward economics'])

@section('page_title', 'Reward economics')
@section('page_subtitle', 'Per-point rate, withdrawal threshold, and referral incentives. Edits here flow into the live withdrawal flow.')

@section('admin_content')
    <div class="row">
        <div class="col-lg-7">
            <div class="card card-primary card-outline">
                <form method="POST" action="{{ route('admin.gamification.economics.update') }}" id="economics-form">
                    @csrf
                    @method('PUT')

                    <div class="card-body">
                        <h5 class="mb-3">Withdrawal</h5>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="euro_cents_per_point">Per-point rate (€ cents)</label>
                                <input type="number" name="euro_cents_per_point" id="euro_cents_per_point"
                                       min="1" max="500" required class="form-control"
                                       value="{{ old('euro_cents_per_point', $economics->euro_cents_per_point) }}">
                                <small class="form-text text-muted">
                                    <span id="display-per-point">€{{ number_format($economics->euro_cents_per_point / 100, 2) }}</span> per point.
                                </small>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="withdrawal_threshold_cents">Withdrawal threshold (€ cents)</label>
                                <input type="number" name="withdrawal_threshold_cents" id="withdrawal_threshold_cents"
                                       min="0" max="1000000" required class="form-control"
                                       value="{{ old('withdrawal_threshold_cents', $economics->withdrawal_threshold_cents) }}">
                                <small class="form-text text-muted">
                                    <span id="display-threshold-eur">€{{ number_format($economics->withdrawal_threshold_cents / 100, 2) }}</span>
                                    = <span id="display-threshold-points">{{ $currentImpact['threshold_points'] }}</span> points required.
                                </small>
                            </div>
                        </div>

                        <hr>
                        <h5 class="mb-3">Referral</h5>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="referral_goal">Referrals required to unlock cash reward</label>
                                <input type="number" name="referral_goal" id="referral_goal" min="1" max="100" required
                                       class="form-control" value="{{ old('referral_goal', $economics->referral_goal) }}">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="referral_cash_reward_cents">Referral cash reward (€ cents)</label>
                                <input type="number" name="referral_cash_reward_cents" id="referral_cash_reward_cents"
                                       min="0" max="1000000" required class="form-control"
                                       value="{{ old('referral_cash_reward_cents', $economics->referral_cash_reward_cents) }}">
                                <small class="form-text text-muted">
                                    €<span id="display-referral-eur">{{ number_format($economics->referral_cash_reward_cents / 100, 2) }}</span>
                                </small>
                            </div>
                        </div>

                        <hr>
                        <h5 class="mb-3">Other</h5>

                        <div class="form-group">
                            <label for="currency">Currency</label>
                            <input type="text" name="currency" id="currency" maxlength="3" required
                                   class="form-control" style="max-width: 100px;"
                                   value="{{ old('currency', $economics->currency) }}">
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card card-warning card-outline">
                <div class="card-header"><h3 class="card-title mb-0">Cost-impact preview</h3></div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        How many community wallets would qualify for a payout at the values currently in the form,
                        and the total euro liability if every one of them cashed out today. Updates as you type.
                    </p>
                    <dl class="row mb-0">
                        <dt class="col-sm-7">Threshold (points)</dt>
                        <dd class="col-sm-5 text-right"><span id="impact-threshold-points">{{ $currentImpact['threshold_points'] }}</span></dd>

                        <dt class="col-sm-7">Eligible wallets</dt>
                        <dd class="col-sm-5 text-right"><span id="impact-eligible">{{ $currentImpact['eligible_wallets'] }}</span></dd>

                        <dt class="col-sm-7">Per-point rate</dt>
                        <dd class="col-sm-5 text-right">€<span id="impact-rate">{{ number_format($currentImpact['per_point_euros'], 4) }}</span></dd>

                        <dt class="col-sm-7 font-weight-bold">Total potential payout</dt>
                        <dd class="col-sm-5 text-right font-weight-bold">€<span id="impact-payout">{{ number_format($currentImpact['total_payout_euros'], 2) }}</span></dd>
                    </dl>
                </div>
                <div class="card-footer small text-muted">
                    Live preview uses the same math as the withdrawal flow.
                    Server values shown here on page load; typing updates the
                    points-per-threshold + euro estimate. The "eligible wallets"
                    count is the server snapshot — refresh after large changes.
                </div>
            </div>
        </div>
    </div>

    {{-- Vanilla JS preview. CSP allows 'unsafe-inline' so this works without
         a build step. No external libs. --}}
    <script>
        (function () {
            const ratePoints  = document.getElementById('euro_cents_per_point');
            const thresholdEl = document.getElementById('withdrawal_threshold_cents');
            const referralEl  = document.getElementById('referral_cash_reward_cents');

            const displayPerPoint     = document.getElementById('display-per-point');
            const displayThresholdEur = document.getElementById('display-threshold-eur');
            const displayThresholdPts = document.getElementById('display-threshold-points');
            const displayReferralEur  = document.getElementById('display-referral-eur');

            const impactThreshold = document.getElementById('impact-threshold-points');
            const impactRate      = document.getElementById('impact-rate');
            const impactPayout    = document.getElementById('impact-payout');
            const impactEligible  = document.getElementById('impact-eligible');

            const eligibleSnapshot = parseInt(impactEligible.textContent, 10) || 0;

            function fmt(n, places) {
                return (Math.round(n * Math.pow(10, places)) / Math.pow(10, places))
                    .toFixed(places);
            }

            function update() {
                const rateCents      = parseInt(ratePoints.value, 10);
                const thresholdCents = parseInt(thresholdEl.value, 10);
                const referralCents  = parseInt(referralEl.value, 10);

                if (rateCents > 0) {
                    const points = Math.ceil(thresholdCents / rateCents);
                    displayPerPoint.textContent = '€' + fmt(rateCents / 100, 2);
                    displayThresholdEur.textContent = '€' + fmt(thresholdCents / 100, 2);
                    displayThresholdPts.textContent = points;

                    impactThreshold.textContent = points;
                    impactRate.textContent = fmt(rateCents / 100, 4);
                    impactPayout.textContent = fmt((eligibleSnapshot * points * rateCents) / 100, 2);
                }

                displayReferralEur.textContent = fmt(referralCents / 100, 2);
            }

            [ratePoints, thresholdEl, referralEl].forEach(function (el) {
                el.addEventListener('input', update);
            });
        })();
    </script>
@endsection
