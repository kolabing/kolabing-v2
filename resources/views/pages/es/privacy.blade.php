@php
    $title = 'Política de Privacidad';
    $description = 'Cómo Kolabing recopila, usa y protege tus datos personales.';
    $canonical = route('privacy.es');
    $locale = 'es';
    $alternates = [
        ['hreflang' => 'en', 'href' => route('privacy')],
        ['hreflang' => 'es', 'href' => route('privacy.es')],
        ['hreflang' => 'x-default', 'href' => route('privacy')],
    ];
@endphp

<x-layouts.marketing-page :title="$title" :description="$description" :canonical="$canonical" :locale="$locale" :alternates="$alternates">
    <section class="mx-auto max-w-4xl px-6 py-20">
        <div class="flex items-center justify-between gap-4">
            <p class="text-sm text-off-black/50">Última actualización: 12 de julio de 2026</p>
            <p class="text-sm font-semibold"><span>Español</span> <span class="text-off-black/30">·</span> <a href="{{ route('privacy') }}" class="text-off-black/60 underline hover:text-off-black">English</a></p>
        </div>
        <h1 class="mt-4 font-montserrat text-4xl font-black uppercase md:text-5xl">Política de Privacidad</h1>
        <div class="prose prose-lg mt-8 max-w-none prose-headings:font-montserrat prose-headings:uppercase prose-a:text-off-black">
            <p>Esta Política de Privacidad explica cómo Kolabing recopila, usa, comparte y protege tus datos personales cuando utilizas nuestra plataforma, aplicaciones móviles y servicios relacionados (el "Servicio"). Nos comprometemos a proteger tu privacidad y a cumplir con el Reglamento General de Protección de Datos de la UE (RGPD) y con la Ley Orgánica de Protección de Datos Personales y garantía de los derechos digitales española (LOPDGDD).</p>

            <h2>1. Quiénes somos</h2>
            <p>El responsable del tratamiento de tus datos personales es:</p>
            <ul>
                <li><strong>[COMPANY NAME]</strong></li>
                <li>Domicilio social: [REGISTERED ADDRESS]</li>
                <li>Número de registro mercantil / NIF: [COMPANY REG NUMBER / NIF]</li>
                <li>Contacto de privacidad: <a href="mailto:privacy@kolabing.com">privacy@kolabing.com</a></li>
            </ul>

            <h2>2. Información que recopilamos</h2>
            <p>Recopilamos las siguientes categorías de datos personales:</p>
            <ul>
                <li><strong>Datos de cuenta e identidad.</strong> Cuando inicias sesión con Google o Apple, recibimos tu nombre, dirección de correo electrónico, el estado de verificación del correo y tu foto de perfil/avatar. Si usas el inicio de sesión con correo, tratamos tu contraseña y las solicitudes de restablecimiento de contraseña. Tu perfil puede incluir un nombre de usuario, ciudad, intereses y preferencia de idioma.</li>
                <li><strong>Fotos.</strong> Fotos de perfil, fotos de "ofertas" de negocio, fotos de la galería de perfil y fotos de eventos que decidas subir. También tratamos los códigos QR cuando los escaneas para el check-in en eventos y el canje de recompensas.</li>
                <li><strong>Datos de ubicación.</strong> Con tu permiso, tratamos tu ubicación aproximada o precisa (latitud y longitud) para mostrarte eventos cercanos y permitir el check-in en eventos.</li>
                <li><strong>Notificaciones push.</strong> Si activas las notificaciones, tratamos un token push del dispositivo (a través de Firebase Cloud Messaging) y tus preferencias de notificación.</li>
                <li><strong>Mensajería.</strong> Hilos de chat y mensajes intercambiados entre usuarios dentro de la aplicación.</li>
                <li><strong>Datos de pago.</strong> Si eres un usuario de negocio con una suscripción mensual, los pagos son procesados por Stripe. No almacenamos el número completo de tu tarjeta; conservamos datos limitados de la transacción y de la suscripción.</li>
                <li><strong>Datos de uso y analítica.</strong> Analítica de producto recopilada a través de PostHog para entender cómo se usa el Servicio y mejorarlo. Puedes optar por no participar en la analítica.</li>
                <li><strong>Comunicaciones de soporte.</strong> El contenido de cualquier mensaje que nos envíes para obtener soporte.</li>
            </ul>

            <h2>3. Permisos del dispositivo que solicitamos</h2>
            <p>Para ofrecer determinadas funciones, nuestra aplicación puede solicitar los siguientes permisos del dispositivo. Estos permisos son <strong>opcionales</strong> y puedes concederlos o revocarlos en cualquier momento desde los ajustes de tu dispositivo:</p>
            <ul>
                <li><strong>Cámara y galería de fotos</strong> — para tomar o seleccionar fotos de perfil, de ofertas, de galería y de eventos, y para escanear códigos QR.</li>
                <li><strong>Ubicación</strong> — para descubrir eventos cercanos y hacer check-in en eventos.</li>
                <li><strong>Notificaciones</strong> — para enviarte notificaciones push sobre tu actividad en el Servicio.</li>
            </ul>
            <p>Si rechazas o revocas un permiso, es posible que la función relacionada no funcione, pero podrás seguir usando el resto del Servicio.</p>

            <h2>4. Cómo usamos tu información y nuestras bases legales</h2>
            <p>Conforme al artículo 6 del RGPD, nos basamos en las siguientes bases legales:</p>
            <ul>
                <li><strong>Ejecución de un contrato (art. 6.1.b).</strong> Para crear y gestionar tu cuenta, prestar el Servicio, habilitar colaboraciones, procesar suscripciones y ofrecer soporte.</li>
                <li><strong>Consentimiento (art. 6.1.a).</strong> Para acceder a tu cámara, fotos y ubicación, y para enviarte notificaciones push; así como para usar analítica opcional. Puedes retirar tu consentimiento en cualquier momento.</li>
                <li><strong>Intereses legítimos (art. 6.1.f).</strong> Para mantener el Servicio seguro, prevenir fraudes y abusos, mejorar y desarrollar funciones y comunicarnos contigo sobre el Servicio, ponderados frente a tus derechos.</li>
                <li><strong>Obligación legal (art. 6.1.c).</strong> Para cumplir con requisitos contables, fiscales y otras obligaciones legales.</li>
            </ul>

            <h2>5. Cesiones y encargados del tratamiento</h2>
            <p>No vendemos tus datos personales. Los compartimos únicamente con proveedores de confianza ("encargados del tratamiento") que los tratan por cuenta nuestra bajo contrato, y cuando lo exige la ley. Entre ellos:</p>
            <ul>
                <li><strong>Google</strong> y <strong>Apple</strong> — inicio de sesión y autenticación.</li>
                <li><strong>Stripe</strong> — procesamiento de pagos.</li>
                <li><strong>Firebase Cloud Messaging</strong> y el <strong>servicio de notificaciones push de Apple</strong> — envío de notificaciones push.</li>
                <li><strong>Proveedores de alojamiento en la nube</strong> — alojamiento y almacenamiento del Servicio y de sus datos.</li>
                <li><strong>PostHog</strong> — analítica de producto.</li>
            </ul>
            <p>También podemos compartir datos con otros usuarios como parte del Servicio (por ejemplo, tu perfil y mensajes), y con autoridades o asesores cuando sea necesario para cumplir con la ley o proteger nuestros derechos.</p>

            <h2>6. Transferencias internacionales</h2>
            <p>Algunos de nuestros encargados del tratamiento pueden almacenar o tratar datos fuera del Espacio Económico Europeo (EEE). Cuando esto ocurre, garantizamos que existan las salvaguardas adecuadas, como las Cláusulas Contractuales Tipo de la Comisión Europea o una decisión de adecuación, de modo que tus datos reciban un nivel de protección equivalente.</p>

            <h2>7. Conservación de los datos</h2>
            <p>Conservamos tus datos personales únicamente durante el tiempo necesario para los fines descritos en esta política. Por lo general, conservamos los datos de la cuenta mientras esta permanezca activa. Cuando eliminas tu cuenta, la marcamos como eliminada (borrado lógico) y luego suprimimos o anonimizamos tus datos personales en un plazo razonable, salvo cuando debamos conservar determinada información para cumplir obligaciones legales (como registros fiscales y contables) o para resolver disputas.</p>

            <h2>8. Tus derechos</h2>
            <p>Conforme al RGPD y a la LOPDGDD, tienes derecho a:</p>
            <ul>
                <li><strong>Acceso</strong> — obtener una copia de los datos personales que tenemos sobre ti.</li>
                <li><strong>Rectificación</strong> — corregir datos inexactos o incompletos.</li>
                <li><strong>Supresión</strong> — solicitar la eliminación de tus datos ("derecho al olvido"). Puedes eliminar tu cuenta en cualquier momento y suprimiremos tus datos, sujeto a nuestras obligaciones de conservación.</li>
                <li><strong>Limitación</strong> — pedirnos que limitemos cómo tratamos tus datos.</li>
                <li><strong>Portabilidad</strong> — recibir tus datos en un formato estructurado, de uso común y de lectura mecánica.</li>
                <li><strong>Oposición</strong> — oponerte al tratamiento basado en nuestros intereses legítimos.</li>
                <li><strong>Retirar el consentimiento</strong> — retirar cualquier consentimiento que hayas otorgado, en cualquier momento, sin que ello afecte al tratamiento previo.</li>
            </ul>
            <p>Para ejercer tus derechos, contáctanos en <a href="mailto:privacy@kolabing.com">privacy@kolabing.com</a>. También tienes derecho a presentar una reclamación ante la autoridad de control española, la Agencia Española de Protección de Datos (AEPD), en <a href="https://www.aepd.es">www.aepd.es</a>.</p>

            <h2>9. Menores</h2>
            <p>El Servicio está destinado únicamente a personas de 18 años o más. No recopilamos conscientemente datos personales de menores de 18 años. Si crees que un menor de 18 años nos ha facilitado datos personales, contáctanos para que podamos eliminarlos.</p>

            <h2>10. Seguridad</h2>
            <p>Aplicamos medidas técnicas y organizativas adecuadas para proteger tus datos personales frente a pérdidas, usos indebidos y accesos no autorizados, incluidas el cifrado en tránsito, controles de acceso y alojamiento seguro. Ningún sistema es completamente seguro, pero trabajamos continuamente para proteger tus datos y notificaremos a ti y a la AEPD cualquier brecha de datos personales cuando la ley así lo exija.</p>

            <h2>11. Cambios en esta política</h2>
            <p>Podemos actualizar esta Política de Privacidad de vez en cuando. Si realizamos cambios sustanciales, te lo notificaremos (por ejemplo, en la aplicación o por correo electrónico) y actualizaremos la fecha de "Última actualización" indicada arriba. Te animamos a revisar esta política periódicamente.</p>

            <h2>12. Contacto</h2>
            <p>Si tienes cualquier pregunta sobre esta Política de Privacidad o sobre cómo tratamos tus datos, contáctanos:</p>
            <ul>
                <li><strong>[COMPANY NAME]</strong></li>
                <li>Domicilio social: [REGISTERED ADDRESS]</li>
                <li>Número de registro mercantil / NIF: [COMPANY REG NUMBER / NIF]</li>
                <li>Privacidad: <a href="mailto:privacy@kolabing.com">privacy@kolabing.com</a></li>
                <li>Soporte: <a href="mailto:support@kolabing.com">support@kolabing.com</a></li>
            </ul>
        </div>
    </section>
</x-layouts.marketing-page>
