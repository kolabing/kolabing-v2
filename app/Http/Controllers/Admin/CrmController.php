<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminColumnPref;
use App\Models\CrmAccount;
use App\Services\CrmPipelineService;
use App\Services\CrmScoreService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                'category' => ['Category', false, true],
                'location' => ['Location', false, true],
                'ig_followers' => ['IG followers', false, true],
                'whatsapp_members' => ['WhatsApp members', false, true],
                'discord_members' => ['Discord members', false, true],
                'avg_attendance' => ['Avg attendance', false, true],
                'founder_name' => ['Founder', false, true],
                'founder_email' => ['Founder email', false, true],
                'founder_instagram' => ['Founder IG', false, true],
                'ambassador_potential' => ['Ambassador', false, true],
                'founding_partner' => ['Founding partner', false, true],
                // Challenge-A verification metadata (from the verified-leads seed).
                'city' => ['City', true, true],
                'classification' => ['Type', true, true],
                'audience' => ['Audience', true, true],
                'audience_source' => ['Audience src', false, true],
                'confidence' => ['Confidence', true, true],
                'last_active_date' => ['Last active', false, true],
                'evidence_url' => ['Evidence', false, true],
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
            // Preserve the admin's saved ORDER (drop any keys no longer in the catalog).
            return array_values(array_filter(
                $pref->visible_columns,
                static fn (string $key): bool => isset($catalog[$key]),
            ));
        }

        return array_keys(array_filter($catalog, static fn (array $c): bool => $c[1]));
    }

    public function index(Request $request): View
    {
        $type = in_array($request->query('type'), CrmAccount::TYPES, true)
            ? $request->query('type') : 'business';

        // City lives in the metrics JSON: communities key it as `city`, businesses as `source_city`.
        $cityKey = $type === 'business' ? 'source_city' : 'city';

        $query = CrmAccount::query()->where('type', $type);
        if ($owner = $request->query('owner')) {
            $query->where('owner', $owner);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($city = $request->query('city')) {
            $query->where("metrics->{$cityKey}", $city);
        }
        if ($q = $request->query('q')) {
            $query->where('name', 'like', "%{$q}%");
        }
        // "Work now": the operator's daily queue — high/med confidence, locality-confirmed,
        // real fit, not yet contacted. The single most useful supply-side surface.
        $workNow = $request->boolean('work_now') && $type === 'community';
        if ($workNow) {
            $query->where('metrics->locality_confirmed', true)
                ->where('score', '>=', 40)
                ->where('status', 'Target')
                ->where(function ($q2) {
                    $q2->whereRaw("lower(metrics->>'confidence') like 'high%'")
                        ->orWhereRaw("lower(metrics->>'confidence') like 'med%'");
                });
        }

        $accounts = $query->orderByDesc('score')->orderBy('name')->paginate(50)->withQueryString();

        // Funnel counters (community only): leads per stage across the whole set.
        $stageCounts = null;
        if ($type === 'community') {
            $raw = CrmAccount::query()->where('type', 'community')
                ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
            $stageCounts = collect(CrmAccount::COMMUNITY_STAGES)
                ->mapWithKeys(fn (string $s): array => [$s => (int) ($raw[$s] ?? 0)]);
        }

        // Cities present for this type + their counts — powers the city filter dropdown and the map.
        $cityRows = CrmAccount::query()->where('type', $type)
            ->whereNotNull("metrics->{$cityKey}")
            ->selectRaw("metrics->>'{$cityKey}' as city, count(*) as n")
            ->groupByRaw("metrics->>'{$cityKey}'")
            ->orderByDesc('n')
            ->get();

        return view('admin.crm.index', [
            'type' => $type,
            'accounts' => $accounts,
            'catalog' => $this->columnsFor($type),
            'visible' => $this->visibleColumns($type),
            'owners' => CrmAccount::query()->where('type', $type)->whereNotNull('owner')->distinct()->pluck('owner'),
            'statuses' => CrmAccount::query()->where('type', $type)->whereNotNull('status')->distinct()->pluck('status'),
            'cities' => $cityRows->pluck('city'),
            'cityCounts' => $cityRows->pluck('n', 'city'),
            'workNow' => $workNow,
            'stageCounts' => $stageCounts,
            'filters' => $request->only(['owner', 'status', 'q', 'city']),
        ]);
    }

    /**
     * The community pipeline as a Kanban board: one column per stage, cards
     * grouped by their current stage, drag-and-drop moves persisting via
     * moveStage(). Honours the same city / owner / confidence / search filters
     * as the index so a filtered board stays coherent.
     */
    public function board(Request $request): View
    {
        $query = CrmAccount::query()->where('type', 'community');

        if ($owner = $request->query('owner')) {
            $query->where('owner', $owner);
        }
        if ($city = $request->query('city')) {
            $query->where('metrics->city', $city);
        }
        if ($q = $request->query('q')) {
            $query->where('name', 'like', "%{$q}%");
        }
        if ($conf = $request->query('confidence')) {
            $query->whereRaw("lower(metrics->>'confidence') like ?", [strtolower($conf).'%']);
        }

        $accounts = $query->orderByDesc('score')->orderBy('name')->get();
        $byStage = collect(CrmAccount::COMMUNITY_STAGES)
            ->mapWithKeys(fn (string $s): array => [$s => collect()])
            ->merge($accounts->groupBy(fn (CrmAccount $a): string => $a->currentStage()));

        $cityRows = CrmAccount::query()->where('type', 'community')
            ->whereNotNull('metrics->city')
            ->selectRaw("metrics->>'city' as city, count(*) as n")
            ->groupByRaw("metrics->>'city'")->orderByDesc('n')->get();

        return view('admin.crm.board', [
            'stages' => CrmAccount::COMMUNITY_STAGES,
            'forward' => CrmAccount::COMMUNITY_FORWARD_STAGES,
            'byStage' => $byStage,
            'total' => $accounts->count(),
            'cities' => $cityRows->pluck('city'),
            'owners' => CrmAccount::query()->where('type', 'community')->whereNotNull('owner')->distinct()->pluck('owner'),
            'filters' => $request->only(['owner', 'city', 'q', 'confidence']),
        ]);
    }

    public function saveColumns(Request $request): RedirectResponse
    {
        $type = in_array($request->input('type'), CrmAccount::TYPES, true) ? $request->input('type') : 'business';
        $allowed = array_keys($this->columnsFor($type));
        // Keep the SUBMITTED order (that's how the picker persists a reorder), not
        // the catalog order; drop unknowns and de-duplicate.
        $cols = array_values(array_unique(array_filter(
            (array) $request->input('columns', []),
            static fn ($key): bool => is_string($key) && in_array($key, $allowed, true),
        )));
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

    /** Lead detail: contact, pipeline stage, the activity timeline, first-touch draft. */
    public function show(CrmAccount $account, CrmPipelineService $pipeline): View
    {
        $account->load('activities');

        return view('admin.crm.show', [
            'account' => $account,
            'firstTouch' => $account->type === 'community' ? $pipeline->firstTouchMessage($account) : null,
        ]);
    }

    /** Log that the first-touch message was sent and move Target → Contacted. */
    public function firstTouch(CrmAccount $account, CrmPipelineService $pipeline): RedirectResponse
    {
        $actor = auth('admin')->user()?->name;
        $pipeline->log($account, 'first_touch', 'First-touch message sent.', $actor);
        if ($account->currentStage() === 'Target') {
            $pipeline->moveStage($account, 'Contacted', $actor);
        }

        return redirect()->route('admin.crm.show', $account)->with('status', 'First-touch logged.');
    }

    /** Stream the filtered community set as CSV. */
    public function export(Request $request): StreamedResponse
    {
        $query = CrmAccount::query()->where('type', 'community');
        if ($owner = $request->query('owner')) {
            $query->where('owner', $owner);
        }
        if ($city = $request->query('city')) {
            $query->where('metrics->city', $city);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($q = $request->query('q')) {
            $query->where('name', 'like', "%{$q}%");
        }

        $accounts = $query->orderByDesc('score')->orderBy('name')->get();

        return response()->streamDownload(function () use ($accounts): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'City', 'Type', 'Audience', 'Confidence', 'Fit', 'Stage', 'Owner', 'Last activity', 'Instagram', 'Evidence', 'Collabs']);
            foreach ($accounts as $a) {
                $m = $a->metrics ?? [];
                fputcsv($out, [
                    $a->name, $m['city'] ?? '', $m['classification'] ?? '', $m['audience'] ?? '',
                    $m['confidence'] ?? '', $a->score, $a->currentStage(), $a->owner ?? '',
                    $a->last_activity_at?->format('Y-m-d') ?? '',
                    $a->instagram_handle ?? ($m['handle'] ?? ''),
                    $m['evidence_url'] ?? '', $m['collab_businesses'] ?? ($m['collabs'] ?? ''),
                ]);
            }
            fclose($out);
        }, 'kolabing-communities-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Move a lead to a pipeline stage (forward, backward, or the Rejected lane).
     * Answers JSON for the drag-and-drop board, a redirect for the detail page.
     */
    public function moveStage(Request $request, CrmAccount $account, CrmPipelineService $pipeline): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'stage' => ['required', Rule::in(CrmAccount::COMMUNITY_STAGES)],
        ]);

        $pipeline->moveStage($account, $data['stage'], auth('admin')->user()?->name);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'id' => $account->id, 'stage' => $data['stage']]);
        }

        return redirect()
            ->route('admin.crm.show', $account)
            ->with('status', "Stage updated to {$data['stage']}.");
    }

    /** Log a free-text note onto the lead's timeline. */
    public function addActivity(Request $request, CrmAccount $account, CrmPipelineService $pipeline): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $pipeline->logNote($account, $data['body'], auth('admin')->user()?->name);

        return redirect()
            ->route('admin.crm.show', $account)
            ->with('status', 'Note added.');
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
        if ($type === 'community') {
            foreach (['events_weekly', 'strong_attendance', 'active_ig', 'engaged_founder', 'good_vibes'] as $f) {
                $metrics[$f] = ! empty($metrics[$f]);
            }
        }

        // Preserve seeded/verification metadata (city, classification, audience_count, evidence_url, …):
        // merge the posted metrics ONTO the existing ones instead of replacing the whole JSON. Without
        // this, editing a verified community and saving would wipe its Challenge-A verification data.
        if ($account !== null) {
            $metrics = array_merge($account->metrics ?? [], $metrics);
        }
        $data['metrics'] = $metrics;

        return $data;
    }
}
