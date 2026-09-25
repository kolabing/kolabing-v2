<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-off data backfill, not a schema change. This specific listing
 * (profile_id 01a0a313-293a-737c-ae51-a8302dce8f78, the CDMX specialty-coffee
 * brand "Exploradores de Café", https://www.instagram.com/exploradoresdecafe/,
 * ~33K followers, ranked in the World's 100 Best Coffee Shops) had its name/about/
 * business_type/opening_hours set correctly via the admin UI twice tonight
 * (2026-09-15) and both times reverted to blank/original before the change could be
 * verified stable -- root cause unconfirmed (leading theory: a stale browser tab on
 * the edit form resubmitting old field values, since the admin form has no
 * dirty-check and upsertDetailProfile() treats an omitted field as "clear it").
 *
 * A migration is deterministic and runs on every deploy via the app's own
 * `php artisan migrate --force` deploy step -- no browser automation, no stale-tab
 * risk, no dependency on anyone tapping "arm" on a live job. Scoped with
 * `WHERE (about IS NULL OR about = '') AND business_type IS NULL` so it backfills
 * ONLY if the fields are still genuinely empty -- if a maintainer manually edits
 * this listing again before this runs, their edit is NOT overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('business_profiles')
            ->where('profile_id', '01a0a313-293a-737c-ae51-a8302dce8f78')
            ->where(function ($query): void {
                $query->whereNull('about')->orWhere('about', '');
            })
            ->whereNull('business_type')
            ->update([
                'name' => 'Exploradores de Café',
                'about' => "Exploradores de Café nace de la pasión por recorrer las montañas de México en busca de cafés extraordinarios. Trabajamos microlotes excepcionales de Chiapas, Oaxaca, Veracruz y el Estado de México, muchos comprados directamente a los productores que los cultivan.\n\nEn nuestro espacio de Santa Fe vivimos el café de principio a fin: plantas de café en exhibición, tueste en vivo, sala de cata, showroom de equipo profesional y una barra de espresso clásica con interpretaciones propias. Cada sábado abrimos catas gratuitas para quien quiera aprender a distinguir origen y proceso.\n\nBuscamos café extraordinario — y una relación justa con quienes lo cultivan.",
                'business_type' => 'cafe',
                'opening_hours' => json_encode([
                    'Monday: 7:30 AM - 2:30 PM',
                    'Tuesday: 7:30 AM - 8:30 PM',
                    'Wednesday: 7:30 AM - 8:30 PM',
                    'Thursday: 7:30 AM - 8:30 PM',
                    'Friday: 7:30 AM - 8:30 PM',
                    'Saturday: 8:30 AM - 8:30 PM',
                    'Sunday: 8:30 AM - 5:30 PM',
                ]),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Deliberately no-op: this is a one-time content backfill for a specific
        // listing, not a reversible schema change. Reverting it would just re-blank
        // real content with no upside.
    }
};
