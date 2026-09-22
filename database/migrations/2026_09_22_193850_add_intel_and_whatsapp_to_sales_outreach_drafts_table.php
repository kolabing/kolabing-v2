<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The pitch gets research, an angle, and a WhatsApp message (BE-NF-67).
 *
 * `intel` stores exactly what was gathered about the pair at generation time —
 * the Google rating and review count we already held, what their own website said,
 * the weather outlook for their venue. It is kept rather than recomputed because a
 * pitch that claims "your quietest shift is Tuesday morning" has to remain
 * explainable next month, when their hours have changed and the website has been
 * redesigned. Without it, nobody can ever answer "where did that claim come from?".
 *
 * `angle` is which opening the model chose. Stored so the maintainer can see it
 * without re-reading the copy, and so it is possible to learn which angles land.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_outreach_drafts', function (Blueprint $table): void {
            $table->jsonb('intel')->nullable()->after('kolab_ideas');
            $table->string('angle', 64)->nullable()->after('intel');
            $table->text('whatsapp_message')->nullable()->after('body_markdown');
        });
    }

    public function down(): void
    {
        Schema::table('sales_outreach_drafts', function (Blueprint $table): void {
            $table->dropColumn(['intel', 'angle', 'whatsapp_message']);
        });
    }
};
