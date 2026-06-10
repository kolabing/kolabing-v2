<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminColumnPref;
use App\Models\CrmAccount;
use App\Services\CrmScoreService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CrmController extends Controller
{
    public function __construct(private readonly CrmScoreService $score) {}

    /**
     * Column catalog per type: key => [label, default-visible, is-metric].
     * Top-level keys read $account->{key}; metric keys read metrics[key].
     */
    public function columnsFor(string $type): array
    {
        $common = [
            'name' => ['Name', true, false],
            'status' => ['Status', true, false],
            'owner' => ['Owner', true, false],
            'score' => ['Score', true, false],
            'last_activity_at' => ['Last activity', true, false],
            'next_action' => ['Next action', true, false],
            'email' => ['Email', false, false],
            'instagram_handle' => ['Instagram', false, false],
            'whatsapp' => ['WhatsApp', false, false],
            'notes' => ['Notes', false, false],
        ];

        return match ($type) {
            'business' => $common + [
                'category' => ['Category', false, true],
                'neighborhood' => ['Neighborhood', false, true],
                'followers' => ['Followers', false, true],
                'instagram_language' => ['IG language', false, true],
                'event_potential' => ['Event potential', false, true],
                'potential_kolabs' => ['Potential Kolabs', false, true],
                'address' => ['Address', false, true],
            ],
            'ambassador' => $common + [
                'sector' => ['Sector', false, true],
                'community_if_fp' => ['Community (FP)', false, true],
                'businesses_referred' => ['Biz referred', true, true],
                'communities_referred' => ['Comm referred', true, true],
                'kolabs_generated' => ['Kolabs gen.', true, true],
                'product_feedback' => ['Feedback', false, true],
                'feature_suggestions' => ['Suggestions', false, true],
                'kolabs_this_month' => ['Kolabs/month', false, true],
                'monthly_call_attendance' => ['Calls', false, true],
            ],
            default => $common + [ // community
                'sector' => ['Sector / niche', true, true],
                'member_count' => ['Members', true, true],
                'leader_contact' => ['Leader contact', false, true],
            ],
        };
    }

    private function tableKey(string $type): string
    {
        return "crm.$type";
    }

    /** Visible columns: saved per-admin pref, else the catalog defaults. */
    private function visibleColumns(string $type): array
    {
        $catalog = $this->columnsFor($type);
        $pref = AdminColumnPref::query()
            ->where('admin_id', auth('admin')->id())
            ->where('table_key', $this->tableKey($type))
            ->first();

        if ($pref !== null) {
            return array_values(array_intersect(array_keys($catalog), $pref->visible_columns));
        }

        return array_keys(array_filter($catalog, static fn (array $c): bool => $c[1]));
    }

    public function index(Request $request): View
    {
        $type = in_array($request->query('type'), CrmAccount::TYPES, true)
            ? $request->query('type') : 'business';

        $query = CrmAccount::query()->where('type', $type);
        if ($owner = $request->query('owner')) {
            $query->where('owner', $owner);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($q = $request->query('q')) {
            $query->where('name', 'like', "%{$q}%");
        }

        $accounts = $query->orderByDesc('score')->orderBy('name')->paginate(50)->withQueryString();

        return view('admin.crm.index', [
            'type' => $type,
            'accounts' => $accounts,
            'catalog' => $this->columnsFor($type),
            'visible' => $this->visibleColumns($type),
            'owners' => CrmAccount::query()->where('type', $type)->whereNotNull('owner')->distinct()->pluck('owner'),
            'statuses' => CrmAccount::query()->where('type', $type)->whereNotNull('status')->distinct()->pluck('status'),
            'filters' => $request->only(['owner', 'status', 'q']),
        ]);
    }

    public function saveColumns(Request $request): RedirectResponse
    {
        $type = in_array($request->input('type'), CrmAccount::TYPES, true) ? $request->input('type') : 'business';
        $allowed = array_keys($this->columnsFor($type));
        $cols = array_values(array_intersect($allowed, (array) $request->input('columns', [])));
        if (! in_array('name', $cols, true)) {
            array_unshift($cols, 'name');
        }

        AdminColumnPref::query()->updateOrCreate(
            ['admin_id' => auth('admin')->id(), 'table_key' => $this->tableKey($type)],
            ['visible_columns' => $cols],
        );

        return redirect()->route('admin.crm.index', ['type' => $type])->with('status', 'Columns updated.');
    }

    public function create(Request $request): View
    {
        $type = in_array($request->query('type'), CrmAccount::TYPES, true) ? $request->query('type') : 'business';

        return view('admin.crm.edit', ['type' => $type, 'account' => new CrmAccount(['type' => $type])]);
    }

    public function edit(CrmAccount $account): View
    {
        return view('admin.crm.edit', ['type' => $account->type, 'account' => $account]);
    }

    public function store(Request $request): RedirectResponse
    {
        $account = CrmAccount::query()->create($this->validated($request));
        $this->score->recalculate($account);
        $this->syncNextActionTask($account);

        return redirect()->route('admin.crm.index', ['type' => $account->type])->with('status', 'Account created.');
    }

    public function update(Request $request, CrmAccount $account): RedirectResponse
    {
        $account->update($this->validated($request, $account));
        $this->score->recalculate($account);
        $this->syncNextActionTask($account);

        return redirect()->route('admin.crm.index', ['type' => $account->type])->with('status', 'Account updated.');
    }

    /**
     * Mirror the account's "Next action" to one open linked task —
     * Area = Sales, Subarea = the account's tab, assignee = the owner.
     */
    private function syncNextActionTask(CrmAccount $account): void
    {
        if (blank($account->next_action)) {
            return;
        }

        $attrs = [
            'title' => $account->next_action,
            'area' => 'sales',
            'subarea' => TaskController::subareaForType($account->type),
            'assignee' => $account->owner,
        ];

        $open = $account->tasks()->where('status', '!=', 'done')->first();
        if ($open !== null) {
            $open->update($attrs);
        } else {
            $account->tasks()->create($attrs + ['status' => 'open']);
        }
    }

    public function destroy(CrmAccount $account): RedirectResponse
    {
        $type = $account->type;
        $account->delete();

        return redirect()->route('admin.crm.index', ['type' => $type])->with('status', 'Account deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CrmAccount $account = null): array
    {
        $type = $account?->type ?? ($request->input('type'));
        $data = $request->validate([
            'type' => ['required', 'in:business,community,ambassador'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:30'],
            'owner' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'instagram_handle' => ['nullable', 'string', 'max:80'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'last_activity_at' => ['nullable', 'date'],
            'metrics' => ['nullable', 'array'],
        ]);

        // Coerce business fit factors (checkboxes) to booleans; counts to ints.
        $metrics = $data['metrics'] ?? [];
        if ($type === 'business') {
            foreach (['active_ig', 'hosts_events', 'community_friendly', 'multiple_locations', 'good_fit', 'responsive'] as $f) {
                $metrics[$f] = ! empty($metrics[$f]);
            }
        }
        if ($type === 'ambassador') {
            foreach (['businesses_referred', 'communities_referred', 'businesses_converted', 'communities_activated', 'kolabs_generated', 'product_feedback', 'feature_suggestions', 'kolabs_this_month', 'monthly_call_attendance'] as $c) {
                if (array_key_exists($c, $metrics)) {
                    $metrics[$c] = (int) $metrics[$c];
                }
            }
        }
        $data['metrics'] = $metrics;

        return $data;
    }
}
