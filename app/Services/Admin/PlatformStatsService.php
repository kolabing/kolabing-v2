<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\CollaborationStatus;
use App\Enums\KolabStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserType;
use App\Models\Application;
use App\Models\BusinessSubscription;
use App\Models\ChatMessage;
use App\Models\Collaboration;
use App\Models\CollaborationReview;
use App\Models\Kolab;
use App\Models\PointLedger;
use App\Models\Profile;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class PlatformStatsService
{
    public const RANGES = ['7d', '30d', '90d', 'all'];

    /**
     * Build the full payload for the admin Stats dashboard.
     *
     * @return array<string, mixed>
     */
    public function summary(string $range = '30d'): array
    {
        $since = $this->resolveSince($range);

        return [
            'range' => $range,
            'since' => $since?->toIso8601String(),
            'audience' => $this->audience($since),
            'kolabs' => $this->kolabs($since),
            'applications' => $this->applications($since),
            'collaborations' => $this->collaborations($since),
            'quality' => $this->quality($since),
            'funnel' => $this->funnel(),
            'money' => $this->money($since),
            'activity' => $this->activity(),
        ];
    }

    private function resolveSince(string $range): ?CarbonImmutable
    {
        return match ($range) {
            '7d' => CarbonImmutable::now()->subDays(7),
            '30d' => CarbonImmutable::now()->subDays(30),
            '90d' => CarbonImmutable::now()->subDays(90),
            default => null,
        };
    }

    /**
     * Build a driver-portable SQL expression that returns the number of seconds
     * between two timestamp columns. SQLite has no EXTRACT(EPOCH FROM ...) and
     * PostgreSQL has no julianday(), so we branch per active connection.
     */
    private function secondsBetween(string $laterCol, string $earlierCol): string
    {
        $driver = (new Profile)->getConnection()->getDriverName();

        return match ($driver) {
            'sqlite' => "(julianday({$laterCol}) - julianday({$earlierCol})) * 86400",
            'mysql', 'mariadb' => "TIMESTAMPDIFF(SECOND, {$earlierCol}, {$laterCol})",
            default => "EXTRACT(EPOCH FROM ({$laterCol} - {$earlierCol}))",
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function audience(?CarbonImmutable $since): array
    {
        $byType = Profile::query()->toBase()
            ->selectRaw('user_type, count(*) as total')
            ->groupBy('user_type')
            ->pluck('total', 'user_type');

        $newInRange = Profile::query()
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->count();

        $weekly = $this->weeklyCounts(Profile::query(), $since);

        return [
            'total' => (int) $byType->sum(),
            'business' => (int) ($byType[UserType::Business->value] ?? 0),
            'community' => (int) ($byType[UserType::Community->value] ?? 0),
            'attendee' => (int) ($byType[UserType::Attendee->value] ?? 0),
            'new_in_range' => $newInRange,
            'soft_deleted' => Profile::onlyTrashed()
                ->when($since, fn ($q) => $q->where('deleted_at', '>=', $since))
                ->count(),
            'weekly_new' => $weekly,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kolabs(?CarbonImmutable $since): array
    {
        $byStatus = Kolab::query()->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byIntent = Kolab::query()->toBase()
            ->selectRaw('intent_type, count(*) as total')
            ->groupBy('intent_type')
            ->pluck('total', 'intent_type');

        $publishedDelaysSeconds = Kolab::query()
            ->whereNotNull('published_at')
            ->when($since, fn ($q) => $q->where('published_at', '>=', $since))
            ->selectRaw('AVG('.$this->secondsBetween('published_at', 'created_at').') as avg_delay')
            ->value('avg_delay');

        $weeklyPublished = $this->weeklyCounts(
            Kolab::query()->whereNotNull('published_at'),
            $since,
            'published_at',
        );

        $lifecycleService = app(KolabLifecycleService::class);
        $live = Kolab::query()
            ->select('kolabs.*')
            ->selectSub(
                Application::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('kolab_id', 'kolabs.id')
                    ->where('status', ApplicationStatus::Pending->value),
                'pending_applications_count'
            )
            ->selectSub(
                Application::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('kolab_id', 'kolabs.id')
                    ->where('status', ApplicationStatus::Accepted->value),
                'accepted_applications_count'
            )
            ->get();
        $lifecycles = $lifecycleService->summarizeMany($live);
        $lifecycleBuckets = $lifecycles->groupBy('lifecycle')->map->count();

        return [
            'total' => (int) $byStatus->sum(),
            'draft' => (int) ($byStatus[KolabStatus::Draft->value] ?? 0),
            'published' => (int) ($byStatus[KolabStatus::Published->value] ?? 0),
            'closed' => (int) ($byStatus[KolabStatus::Closed->value] ?? 0),
            'avg_time_to_publish_days' => $publishedDelaysSeconds !== null
                ? round(((float) $publishedDelaysSeconds) / 86400, 1)
                : null,
            'weekly_published' => $weeklyPublished,
            'by_intent' => $byIntent->mapWithKeys(fn ($total, $key) => [(string) $key => (int) $total])->all(),
            'lifecycle_distribution' => $lifecycleBuckets->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function applications(?CarbonImmutable $since): array
    {
        $base = Application::query()->toBase()
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since));

        $byStatus = (clone $base)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byType = (clone $base)
            ->selectRaw('applicant_profile_type, count(*) as total')
            ->groupBy('applicant_profile_type')
            ->pluck('total', 'applicant_profile_type');

        $accepted = (int) ($byStatus[ApplicationStatus::Accepted->value] ?? 0);
        $declined = (int) ($byStatus[ApplicationStatus::Declined->value] ?? 0);
        $withdrawn = (int) ($byStatus[ApplicationStatus::Withdrawn->value] ?? 0);
        $resolved = $accepted + $declined + $withdrawn;
        $acceptanceRate = $resolved > 0 ? round($accepted / $resolved * 100, 1) : null;

        // Median applications per published kolab (sqlite-safe via PHP).
        $perKolabCounts = Application::query()->toBase()
            ->selectRaw('kolab_id as canonical_kolab_id')
            ->selectRaw('count(*) as c')
            ->whereNotNull('kolab_id')
            ->groupBy('canonical_kolab_id')
            ->pluck('c')
            ->map(fn ($v) => (int) $v)
            ->sort()
            ->values();
        $medianPerKolab = $perKolabCounts->isEmpty() ? null : (int) $perKolabCounts[(int) floor($perKolabCounts->count() / 2)];

        // Average time-to-accept using new accepted_at column (Phase 2).
        $avgTimeToAcceptSeconds = Application::query()
            ->whereNotNull('accepted_at')
            ->when($since, fn ($q) => $q->where('accepted_at', '>=', $since))
            ->selectRaw('AVG('.$this->secondsBetween('accepted_at', 'created_at').') as avg_seconds')
            ->value('avg_seconds');

        return [
            'total' => (int) $byStatus->sum(),
            'pending' => (int) ($byStatus[ApplicationStatus::Pending->value] ?? 0),
            'accepted' => $accepted,
            'declined' => $declined,
            'withdrawn' => $withdrawn,
            'from_business' => (int) ($byType[UserType::Business->value] ?? 0),
            'from_community' => (int) ($byType[UserType::Community->value] ?? 0),
            'acceptance_rate_pct' => $acceptanceRate,
            'median_per_kolab' => $medianPerKolab,
            'avg_time_to_accept_hours' => $avgTimeToAcceptSeconds !== null
                ? round(((float) $avgTimeToAcceptSeconds) / 3600, 1)
                : null,
            'weekly' => $this->weeklyCounts(Application::query(), $since),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collaborations(?CarbonImmutable $since): array
    {
        $byStatus = Collaboration::query()->toBase()
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $completed = (int) ($byStatus[CollaborationStatus::Completed->value] ?? 0);
        $cancelled = (int) ($byStatus[CollaborationStatus::Cancelled->value] ?? 0);
        $resolved = $completed + $cancelled;
        $completionRate = $resolved > 0 ? round($completed / $resolved * 100, 1) : null;

        $avgTimeToCompleteSeconds = Collaboration::query()
            ->whereNotNull('completed_at')
            ->when($since, fn ($q) => $q->where('completed_at', '>=', $since))
            ->selectRaw('AVG('.$this->secondsBetween('completed_at', 'created_at').') as avg_seconds')
            ->value('avg_seconds');

        $chatPerCollab = ChatMessage::query()->toBase()
            ->select('application_id')
            ->selectRaw('count(*) as c')
            ->groupBy('application_id')
            ->pluck('c')
            ->map(fn ($v) => (int) $v)
            ->sort()
            ->values();
        $medianChat = $chatPerCollab->isEmpty() ? null : (int) $chatPerCollab[(int) floor($chatPerCollab->count() / 2)];

        // Cancellation reasons (top 5).
        $cancellationReasons = Collaboration::query()->toBase()
            ->whereNotNull('cancellation_reason')
            ->when($since, fn ($q) => $q->where('cancelled_at', '>=', $since))
            ->selectRaw('cancellation_reason, count(*) as total')
            ->groupBy('cancellation_reason')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row) => ['reason' => $row->cancellation_reason, 'count' => (int) $row->total])
            ->all();

        return [
            'total' => (int) $byStatus->sum(),
            'scheduled' => (int) ($byStatus[CollaborationStatus::Scheduled->value] ?? 0),
            'active' => (int) ($byStatus[CollaborationStatus::Active->value] ?? 0),
            'completed' => $completed,
            'cancelled' => $cancelled,
            'completion_rate_pct' => $completionRate,
            'avg_time_to_complete_days' => $avgTimeToCompleteSeconds !== null
                ? round(((float) $avgTimeToCompleteSeconds) / 86400, 1)
                : null,
            'median_chat_messages' => $medianChat,
            'weekly_completed' => $this->weeklyCounts(
                Collaboration::query()->whereNotNull('completed_at'),
                $since,
                'completed_at',
            ),
            'top_cancellation_reasons' => $cancellationReasons,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function quality(?CarbonImmutable $since): array
    {
        $avgRating = CollaborationReview::query()
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->avg('rating');

        $perSide = CollaborationReview::query()->toBase()
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->selectRaw('reviewer_role, AVG(rating) as avg_rating, count(*) as total')
            ->groupBy('reviewer_role')
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->reviewer_role => [
                'avg' => $r->avg_rating !== null ? round((float) $r->avg_rating, 1) : null,
                'count' => (int) $r->total,
            ]])
            ->all();

        // Completed collabs where both sides have reviewed.
        $completed = Collaboration::query()->where('status', CollaborationStatus::Completed->value);
        $totalCompleted = (clone $completed)->count();
        $bothReviewed = (clone $completed)
            ->whereHas('reviews', fn ($q) => $q->where('reviewer_role', 'business'))
            ->whereHas('reviews', fn ($q) => $q->where('reviewer_role', 'community'))
            ->count();

        $weekly = $this->weeklyCounts(CollaborationReview::query(), $since);

        $eventMix = PointLedger::query()->toBase()
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->selectRaw('event_type, count(*) as total')
            ->groupBy('event_type')
            ->orderByDesc('total')
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->event_type => (int) $r->total])
            ->all();

        return [
            'avg_rating' => $avgRating !== null ? round((float) $avgRating, 2) : null,
            'per_side' => $perSide,
            'both_sides_reviewed_pct' => $totalCompleted > 0
                ? round($bothReviewed / $totalCompleted * 100, 1)
                : null,
            'weekly_reviews' => $weekly,
            'point_event_mix' => $eventMix,
        ];
    }

    /**
     * The funnel asked for: % of users at each lifecycle step, split by user_type.
     * Lifetime values — independent of the date range.
     *
     * @return array<string, mixed>
     */
    private function funnel(): array
    {
        $build = function (string $userType): array {
            $created = Profile::query()
                ->where('user_type', $userType)
                ->count();

            $onboardedRelation = $userType === UserType::Business->value ? 'businessProfile' : 'communityProfile';
            $onboarded = Profile::query()
                ->where('user_type', $userType)
                ->whereHas($onboardedRelation)
                ->count();

            $publishedKolab = $userType === UserType::Business->value
                ? Profile::query()
                    ->where('user_type', $userType)
                    ->whereIn('id', Kolab::query()
                        ->whereNotNull('published_at')
                        ->select('creator_profile_id'))
                    ->count()
                : null;

            $applied = Profile::query()
                ->where('user_type', $userType)
                ->whereIn('id', Application::query()->select('applicant_profile_id'))
                ->count();

            $accepted = Profile::query()
                ->where('user_type', $userType)
                ->whereIn('id', Application::query()
                    ->where('status', ApplicationStatus::Accepted->value)
                    ->select('applicant_profile_id'))
                ->count();

            $collaborated = Profile::query()
                ->where('user_type', $userType)
                ->where(function ($q): void {
                    $q->whereIn('id', Collaboration::query()->select('creator_profile_id'))
                        ->orWhereIn('id', Collaboration::query()->select('applicant_profile_id'));
                })
                ->count();

            $completed = Profile::query()
                ->where('user_type', $userType)
                ->where(function ($q): void {
                    $completedCollabs = Collaboration::query()->where('status', CollaborationStatus::Completed->value);
                    $q->whereIn('id', (clone $completedCollabs)->select('creator_profile_id'))
                        ->orWhereIn('id', (clone $completedCollabs)->select('applicant_profile_id'));
                })
                ->count();

            $reviewed = Profile::query()
                ->where('user_type', $userType)
                ->whereIn('id', CollaborationReview::query()->select('reviewer_profile_id'))
                ->count();

            $pct = fn (int $n) => $created > 0 ? round($n / $created * 100, 1) : null;

            return [
                'created' => ['n' => $created, 'pct' => 100.0],
                'onboarded' => ['n' => $onboarded, 'pct' => $pct($onboarded)],
                'published_kolab' => $publishedKolab === null
                    ? null
                    : ['n' => $publishedKolab, 'pct' => $pct($publishedKolab)],
                'applied' => ['n' => $applied, 'pct' => $pct($applied)],
                'accepted' => ['n' => $accepted, 'pct' => $pct($accepted)],
                'collaborated' => ['n' => $collaborated, 'pct' => $pct($collaborated)],
                'completed' => ['n' => $completed, 'pct' => $pct($completed)],
                'reviewed' => ['n' => $reviewed, 'pct' => $pct($reviewed)],
            ];
        };

        return [
            'business' => $build(UserType::Business->value),
            'community' => $build(UserType::Community->value),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function money(?CarbonImmutable $since): array
    {
        $active = BusinessSubscription::query()->toBase()
            ->where('status', SubscriptionStatus::Active->value)
            ->selectRaw('source, count(*) as total')
            ->groupBy('source')
            ->pluck('total', 'source');

        $totalBusinesses = Profile::query()->where('user_type', UserType::Business->value)->count();
        $activeTotal = (int) $active->sum();

        $weeklyActivations = $this->weeklyCounts(
            BusinessSubscription::query()->where('status', SubscriptionStatus::Active->value),
            $since,
            'updated_at',
        );

        return [
            'active_total' => $activeTotal,
            'active_by_source' => $active->mapWithKeys(fn ($n, $s) => [(string) $s => (int) $n])->all(),
            'paid_penetration_pct' => $totalBusinesses > 0
                ? round($activeTotal / $totalBusinesses * 100, 1)
                : null,
            'weekly_activations' => $weeklyActivations,
        ];
    }

    /**
     * Engagement: DAU / WAU / MAU using the new last_active_at column.
     *
     * @return array<string, int>
     */
    private function activity(): array
    {
        $now = CarbonImmutable::now();

        $count = fn (int $days) => Profile::query()
            ->where('last_active_at', '>=', $now->subDays($days))
            ->count();

        return [
            'dau' => $count(1),
            'wau' => $count(7),
            'mau' => $count(30),
        ];
    }

    /**
     * Weekly count rolled by week (ISO).
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return array<int, array{week: string, count: int}>
     */
    private function weeklyCounts(Builder $query, ?CarbonImmutable $since, string $column = 'created_at'): array
    {
        $rows = $query->toBase()
            ->when($since, fn ($q) => $q->where($column, '>=', $since))
            ->whereNotNull($column)
            ->orderBy($column)
            ->pluck($column);

        $buckets = [];
        foreach ($rows as $value) {
            $week = CarbonImmutable::parse((string) $value)->startOfWeek()->toDateString();
            $buckets[$week] = ($buckets[$week] ?? 0) + 1;
        }

        $out = [];
        foreach ($buckets as $week => $count) {
            $out[] = ['week' => $week, 'count' => $count];
        }

        return $out;
    }
}
