<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One sales pitch, from generation to send (BE-NF-65).
 *
 * A row exists as soon as a maintainer generates ideas, long before anything is
 * sent — that is the point. The generated copy, the chosen Kolab idea, the cover
 * image and the revenue arithmetic are all persisted so the pitch can be reviewed,
 * edited and re-previewed without paying OpenAI again, and so that what was
 * actually sent is recoverable afterwards. A sales claim made to a real business
 * that only ever existed in a request cycle is a claim nobody can audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_outreach_drafts', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Who it goes to, and who it pitches. Both are profiles: the recipient is
            // a business, the subject a community. Cascade because a draft about a
            // deleted account is meaningless, not merely stale.
            $table->foreignUuid('business_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignUuid('community_profile_id')->constrained('profiles')->cascadeOnDelete();

            $table->string('locale', 5);

            /**
             * Every generated alternative, not just the chosen one: a maintainer who
             * picks idea 2 and later wants idea 1 should not have to regenerate, and
             * the discarded options are the cheapest signal we have about what the
             * model produces for a given pair.
             */
            $table->jsonb('kolab_ideas');
            $table->unsignedSmallInteger('selected_idea_index')->default(0);

            $table->string('cover_image_url', 2048)->nullable();

            /*
             * The revenue pitch, stored as its inputs *and* its result. Storing only
             * the total would make an old pitch unexplainable the moment the default
             * assumptions change; storing the inputs means any number sent to a
             * business can always be reconstructed.
             */
            $table->unsignedInteger('expected_attendees');
            $table->unsignedInteger('avg_spend_cents');
            $table->unsignedBigInteger('estimated_revenue_cents');

            $table->string('subject', 255);
            $table->text('body_markdown');

            $table->string('status', 16)->default('draft');
            $table->timestamp('sent_at')->nullable();

            // The maintainer who generated it. `users.id` is a bigint, unlike every
            // profile-side id here, which is a uuid. Nullable + nullOnDelete: losing
            // the admin account must not delete the record of what was sent.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('business_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_outreach_drafts');
    }
};
