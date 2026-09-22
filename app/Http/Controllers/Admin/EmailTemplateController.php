<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEmailTemplateRequest;
use App\Http\Requests\Admin\UpdateEmailTemplateRequest;
use App\Models\AdminWelcomeEmailTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Admin-editable quick-add welcome email content, per language — Daniel 2026-09-14:
 * "make the template editable in admin dashboard in all languages (add/edit/remove
 * languages)". The two action buttons (view listing / set password) and the URLs they
 * point to are NOT editable here by design — see AdminProfileWelcomeMail.
 */
class EmailTemplateController extends Controller
{
    public function index(): View
    {
        return view('admin.email-templates.index', [
            'templates' => AdminWelcomeEmailTemplate::query()->orderBy('label')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.email-templates.create');
    }

    public function store(StoreEmailTemplateRequest $request): RedirectResponse
    {
        AdminWelcomeEmailTemplate::query()->create($request->validated());

        return redirect()->route('admin.email-templates.index')
            ->with('status', __('Language added.'));
    }

    public function edit(AdminWelcomeEmailTemplate $emailTemplate): View
    {
        return view('admin.email-templates.edit', [
            'template' => $emailTemplate,
        ]);
    }

    public function update(UpdateEmailTemplateRequest $request, AdminWelcomeEmailTemplate $emailTemplate): RedirectResponse
    {
        $emailTemplate->update($request->validated());

        return redirect()->route('admin.email-templates.index')
            ->with('status', __('Template updated.'));
    }

    public function destroy(AdminWelcomeEmailTemplate $emailTemplate): RedirectResponse
    {
        $emailTemplate->delete();

        return redirect()->route('admin.email-templates.index')
            ->with('status', __('Language removed.'));
    }
}
