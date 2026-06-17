<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\IntentType;
use App\Enums\KolabStatus;
use App\Enums\OfferStatus;
use App\Enums\UserType;
use App\Models\Application;
use App\Models\CollabOpportunity;
use App\Models\Collaboration;
use App\Models\Kolab;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateLegacyOpportunitiesToKolabs extends Command
{
    protected $signature = 'kolabs:migrate-legacy-opportunities
        {--dry-run : Report what would be migrated without writing}';

    protected $description = 'Migrate legacy collab_opportunities into canonical kolabs and backfill kolab links.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $legacyRows = CollabOpportunity::query()
            ->with('creatorProfile')
            ->orderBy('created_at')
            ->get();

        $stats = [
            'scanned' => $legacyRows->count(),
            'created' => 0,
            'updated' => 0,
            'applications_linked' => 0,
            'collaborations_linked' => 0,
        ];

        if ($dryRun) {
            $stats['created'] = $legacyRows
                ->reject(fn (CollabOpportunity $opportunity): bool => Kolab::query()->whereKey($opportunity->id)->exists())
                ->count();
            $stats['updated'] = $stats['scanned'] - $stats['created'];
            $stats['applications_linked'] = Application::query()
                ->whereNull('kolab_id')
                ->whereNotNull('collab_opportunity_id')
                ->count();
            $stats['collaborations_linked'] = Collaboration::query()
                ->whereNull('kolab_id')
                ->whereNotNull('collab_opportunity_id')
                ->count();

            $this->printStats($stats, true);

            return self::SUCCESS;
        }

        DB::transaction(function () use ($legacyRows, &$stats): void {
            foreach ($legacyRows as $legacy) {
                $kolab = Kolab::query()->find($legacy->id);
                $wasExisting = $kolab !== null;

                if ($kolab === null) {
                    $kolab = new Kolab;
                    $kolab->id = $legacy->id;
                    $kolab->exists = false;
                }

                $kolab->forceFill($this->mapLegacyOpportunity($legacy));
                $kolab->save();

                $wasExisting ? $stats['updated']++ : $stats['created']++;

                $stats['applications_linked'] += Application::query()
                    ->where('collab_opportunity_id', $legacy->id)
                    ->whereNull('kolab_id')
                    ->update(['kolab_id' => $legacy->id]);

                $stats['collaborations_linked'] += Collaboration::query()
                    ->where('collab_opportunity_id', $legacy->id)
                    ->whereNull('kolab_id')
                    ->update(['kolab_id' => $legacy->id]);
            }

            $stats['collaborations_linked'] += Collaboration::query()
                ->whereNull('kolab_id')
                ->whereHas('application', fn ($query) => $query->whereNotNull('kolab_id'))
                ->update([
                    'kolab_id' => DB::raw('(select applications.kolab_id from applications where applications.id = collaborations.application_id)'),
                ]);
        });

        $this->printStats($stats, false);

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLegacyOpportunity(CollabOpportunity $legacy): array
    {
        $creatorType = $legacy->creator_profile_type;
        $intentType = $creatorType === UserType::Community
            ? IntentType::CommunitySeeking
            : IntentType::VenuePromotion;

        return [
            'creator_profile_id' => $legacy->creator_profile_id,
            'recipient_community_id' => $legacy->recipient_community_id,
            'intent_type' => $intentType,
            'status' => $this->mapStatus($legacy->status),
            'title' => $legacy->title,
            'description' => $legacy->description ?? $legacy->title,
            'offer_headline' => $intentType === IntentType::CommunitySeeking ? null : $legacy->offer_headline,
            'base_offer' => $intentType === IntentType::CommunitySeeking ? null : $legacy->base_offer,
            'negotiation_triggers' => $intentType === IntentType::CommunitySeeking ? [] : ($legacy->negotiation_triggers ?? []),
            'preferred_city' => $legacy->preferred_city ?: 'Unknown',
            'area' => null,
            'media' => $this->mapMedia($legacy),
            'availability_mode' => $legacy->availability_mode,
            'availability_start' => $legacy->availability_start,
            'availability_end' => $legacy->availability_end,
            'selected_time' => $legacy->selected_time,
            'recurring_days' => $legacy->recurring_days,
            'needs' => $intentType === IntentType::CommunitySeeking ? $legacy->business_offer : null,
            'community_types' => $intentType === IntentType::CommunitySeeking ? $legacy->categories : null,
            'community_size' => null,
            'typical_attendance' => null,
            'offers_in_return' => $intentType === IntentType::CommunitySeeking ? $legacy->community_deliverables : null,
            'venue_preference' => $this->mapVenuePreference($legacy),
            'venue_name' => null,
            'venue_type' => null,
            'capacity' => null,
            'venue_address' => $legacy->address,
            'product_name' => null,
            'product_type' => null,
            'offering' => $intentType !== IntentType::CommunitySeeking ? $legacy->business_offer : null,
            'seeking_communities' => $intentType !== IntentType::CommunitySeeking ? ['types' => $legacy->categories ?? []] : null,
            'min_community_size' => null,
            'expects' => $intentType !== IntentType::CommunitySeeking ? $legacy->community_deliverables : null,
            'past_events' => null,
            'published_at' => $legacy->published_at,
            'created_at' => $legacy->created_at,
            'updated_at' => $legacy->updated_at,
        ];
    }

    private function mapStatus(OfferStatus $status): KolabStatus
    {
        return match ($status) {
            OfferStatus::Draft => KolabStatus::Draft,
            OfferStatus::Published => KolabStatus::Published,
            OfferStatus::Closed, OfferStatus::Completed => KolabStatus::Closed,
        };
    }

    /**
     * @return array<int, array{url: string, type: string}>|null
     */
    private function mapMedia(CollabOpportunity $legacy): ?array
    {
        if (! is_string($legacy->offer_photo) || $legacy->offer_photo === '') {
            return null;
        }

        return [
            [
                'url' => $legacy->offer_photo,
                'type' => 'image',
            ],
        ];
    }

    private function mapVenuePreference(CollabOpportunity $legacy): ?string
    {
        return match ($legacy->venue_mode) {
            'business_provides', 'has_venue' => 'business_provides',
            'community_provides', 'needs_venue' => 'community_provides',
            'no_venue' => 'no_venue',
            default => null,
        };
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function printStats(array $stats, bool $dryRun): void
    {
        $prefix = $dryRun ? '[dry-run] ' : '';

        $this->line($prefix.'legacy rows scanned: '.$stats['scanned']);
        $this->line($prefix.'kolabs created: '.$stats['created']);
        $this->line($prefix.'kolabs updated: '.$stats['updated']);
        $this->line($prefix.'applications linked: '.$stats['applications_linked']);
        $this->line($prefix.'collaborations linked: '.$stats['collaborations_linked']);
    }
}
