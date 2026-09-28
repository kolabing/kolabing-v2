<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\KolabStatus;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Hand a profile's open kolabs to another profile of the same kind.
 *
 * Case that prompted it (bug report 2026-09-28, item 3): LabTwentyTwo's only
 * Published kolab belongs to a profile that was deleted afterwards, while a new,
 * live LabTwentyTwo profile exists (email reuse is allowed since PR #297). The
 * kolab is invisible in Explore (deleted owner) and the live account cannot edit
 * it. Moving it is one column, but on prod it deserves a dry run and guard rails
 * rather than a hand-typed UPDATE.
 *
 *   php artisan kolabing:move-kolabs --from=<old profile id> --to=<live profile id>           # dry run
 *   php artisan kolabing:move-kolabs --from=<old profile id> --to=<live profile id> --apply   # write
 *
 * Only Draft and Published kolabs move. Closed ones are history and stay with
 * the old owner. Kolabs that belong to a multi-kolab event stay too: they are
 * owned through the event, and moving one alone would split it. Re-running after
 * --apply finds nothing left to move, so the command is idempotent.
 */
class MoveKolabs extends Command
{
    protected $signature = 'kolabing:move-kolabs
        {--from= : Profile id that owns the kolabs now (may be soft-deleted)}
        {--to= : Live profile id to move them to}
        {--apply : Write the changes (without this flag the command only reports)}';

    protected $description = 'Move the Draft/Published kolabs of one profile (deleted or not) to another live profile of the same type. Dry run unless --apply.';

    public function handle(): int
    {
        $fromId = (string) $this->option('from');
        $toId = (string) $this->option('to');
        $apply = (bool) $this->option('apply');

        if ($fromId === '' || $toId === '') {
            $this->error('Both --from and --to are required.');

            return self::FAILURE;
        }

        if ($fromId === $toId) {
            $this->error('--from and --to are the same profile.');

            return self::FAILURE;
        }

        $from = Profile::withTrashed()->find($fromId);
        if ($from === null) {
            $this->error("No profile with id {$fromId} (--from).");

            return self::FAILURE;
        }

        $to = Profile::withTrashed()->find($toId);
        if ($to === null) {
            $this->error("No profile with id {$toId} (--to).");

            return self::FAILURE;
        }

        if ($to->trashed()) {
            $this->error("Refusing: --to profile {$to->id} is deleted. Restore it first or pick the live account.");

            return self::FAILURE;
        }

        if (! $to->is_active) {
            $this->error("Refusing: --to profile {$to->id} is switched off (inactive). Activate it first.");

            return self::FAILURE;
        }

        if ($from->user_type !== $to->user_type) {
            $this->error("Refusing: --from is a {$from->user_type->value} and --to is a {$to->user_type->value}. Kolabs only move between profiles of the same type.");

            return self::FAILURE;
        }

        $this->info($apply ? 'Moving kolabs…' : 'Dry run — nothing will be written. Pass --apply to commit.');
        $this->line('  from: '.$this->describe($from));
        $this->line('  to:   '.$this->describe($to));
        $this->newLine();

        $kolabs = Kolab::query()
            ->where('creator_profile_id', $from->id)
            ->withCount(['applications', 'collaborations'])
            ->orderBy('created_at')
            ->get();

        if ($kolabs->isEmpty()) {
            $this->info('The --from profile owns no kolabs. Nothing to move.');

            return self::SUCCESS;
        }

        $toMove = [];

        foreach ($kolabs as $kolab) {
            $movable = in_array($kolab->status, [KolabStatus::Draft, KolabStatus::Published], true)
                && $kolab->multi_kolab_event_id === null;

            $reason = match (true) {
                $movable => $apply ? 'MOVED' : 'WILL MOVE',
                $kolab->multi_kolab_event_id !== null => 'stays (part of a multi-kolab event)',
                default => 'stays ('.$kolab->status->value.')',
            };

            $this->line(sprintf(
                '  %s  %-9s  %s  [applications: %d, collaborations: %d]  → %s',
                $kolab->id,
                $kolab->status->value,
                $kolab->title ?? '(untitled)',
                $kolab->applications_count,
                $kolab->collaborations_count,
                $reason,
            ));

            if ($movable) {
                $toMove[] = $kolab->id;
            }
        }

        if ($toMove !== [] && $kolabs->whereIn('id', $toMove)->sum('collaborations_count') > 0) {
            $this->newLine();
            $this->warn('Some of these kolabs have collaborations. Those rows keep the old profile as creator_profile_id; only the kolab changes owner.');
        }

        if ($apply && $toMove !== []) {
            DB::transaction(function () use ($toMove, $from, $to): void {
                Kolab::query()
                    ->whereIn('id', $toMove)
                    ->where('creator_profile_id', $from->id)
                    ->update(['creator_profile_id' => $to->id, 'updated_at' => now()]);
            });
        }

        $this->newLine();
        $count = count($toMove);
        $this->info(match (true) {
            $count === 0 => 'Done. No Draft or Published kolabs to move.',
            $apply => "Done. {$count} kolab(s) moved.",
            default => "Done. {$count} kolab(s) would be moved.",
        });

        return self::SUCCESS;
    }

    private function describe(Profile $profile): string
    {
        $name = $profile->businessProfile()->withoutGlobalScopes()->value('name')
            ?? $profile->communityProfile()->withoutGlobalScopes()->value('name')
            ?? '(no name)';

        $state = match (true) {
            $profile->trashed() => 'DELETED '.$profile->deleted_at?->toDateTimeString(),
            ! $profile->is_active => 'inactive',
            default => 'live',
        };

        return sprintf('%s  %s <%s>  %s, %s', $profile->id, $name, $profile->email, $profile->user_type->value, $state);
    }
}
