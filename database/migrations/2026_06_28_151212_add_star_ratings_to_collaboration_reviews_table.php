<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collaboration_reviews', function (Blueprint $table): void {
            $table->unsignedTinyInteger('communication_rating')->nullable()->after('rating');
            $table->unsignedTinyInteger('reliability_rating')->nullable()->after('communication_rating');
            $table->unsignedTinyInteger('fit_rating')->nullable()->after('reliability_rating');
            $table->unsignedTinyInteger('value_rating')->nullable()->after('fit_rating');
            $table->unsignedTinyInteger('repeat_rating')->nullable()->after('value_rating');
            $table->text('public_comment')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('collaboration_reviews', function (Blueprint $table): void {
            $table->dropColumn([
                'communication_rating',
                'reliability_rating',
                'fit_rating',
                'value_rating',
                'repeat_rating',
                'public_comment',
            ]);
        });
    }
};
