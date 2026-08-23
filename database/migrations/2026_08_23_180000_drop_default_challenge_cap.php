<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * No XP cap for MVP (kolabing-app#156, §8 of the product model).
 *
 * `events.max_challenges_per_attendee` defaulted to 10, so every event ever
 * created carries a cap nobody chose. The product decision is to see what people
 * actually do before restricting it — and a limit that arrived by default is the
 * hardest kind to reason about, because nothing in the product ever said 10.
 *
 * The column STAYS. An organizer wanting a cap is a plausible future, and
 * dropping the column would throw away a working mechanism to express a
 * temporary decision. From here **null means unlimited**, and that is what new
 * events get.
 *
 * Existing rows are nulled too: they hold a 10 that came from this default, not
 * from anyone's intent, and leaving them capped would mean the decision applied
 * only to events created after today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->unsignedInteger('max_challenges_per_attendee')->nullable()->default(null)->change();
        });

        // Only the value the old default produced. An organizer who ever set
        // something else chose it, and this must not overwrite a real choice.
        DB::table('events')->where('max_challenges_per_attendee', 10)->update([
            'max_challenges_per_attendee' => null,
        ]);
    }

    public function down(): void
    {
        DB::table('events')->whereNull('max_challenges_per_attendee')->update([
            'max_challenges_per_attendee' => 10,
        ]);

        Schema::table('events', function (Blueprint $table): void {
            $table->unsignedInteger('max_challenges_per_attendee')->default(10)->change();
        });
    }
};
