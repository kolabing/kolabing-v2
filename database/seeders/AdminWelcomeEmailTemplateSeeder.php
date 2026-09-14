<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AdminWelcomeEmailTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds the 3 default locales (en/es/ca) for the admin-editable quick-add welcome
 * email. Idempotent upsert by locale — safe to re-run, and re-running never
 * overwrites a maintainer's live edits once the row already exists, since
 * updateOrCreate() here only fires on first run per locale (guarded by the row's
 * own absence check below). Daniel 2026-09-14: "make the template editable in admin
 * dashboard in all languages (add/edit/remove languages)" — this seeder is only the
 * starting content, not a source of truth the admin panel defers to afterward.
 */
class AdminWelcomeEmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $template) {
            AdminWelcomeEmailTemplate::query()->firstOrCreate(
                ['locale' => $template['locale']],
                $template,
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            [
                'locale' => 'en',
                'label' => 'English',
                'subject' => 'Your Kolabing listing is live — set your password',
                'intro_markdown' => <<<'MD'
                    # Welcome to Kolabing, {{name}}!

                    We've listed you on Kolabing so {{who}} can already find you.

                    ## What you get

                    - A public profile {{who}} can discover and reach out to
                    - Direct collaboration requests from real local communities and businesses
                    - Full control over your listing — photos, description, availability
                    - No cost to be listed; you decide if and when you want to do more
                    MD,
                'next_steps_markdown' => <<<'MD'
                    ## What happens next

                    Set your password below to manage your profile, review requests, and reply.
                    MD,
                'footer_markdown' => <<<'MD'
                    This link is only valid for a limited time — if it expires, you can always request a new one from the sign-in screen.

                    Thanks,
                    Kolabing
                    MD,
                'is_active' => true,
            ],
            [
                'locale' => 'es',
                'label' => 'Español',
                'subject' => 'Tu perfil de Kolabing ya está activo — crea tu contraseña',
                'intro_markdown' => <<<'MD'
                    # ¡Bienvenido a Kolabing, {{name}}!

                    Ya te hemos listado en Kolabing para que {{who}} puedan encontrarte.

                    ## Qué obtienes

                    - Un perfil público donde {{who}} pueden descubrirte y escribirte
                    - Solicitudes de colaboración directas de comunidades y negocios reales de tu zona
                    - Control total sobre tu perfil — fotos, descripción, disponibilidad
                    - Aparecer no tiene costo; tú decides si y cuándo quieres ir más allá
                    MD,
                'next_steps_markdown' => <<<'MD'
                    ## Qué sigue

                    Crea tu contraseña abajo para gestionar tu perfil, revisar solicitudes y responder.
                    MD,
                'footer_markdown' => <<<'MD'
                    Este enlace es válido solo por tiempo limitado — si caduca, siempre puedes pedir uno nuevo desde la pantalla de inicio de sesión.

                    Gracias,
                    Kolabing
                    MD,
                'is_active' => true,
            ],
            [
                'locale' => 'ca',
                'label' => 'Català',
                'subject' => 'El teu perfil de Kolabing ja és actiu — crea la teva contrasenya',
                'intro_markdown' => <<<'MD'
                    # Benvingut a Kolabing, {{name}}!

                    Ja t'hem llistat a Kolabing perquè {{who}} et puguin trobar.

                    ## Què obtens

                    - Un perfil públic on {{who}} et poden descobrir i escriure't
                    - Sol·licituds de col·laboració directes de comunitats i negocis reals de la teva zona
                    - Control total sobre el teu perfil — fotos, descripció, disponibilitat
                    - Aparèixer no té cap cost; tu decideixes si i quan vols anar més enllà
                    MD,
                'next_steps_markdown' => <<<'MD'
                    ## Què passa ara

                    Crea la teva contrasenya a sota per gestionar el teu perfil, revisar sol·licituds i respondre.
                    MD,
                'footer_markdown' => <<<'MD'
                    Aquest enllaç només és vàlid durant un temps limitat — si caduca, sempre pots demanar-ne un de nou des de la pantalla d'inici de sessió.

                    Gràcies,
                    Kolabing
                    MD,
                'is_active' => true,
            ],
        ];
    }
}
