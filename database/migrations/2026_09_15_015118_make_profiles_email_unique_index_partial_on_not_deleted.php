<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `profiles.email` had a blanket unique index, but `profiles` is soft-deleted
 * (SoftDeletes). The two don't mix: once a profile is deleted, its email is
 * permanently unusable forever -- the DB itself refuses a second row with that
 * email even after the app-level validation is fixed to allow it (Rule::unique
 * does not apply the SoftDeletes scope on its own, and even scoping it correctly
 * on the request-validation side doesn't stop the raw Postgres unique index from
 * throwing on the actual INSERT). Caught live 2026-09-14/15: several delete
 * attempts on Exploradores de Café left the profile soft-deleted, silently
 * blocking every recreate attempt afterward with no clear error to the maintainer.
 *
 * Fix at the source: a partial unique index scoped to `deleted_at IS NULL`, so
 * only LIVE rows are constrained. Supported identically on Postgres (prod) and
 * SQLite (this repo's test driver) -- same WHERE-clause syntax on CREATE UNIQUE
 * INDEX on both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropUnique('profiles_email_unique');
        });

        DB::statement('create unique index profiles_email_unique on profiles (email) where deleted_at is null');
    }

    public function down(): void
    {
        DB::statement('drop index profiles_email_unique');

        Schema::table('profiles', function (Blueprint $table) {
            $table->unique('email');
        });
    }
};
