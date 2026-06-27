<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot backing the app's "Saved Kolabs" feature: one row per
     * (profile, kolab) a profile has bookmarked. A pure pivot — composite
     * primary key, no UUID surrogate id (so belongsToMany attach/sync never
     * trips the NOT NULL-id pitfall).
     */
    public function up(): void
    {
        Schema::create('saved_kolabs', function (Blueprint $table): void {
            $table->foreignUuid('profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->foreignUuid('kolab_id')
                ->constrained('kolabs')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['profile_id', 'kolab_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_kolabs');
    }
};
