<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrmTask;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $query = CrmTask::query()->with('account');
        foreach (['assignee', 'area', 'subarea'] as $f) {
            if ($v = $request->query($f)) {
                $query->where($f, $v);
            }
        }
        // Default to open work unless a status is chosen.
        $status = $request->query('status', 'active');
        if ($status === 'active') {
            $query->whereIn('status', ['open', 'doing']);
        } elseif (in_array($status, CrmTask::STATUSES, true)) {
            $query->where('status', $status);
        }

        $tasks = $query->orderByRaw("CASE area WHEN 'sales' THEN 1 WHEN 'marketing' THEN 2 ELSE 3 END")
            ->orderBy('subarea')->orderByRaw('due_on is null, due_on')->get();

        return view('admin.tasks.index', [
            'grouped' => $tasks->groupBy(['area', 'subarea']),
            'assignees' => CrmTask::query()->whereNotNull('assignee')->distinct()->pluck('assignee'),
            'filters' => array_merge(['status' => $status], $request->only(['assignee', 'area', 'subarea'])),
        ]);
    }

    public function create(): View
    {
        return view('admin.tasks.edit', ['task' => new CrmTask(['area' => 'sales', 'subarea' => 'business', 'status' => 'open'])]);
    }

    public function edit(CrmTask $task): View
    {
        return view('admin.tasks.edit', ['task' => $task]);
    }

    public function store(Request $request): RedirectResponse
    {
        CrmTask::query()->create($this->validated($request));

        return redirect()->route('admin.tasks.index')->with('status', 'Task created.');
    }

    public function update(Request $request, CrmTask $task): RedirectResponse
    {
        $task->update($this->validated($request));

        return redirect()->route('admin.tasks.index')->with('status', 'Task updated.');
    }

    public function destroy(CrmTask $task): RedirectResponse
    {
        $task->delete();

        return redirect()->route('admin.tasks.index')->with('status', 'Task deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'crm_account_id' => ['nullable', 'uuid', 'exists:crm_accounts,id'],
            'assignee' => ['nullable', 'string', 'max:60'],
            'area' => ['required', Rule::in(CrmTask::AREAS)],
            'subarea' => ['required', Rule::in(CrmTask::SUBAREAS)],
            'due_on' => ['nullable', 'date'],
            'status' => ['required', Rule::in(CrmTask::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);

        // Ambassadors tasks only under sales/marketing.
        $rule = CrmTask::SUBAREA_AREA_RULES[$data['subarea']] ?? null;
        if ($rule !== null && ! in_array($data['area'], $rule, true)) {
            throw ValidationException::withMessages([
                'subarea' => 'Ambassadors tasks must be under Sales or Marketing.',
            ]);
        }

        return $data;
    }

    /**
     * Create a task from a CRM account's "Next action" — Area defaults Sales,
     * Subarea from the account's type, assignee = the account's owner.
     */
    public static function subareaForType(string $type): string
    {
        return match ($type) {
            'community' => 'communities',
            'ambassador' => 'ambassadors',
            default => 'business',
        };
    }
}
