@extends('admin.layout', ['title' => 'Company & legal'])

@section('page_title', 'Company & legal details')
@section('page_subtitle', 'These values populate the public Terms of Service + Privacy Policy pages. Bumping the version re-prompts app users to accept.')

@section('admin_content')
    <div class="row">
        <div class="col-lg-8">
            <div class="card card-primary card-outline">
                <form method="POST" action="{{ route('admin.company-settings.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="card-body">
                        <h5 class="mb-3">Company identity</h5>

                        <div class="form-group">
                            <label for="legal_name">Registered company name <span class="text-danger">*</span></label>
                            <input type="text" name="legal_name" id="legal_name" required class="form-control"
                                   value="{{ old('legal_name', $company->legal_name) }}">
                            <small class="form-text text-muted">Fills <code>[COMPANY NAME]</code> everywhere in the agreements.</small>
                        </div>

                        <div class="form-group">
                            <label for="registration_number">Company registration number / NIF</label>
                            <input type="text" name="registration_number" id="registration_number" class="form-control"
                                   value="{{ old('registration_number', $company->registration_number) }}">
                        </div>

                        <div class="form-group">
                            <label for="registered_address">Registered address</label>
                            <textarea name="registered_address" id="registered_address" rows="2" class="form-control">{{ old('registered_address', $company->registered_address) }}</textarea>
                        </div>

                        <hr>
                        <h5 class="mb-3">Contact</h5>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="privacy_email">Privacy / data-protection email <span class="text-danger">*</span></label>
                                <input type="email" name="privacy_email" id="privacy_email" required class="form-control"
                                       value="{{ old('privacy_email', $company->privacy_email) }}">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="support_email">Support email <span class="text-danger">*</span></label>
                                <input type="email" name="support_email" id="support_email" required class="form-control"
                                       value="{{ old('support_email', $company->support_email) }}">
                            </div>
                        </div>

                        <hr>
                        <h5 class="mb-3">Terms-specific</h5>

                        <div class="form-group">
                            <label for="refund_policy">Refund policy</label>
                            <textarea name="refund_policy" id="refund_policy" rows="3" class="form-control">{{ old('refund_policy', $company->refund_policy) }}</textarea>
                            <small class="form-text text-muted">Fills <code>[REFUND POLICY]</code> in the Terms. Plain text.</small>
                        </div>

                        <hr>
                        <h5 class="mb-3">Agreement version</h5>
                        <p class="text-muted small">Change the version (and effective date) when you make a material change to the agreements. App users who accepted an older version are re-prompted to accept on next launch.</p>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="terms_version">Version <span class="text-danger">*</span></label>
                                <input type="text" name="terms_version" id="terms_version" required class="form-control"
                                       value="{{ old('terms_version', $company->terms_version) }}">
                                <small class="form-text text-muted">A short, always-increasing tag. A date like <code>{{ now()->toDateString() }}</code> works well.</small>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="terms_effective_date">Effective date <span class="text-danger">*</span></label>
                                <input type="date" name="terms_effective_date" id="terms_effective_date" required class="form-control"
                                       value="{{ old('terms_effective_date', optional($company->terms_effective_date)->toDateString()) }}">
                                <small class="form-text text-muted">Shown as "Last updated" on the pages.</small>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card card-info card-outline">
                <div class="card-header"><h3 class="card-title mb-0">Preview the pages</h3></div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Open the live pages to check how the values read in context.</p>
                    <ul class="mb-0 pl-3">
                        <li><a href="{{ route('terms') }}" target="_blank" rel="noopener">Terms (EN)</a> · <a href="{{ route('terms.es') }}" target="_blank" rel="noopener">ES</a></li>
                        <li><a href="{{ route('privacy') }}" target="_blank" rel="noopener">Privacy (EN)</a> · <a href="{{ route('privacy.es') }}" target="_blank" rel="noopener">ES</a></li>
                    </ul>
                </div>
                <div class="card-footer small text-muted">
                    Empty company fields fall back to <code>[PLACEHOLDER]</code> text so unfilled details are obvious.
                </div>
            </div>
        </div>
    </div>
@endsection
