<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kolabs', function (Blueprint $table): void {
            // Nullable — an ordinary Kolab never sets these. Only a Kolab
            // created by MultiKolabRoleApplication acceptance (Task 6) links
            // back to its parent event/role. restrictOnDelete protects the
            // canonical acceptance record: a parent event/role cannot be
            // hard-deleted out from under a linked child Kolab.
            $table->foreignUuid('multi_kolab_event_id')
                ->nullable()
                ->constrained('multi_kolab_events')
                ->restrictOnDelete();
            $table->foreignUuid('multi_kolab_role_id')
                ->nullable()
                ->constrained('multi_kolab_roles')
                ->restrictOnDelete();

            $table->index('multi_kolab_event_id');
            $table->index('multi_kolab_role_id');
        });
    }

    public function down(): void
    {
        Schema::table('kolabs', function (Blueprint $table): void {
            $table->dropIndex(['multi_kolab_event_id']);
            $table->dropIndex(['multi_kolab_role_id']);
        });

        Schema::table('kolabs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('multi_kolab_event_id');
            $table->dropConstrainedForeignId('multi_kolab_role_id');
        });
    }
};
