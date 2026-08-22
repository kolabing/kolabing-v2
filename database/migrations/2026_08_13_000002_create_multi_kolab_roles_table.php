<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('multi_kolab_roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('multi_kolab_event_id')
                ->constrained('multi_kolab_events')
                ->cascadeOnDelete();

            $table->string('status', 20)->default('open');

            $table->string('title', 255);
            $table->string('eligible_account_type', 20);

            $table->unsignedInteger('positions_needed')->default(1);
            $table->unsignedInteger('positions_filled')->default(0);
            $table->boolean('required')->default(true);

            $table->text('need')->nullable();
            $table->text('receive')->nullable();
            $table->string('compensation_type', 30)->nullable();
            $table->text('requirements')->nullable();
            $table->text('details')->nullable();

            $table->timestamps();

            $table->index(['multi_kolab_event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multi_kolab_roles');
    }
};
