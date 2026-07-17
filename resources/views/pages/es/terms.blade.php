@php
    $title = 'Términos del Servicio';
    $description = 'Las condiciones que rigen el uso de Kolabing.';
    $canonical = route('terms.es');
    $locale = 'es';
    $alternates = [
        ['hreflang' => 'en', 'href' => route('terms')],
        ['hreflang' => 'es', 'href' => route('terms.es')],
        ['hreflang' => 'x-default', 'href' => route('terms')],
    ];
@endphp

<x-layouts.marketing-page :title="$title" :description="$description" :canonical="$canonical" :locale="$locale" :alternates="$alternates">
    <section class="mx-auto max-w-4xl px-6 py-20">
        <div class="flex items-center justify-between gap-4">
            <p class="text-sm text-off-black/50">Última actualización: {{ $company->effectiveDateLabel($locale) }}</p>
            <p class="text-sm font-semibold"><span>Español</span> <span class="text-off-black/30">·</span> <a href="{{ route('terms') }}" class="text-off-black/60 underline hover:text-off-black">English</a></p>
        </div>
        <h1 class="mt-4 font-montserrat text-4xl font-black uppercase md:text-5xl">Términos del Servicio</h1>
        <div class="prose prose-lg mt-8 max-w-none prose-headings:font-montserrat prose-headings:uppercase prose-a:text-off-black">
            <p>Te damos la bienvenida a Kolabing. Estos Términos del Servicio ("Términos") constituyen un acuerdo legal entre tú y {{ $company->legal_name }} ("Kolabing", "nosotros" o "nuestro"), operador de la plataforma Kolabing, sus aplicaciones móviles y servicios relacionados (en conjunto, el "Servicio"). Léelos con atención. Al crear una cuenta o usar el Servicio, aceptas quedar vinculado por estos Términos.</p>

            <h2>1. Aceptación y elegibilidad</h2>
            <p>Al acceder o usar el Servicio, confirmas que has leído, comprendido y aceptas estos Términos y nuestra Política de Privacidad. Si no estás de acuerdo, no uses el Servicio.</p>
            <p>Debes tener al menos 18 años para usar Kolabing. Al usar el Servicio, declaras y garantizas que tienes 18 años o más y que tienes capacidad legal para aceptar estos Términos. Si usas el Servicio en nombre de una empresa u organización, declaras estar autorizado para vincular a esa entidad a estos Términos.</p>

            <h2>2. El Servicio</h2>
            <p>Kolabing es un marketplace de colaboración con sede en España que conecta a negocios locales con organizadores de comunidades, y que además ofrece una experiencia para asistentes y de gamificación. Según cómo te registres, podrás usar el Servicio de alguna de estas formas:</p>
            <ul>
                <li><strong>Negocios</strong> — crear y publicar oportunidades de colaboración y ofertas.</li>
                <li><strong>Comunidades</strong> — explorar oportunidades y postularse para colaborar con negocios.</li>
                <li><strong>Asistentes</strong> — descubrir eventos cercanos, hacer check-in, completar retos y obtener recompensas.</li>
            </ul>
            <p><strong>Kolabing es un facilitador, no una parte de ninguna colaboración.</strong> Ofrecemos la plataforma que ayuda a negocios, comunidades y asistentes a encontrarse y conectar entre sí. No somos parte de ningún acuerdo, arreglo, evento, oferta, pago o resultado entre usuarios, ni nos responsabilizamos de ellos. No garantizamos la calidad, seguridad, legalidad ni el cumplimiento de ninguna colaboración, oportunidad, recompensa o usuario.</p>

            <h2>3. Cuentas</h2>
            <p>Para usar la mayoría de las funciones debes crear una cuenta. Puedes iniciar sesión con Google o Apple y, en algunos casos, con un correo electrónico y contraseña. Te comprometes a:</p>
            <ul>
                <li>Proporcionar información veraz, actual y completa, y mantenerla actualizada.</li>
                <li>Mantener la confidencialidad de tus credenciales y no compartir tu cuenta.</li>
                <li>Ser responsable de toda la actividad que ocurra bajo tu cuenta.</li>
                <li>Notificarnos de inmediato en <a href="mailto:{{ $company->support_email }}">{{ $company->support_email }}</a> si sospechas de un uso no autorizado de tu cuenta.</li>
            </ul>
            <p>Eres responsable de mantener la seguridad de tu cuenta y de tu dispositivo. No somos responsables de ninguna pérdida derivada de que no protejas tus credenciales.</p>

            <h2>4. Suscripciones y pagos</h2>
            <p>Algunas funciones están disponibles únicamente para usuarios de negocio mediante una suscripción mensual de pago. Las suscripciones se compran y se facturan a través de tu cuenta de la <strong>App Store de Apple</strong> como una compra dentro de la aplicación; Apple procesa el pago y nosotros nunca recibimos ni almacenamos los datos de tu tarjeta. La facturación y las renovaciones las gestiona Apple a través de tu Apple ID.</p>
            <ul>
                <li><strong>Facturación y renovación.</strong> Las suscripciones de negocio se facturan mensualmente y se renuevan automáticamente al final de cada periodo de facturación hasta su cancelación.</li>
                <li><strong>Cancelación.</strong> Puedes cancelar en cualquier momento desde los ajustes de suscripción de tu Apple ID en tu dispositivo. La cancelación surte efecto al final del periodo de facturación en curso, y mantendrás el acceso a las funciones de pago hasta entonces.</li>
                <li><strong>Cambios de precio.</strong> Podemos modificar los precios de la suscripción. Te avisaremos con una antelación razonable y cualquier cambio se aplicará a partir de tu siguiente periodo de facturación.</li>
                <li><strong>Reembolsos.</strong> {{ $company->refund_policy }}. Las suscripciones compradas a través de la App Store están sujetas al proceso de reembolso de Apple, y las solicitudes de reembolso las gestiona Apple. Salvo cuando lo exija la legislación de consumo española y de la UE aplicable, las cuotas de suscripción no son reembolsables.</li>
                <li><strong>Impuestos.</strong> Los precios pueden mostrarse con o sin los impuestos aplicables (como el IVA), según se indique en el proceso de pago.</li>
            </ul>

            <h2>5. Colaboraciones</h2>
            <p>Cuando los usuarios acuerdan colaborar, lo hacen directamente entre ellos. <strong>Los usuarios son los únicos responsables de sus propios acuerdos, logística y resultados</strong>, incluidos los términos de cualquier colaboración, la entrega de cualquier oferta o entregable, la organización del evento, la asistencia, la salud y la seguridad, y el cumplimiento de la legislación aplicable. Kolabing no supervisa, dirige ni controla las colaboraciones y declina toda responsabilidad sobre ellas. Cualquier disputa entre usuarios deberá resolverse entre las partes implicadas.</p>

            <h2>6. Contenido del usuario y licencia</h2>
            <p>El Servicio te permite subir y compartir contenido, como fotos de perfil, fotos de ofertas y de galería, fotos de eventos y mensajes ("Contenido del Usuario"). <strong>Conservas la titularidad de tu Contenido del Usuario.</strong></p>
            <p>Al enviar Contenido del Usuario, concedes a Kolabing una licencia mundial, no exclusiva y libre de regalías para alojar, almacenar, reproducir, adaptar (a efectos de formato y visualización) y mostrar dicho contenido, únicamente en la medida necesaria para operar, prestar y mejorar el Servicio. Esta licencia finaliza cuando eliminas el contenido correspondiente o tu cuenta, salvo el contenido ya compartido con otros o cuando debamos conservarlo para cumplir con la ley.</p>
            <p>Eres responsable de tu Contenido del Usuario y confirmas que tienes los derechos para compartirlo y que no infringe los derechos de terceros ni la ley. Debes obtener el consentimiento de cualquier persona identificable que aparezca en las fotos que subas.</p>

            <h2>7. Uso aceptable y conductas prohibidas</h2>
            <p>Te comprometes a no:</p>
            <ul>
                <li>Incumplir ninguna ley o norma aplicable, ni infringir los derechos de terceros.</li>
                <li>Publicar contenido ilícito, dañino, acosador, difamatorio, de odio, engañoso u obsceno.</li>
                <li>Suplantar a ninguna persona ni tergiversar tu vinculación con nadie.</li>
                <li>Subir virus, código malicioso, ni intentar hackear, interrumpir o sobrecargar el Servicio.</li>
                <li>Extraer, recopilar o recabar datos del Servicio sin nuestro permiso.</li>
                <li>Usar el Servicio para enviar spam o comunicaciones no solicitadas.</li>
                <li>Eludir cualquier medida de seguridad, muro de pago de suscripción o control de acceso.</li>
                <li>Usar el Servicio con fines fraudulentos o engañosos.</li>
            </ul>

            <h2>8. Propiedad intelectual</h2>
            <p>El Servicio, incluidos su software, diseño, textos, gráficos, logotipos y marcas (pero excluyendo el Contenido del Usuario), es propiedad de Kolabing o de sus licenciantes y está protegido por las leyes de propiedad intelectual. Te concedemos una licencia limitada, personal, intransferible y revocable para usar el Servicio según su finalidad prevista. No puedes copiar, modificar, distribuir, vender ni crear obras derivadas de ninguna parte del Servicio sin nuestro consentimiento previo por escrito.</p>

            <h2>9. Servicios de terceros</h2>
            <p>El Servicio se apoya en proveedores externos (por ejemplo, el inicio de sesión de Google y Apple, la App Store de Apple para los pagos de la suscripción y los servicios de notificaciones push). Tu uso de esos servicios puede estar sujeto a sus propias condiciones y políticas. No somos responsables de los servicios de terceros ni tenemos control sobre ellos.</p>

            <h2>10. Suspensión y terminación</h2>
            <p>Puedes dejar de usar el Servicio y eliminar tu cuenta en cualquier momento. Podemos suspender o cancelar tu acceso al Servicio, con o sin previo aviso, si incumples estos Términos, si la ley nos obliga a ello, o para proteger el Servicio o a otros usuarios. Tras la terminación, las licencias que nos concediste finalizan (sujeto a la Sección 6), y las disposiciones que por su naturaleza deban subsistir (como las exenciones de garantía, la limitación de responsabilidad y la ley aplicable) seguirán vigentes.</p>

            <h2>11. Exenciones de garantía</h2>
            <p>El Servicio se ofrece "tal cual" y "según disponibilidad". En la máxima medida permitida por la ley, declinamos toda garantía, ya sea expresa o implícita, incluida la idoneidad para un fin concreto, la comerciabilidad y la no infracción. No garantizamos que el Servicio sea ininterrumpido, esté libre de errores o sea seguro, ni que ningún contenido, oportunidad, colaboración o recompensa cumpla tus expectativas. Nada en estos Términos excluye ninguna garantía o derecho que no pueda excluirse conforme a la legislación imperativa española o de la UE.</p>

            <h2>12. Limitación de responsabilidad</h2>
            <p>En la máxima medida permitida por la ley, Kolabing no será responsable de daños indirectos, incidentales, especiales, consecuentes o punitivos, ni de la pérdida de beneficios, ingresos, datos o fondo de comercio, derivados de o relacionados con tu uso del Servicio. Nuestra responsabilidad total acumulada por todas las reclamaciones relacionadas con el Servicio no superará la mayor de las siguientes cantidades: los importes que nos hayas pagado en los doce meses anteriores al hecho que origine la reclamación, o cien euros (100 €). Nada en estos Términos limita la responsabilidad que no pueda limitarse conforme a la legislación imperativa, incluida la responsabilidad por muerte o lesiones personales causadas por negligencia, o por fraude.</p>

            <h2>13. Indemnización</h2>
            <p>Aceptas indemnizar y eximir de responsabilidad a Kolabing y a sus administradores, empleados y agentes frente a cualquier reclamación, daño, pérdida y gasto (incluidos honorarios legales razonables) que surjan de tu uso del Servicio, de tu Contenido del Usuario, de tus colaboraciones con otros usuarios, o del incumplimiento de estos Términos o de cualquier ley.</p>

            <h2>14. Ley aplicable y disputas</h2>
            <p>Estos Términos se rigen por la legislación de España. Sin perjuicio de los derechos imperativos que te asisten como consumidor, cualquier disputa relacionada con estos Términos o con el Servicio se someterá a la jurisdicción de los tribunales competentes de España. Si eres consumidor, también podrás tener derecho a iniciar acciones ante los tribunales de tu lugar de residencia, y podrás utilizar la plataforma de resolución de litigios en línea de la Comisión Europea.</p>

            <h2>15. Cambios en estos Términos</h2>
            <p>Podemos actualizar estos Términos de vez en cuando. Si realizamos cambios sustanciales, te lo notificaremos (por ejemplo, en la aplicación o por correo electrónico) y actualizaremos la fecha de "Última actualización" indicada arriba. Los cambios surten efecto en el momento de su publicación, salvo que indiquemos lo contrario. El uso continuado del Servicio tras la entrada en vigor de los cambios significa que aceptas los Términos actualizados.</p>

            <h2>16. Contacto</h2>
            <p>Si tienes cualquier pregunta sobre estos Términos, contáctanos:</p>
            <ul>
                <li><strong>{{ $company->legal_name }}</strong></li>
                <li>Domicilio social: {{ $company->registered_address }}</li>
                <li>Número de registro mercantil / NIF: {{ $company->registration_number }}</li>
                <li>Soporte: <a href="mailto:{{ $company->support_email }}">{{ $company->support_email }}</a></li>
            </ul>
        </div>
    </section>
</x-layouts.marketing-page>
