<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_welcome_email_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Free-text locale code (not a fixed enum) — Daniel 2026-09-14: "add/edit/remove
            // languages" from the admin dashboard, so the language set itself must be
            // editable data, not a hardcoded list.
            $table->string('locale', 10)->unique();
            $table->string('label', 100);
            $table->string('subject', 255);
            // Three editable markdown chunks, split around the two FIXED action buttons
            // (view listing / set password). The buttons themselves — and the URLs they
            // point to, including the one-time reset token — are never part of editable
            // content, deliberately: a maintainer can rewrite every word of the copy but
            // can't redirect the CTAs anywhere else. See WelcomeEmailTemplateRenderer.
            $table->longText('intro_markdown');
            $table->longText('next_steps_markdown');
            $table->longText('footer_markdown');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_welcome_email_templates');
    }
};
