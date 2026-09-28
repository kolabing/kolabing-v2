@php
    $monthly = (int) config('subscriptions.business.stripe.monthly.price');
    $quarterly = (int) config('subscriptions.business.stripe.three_months.price');
    $pro = (int) config('subscriptions.business.stripe.pro_monthly.price');

    $title = 'Precios para negocios';
    $description = 'Publica tu local en Kolabing gratis. Recibir solicitudes y hacer kolabs no cuesta nada. Después del listado gratis, sigue visible por €'.$monthly.' al mes o €'.$quarterly.' cada 3 meses. Las comunidades nunca pagan.';
    $canonical = route('pricing.es');
    $locale = 'es';
    $alternates = [
        ['hreflang' => 'en', 'href' => route('pricing')],
        ['hreflang' => 'es', 'href' => route('pricing.es')],
        ['hreflang' => 'ca', 'href' => route('pricing.ca')],
        ['hreflang' => 'x-default', 'href' => route('pricing')],
    ];

    $c = [
        'eyebrow' => 'Precios',
        'headline' => 'Publica gratis. Paga solo para seguir visible.',
        'intro' => 'Todos los negocios tienen un listado gratis en Explorar desde que se registran, para que las comunidades locales te encuentren y traigan a sus miembros en tus horas valle. Recibir solicitudes, aceptarlas y hacer kolabs es gratis. Las comunidades nunca pagan.',
        'free_badge' => 'Listado gratis',
        'free_price_note' => 'para empezar',
        'free_title' => 'Empieza con un listado gratis',
        'free_desc' => 'Tu listado gratis dura hasta que completes 3 kolabs o 90 días, lo que ocurra primero.',
        'free_items' => [
            'Tu local aparece en Explorar en cuanto te registras',
            'Recibe y acepta solicitudes de comunidades',
            'Haz tus kolabs y chatea con las comunidades que aceptes',
            'Tu página de perfil pública en kolabing.com',
        ],
        'free_cta' => 'Publica tu local gratis',
        'paid_title' => 'Sigue visible cuando acabe tu periodo gratis',
        'paid_desc' => 'Cuando termine tu listado gratis, elige un plan para seguir en Explorar y seguir recibiendo solicitudes.',
        'monthly_name' => 'Mensual',
        'quarterly_name' => '3 meses',
        'per_month' => '/ mes',
        'monthly_note' => 'Cobro mensual · cancela cuando quieras',
        'quarterly_note' => ':price cobrados cada 3 meses',
        'save_badge' => 'Ahorra :percent%',
        'cta' => 'Elegir este plan',
        'pro_name' => 'Venue Pro',
        'pro_badge' => 'Para hoteles',
        'pro_note' => 'Facturación mensual · solo en la web',
        'pro_desc' => 'Para hoteles y espacios que organizan eventos cada semana.',
        'pro_cta' => 'Elegir Venue Pro',
        'login' => '¿Ya tienes cuenta? Inicia sesión',
        'included_title' => 'Todos los planes de pago incluyen',
        'included' => [
            'Sigue visible en Explorar',
            'Publica más kolabs',
            'Ve nombres, perfiles y tamaño de audiencia de las comunidades',
            'Aplica tú mismo a peticiones de comunidades',
            'Cancela cuando quieras, sin permanencia',
        ],
        'communities_title' => 'Las comunidades nunca pagan',
        'communities_desc' => 'Clubes, equipos, creadores y organizadores exploran, aplican y colaboran gratis. No hay ningún plan de comunidad que comprar. Si alguien te pide pagar por organizar en Kolabing, no somos nosotros.',
        'communities_cta' => 'Crear una cuenta de comunidad gratis',
        'faq_title' => 'Preguntas',
        'final_title' => 'Aparece en Kolabing esta semana',
        'final_desc' => 'Crea tu cuenta de negocio en el navegador y tu local aparecerá gratis en Explorar. Sin instalar nada.',
    ];

    $faqs = [
        ['q' => '¿Qué es gratis?', 'a' => 'Publicar tu local, recibir y aceptar solicitudes y hacer tus kolabs. Cada negocio nuevo recibe automáticamente un listado en Explorar, que puedes editar o cerrar cuando quieras.'],
        ['q' => '¿Cuándo termina el listado gratis?', 'a' => 'Después de tu 3.er kolab completado o a los 90 días de registrarte, lo que ocurra primero. Para seguir visible, elige el plan mensual (€'.$monthly.') o el de 3 meses (€'.$quarterly.').'],
        ['q' => '¿Qué es Venue Pro?', 'a' => 'Un plan mensual de €'.$pro.' para hoteles y espacios que organizan eventos cada semana. Añade ingresos y asistencia de cada evento, tus mejores comunidades en ranking y exportación a CSV. Solo se contrata en la web.'],
        ['q' => '¿Qué pasa después de pagar?', 'a' => 'Tu plan se activa al momento y vuelves a Kolabing listo para publicar. El pago lo gestiona Stripe y nosotros nunca vemos los datos de tu tarjeta.'],
        ['q' => '¿Puedo cancelar?', 'a' => 'Sí, cuando quieras, desde el portal de facturación dentro de tu cuenta. El plan sigue activo hasta el final del periodo que ya has pagado.'],
        ['q' => '¿Las comunidades pagan algo?', 'a' => 'No. Las cuentas de comunidad son gratis para siempre: explorar, aplicar y colaborar no cuesta nada.'],
        ['q' => '¿Tenéis descuentos?', 'a' => 'Hacemos campañas de vez en cuando. Si tienes un código promocional, puedes introducirlo en la pantalla de pago de Stripe antes de pagar.'],
        ['q' => '¿Puedo usar Kolabing desde el móvil?', 'a' => 'Sí. Todo funciona en el navegador, y la app de Kolabing añade chat, notificaciones y check-in en eventos.'],
    ];
@endphp

<x-layouts.marketing-page :title="$title" :description="$description" :canonical="$canonical" :locale="$locale" :alternates="$alternates">
    <x-slot:head>
        @include('pages.partials.pricing-schema', ['description' => $description, 'canonical' => $canonical, 'locale' => $locale, 'c' => $c, 'faqs' => $faqs])
    </x-slot:head>

    @include('pages.partials.pricing-content', ['c' => $c, 'faqs' => $faqs, 'locale' => $locale])
</x-layouts.marketing-page>
