@php
    $monthly = (int) config('subscriptions.business.stripe.monthly.price');
    $quarterly = (int) config('subscriptions.business.stripe.three_months.price');
    $pro = (int) config('subscriptions.business.stripe.pro_monthly.price');

    $title = 'Preus per a negocis';
    $description = 'Publica el teu local a Kolabing gratis. Rebre sol·licituds i fer kolabs no costa res. Després del llistat gratuït, segueix visible per €'.$monthly.' al mes o €'.$quarterly.' cada 3 mesos. Les comunitats no paguen mai.';
    $canonical = route('pricing.ca');
    $locale = 'ca';
    $alternates = [
        ['hreflang' => 'en', 'href' => route('pricing')],
        ['hreflang' => 'es', 'href' => route('pricing.es')],
        ['hreflang' => 'ca', 'href' => route('pricing.ca')],
        ['hreflang' => 'x-default', 'href' => route('pricing')],
    ];

    $c = [
        'eyebrow' => 'Preus',
        'headline' => 'Publica gratis. Paga només per seguir visible.',
        'intro' => "Tots els negocis tenen un llistat gratuït a Explora des que es registren, perquè les comunitats locals et trobin i portin els seus membres a les teves hores vall. Rebre sol·licituds, acceptar-les i fer kolabs és gratuït. Les comunitats no paguen mai.",
        'free_badge' => 'Llistat gratuït',
        'free_price_note' => 'per començar',
        'free_title' => 'Comença amb un llistat gratuït',
        'free_desc' => 'El teu llistat gratuït dura fins que completis 3 kolabs o 90 dies, el que passi primer.',
        'free_items' => [
            'El teu local apareix a Explora tan bon punt et registres',
            'Rep i accepta sol·licituds de comunitats',
            'Fes els teus kolabs i xateja amb les comunitats que acceptis',
            'La teva pàgina de perfil pública a kolabing.com',
        ],
        'free_cta' => 'Publica el teu local gratis',
        'paid_title' => 'Segueix visible quan acabi el període gratuït',
        'paid_desc' => 'Quan acabi el teu llistat gratuït, tria un pla per seguir a Explora i continuar rebent sol·licituds.',
        'monthly_name' => 'Mensual',
        'quarterly_name' => '3 mesos',
        'per_month' => '/ mes',
        'monthly_note' => 'Cobrament mensual · cancel·la quan vulguis',
        'quarterly_note' => ':price cobrats cada 3 mesos',
        'save_badge' => 'Estalvia :percent%',
        'cta' => 'Triar aquest pla',
        'pro_name' => 'Venue Pro',
        'pro_badge' => 'Per a hotels',
        'pro_note' => 'Facturació mensual · només al web',
        'pro_desc' => 'Per a hotels i espais que organitzen esdeveniments cada setmana.',
        'pro_cta' => 'Triar Venue Pro',
        'login' => 'Ja tens compte? Inicia la sessió',
        'included_title' => 'Tots els plans de pagament inclouen',
        'included' => [
            'Segueix visible a Explora',
            'Publica més kolabs',
            "Consulta noms, perfils i mida de l'audiència de les comunitats",
            'Aplica tu mateix a peticions de comunitats',
            'Cancel·la quan vulguis, sense permanència',
        ],
        'communities_title' => 'Les comunitats no paguen mai',
        'communities_desc' => 'Clubs, equips, creadors i organitzadors exploren, apliquen i col·laboren gratis. No hi ha cap pla de comunitat per comprar. Si algú et demana pagar per organitzar a Kolabing, no som nosaltres.',
        'communities_cta' => 'Crear un compte de comunitat gratis',
        'faq_title' => 'Preguntes',
        'final_title' => 'Apareix a Kolabing aquesta setmana',
        'final_desc' => 'Crea el teu compte de negoci al navegador i el teu local apareixerà gratis a Explora. Sense instal·lar res.',
    ];

    $faqs = [
        ['q' => 'Què és gratuït?', 'a' => 'Publicar el teu local, rebre i acceptar sol·licituds i fer els teus kolabs. Cada negoci nou rep automàticament un llistat a Explora, que pots editar o tancar quan vulguis.'],
        ['q' => 'Quan acaba el llistat gratuït?', 'a' => 'Després del teu 3r kolab completat o als 90 dies de registrar-te, el que passi primer. Per seguir visible, tria el pla mensual (€'.$monthly.') o el de 3 mesos (€'.$quarterly.').'],
        ['q' => 'Què és Venue Pro?', 'a' => 'Un pla mensual de €'.$pro.' per a hotels i espais que organitzen esdeveniments cada setmana. Afegeix ingressos i assistència de cada esdeveniment, les teves millors comunitats en rànquing i exportació a CSV. Només es contracta al web.'],
        ['q' => 'Què passa després de pagar?', 'a' => "El teu pla s'activa a l'instant i tornes a Kolabing a punt per publicar. El pagament el gestiona Stripe: nosaltres no veiem mai les dades de la teva targeta."],
        ['q' => 'Puc cancel·lar?', 'a' => 'Sí, quan vulguis, des del portal de facturació del teu compte. El pla continua actiu fins al final del període que ja has pagat.'],
        ['q' => 'Les comunitats paguen alguna cosa?', 'a' => 'No. Els comptes de comunitat són gratuïts per sempre: explorar, aplicar i col·laborar no costa res.'],
        ['q' => 'Feu descomptes?', 'a' => 'Fem campanyes de tant en tant. Si tens un codi promocional, pots introduir-lo a la pantalla de pagament de Stripe abans de pagar.'],
        ['q' => 'Puc fer servir Kolabing des del mòbil?', 'a' => "Sí. Tot funciona al navegador, i l'app de Kolabing afegeix xat, notificacions i check-in als esdeveniments."],
    ];
@endphp

<x-layouts.marketing-page :title="$title" :description="$description" :canonical="$canonical" :locale="$locale" :alternates="$alternates">
    <x-slot:head>
        @include('pages.partials.pricing-schema', ['description' => $description, 'canonical' => $canonical, 'locale' => $locale, 'c' => $c, 'faqs' => $faqs])
    </x-slot:head>

    @include('pages.partials.pricing-content', ['c' => $c, 'faqs' => $faqs, 'locale' => $locale])
</x-layouts.marketing-page>
