<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCompanySettingRequest;
use App\Services\Admin\CompanySettingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CompanySettingController extends Controller
{
    public function __construct(private readonly CompanySettingService $service) {}

    public function edit(): View
    {
        return view('admin.company-settings.edit', [
            'company' => $this->service->current(),
        ]);
    }

    public function update(UpdateCompanySettingRequest $request): RedirectResponse
    {
        $this->service->update($request->validated());

        return redirect()
            ->route('admin.company-settings.edit')
            ->with('status', __('Company details saved. The legal pages now show the new values; bumping the version re-prompts app users for consent.'));
    }
}
