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
            $table->boolean('public_comment_visible')->default(true)->after('public_comment');
        });
    }

    public function down(): void
    {
        Schema::table('collaboration_reviews', function (Blueprint $table): void {
            $table->dropColumn('public_comment_visible');
        });
    }
};
