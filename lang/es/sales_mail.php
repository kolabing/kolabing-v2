<?php

declare(strict_types=1);

/*
 * Castellano. Se tutea al destinatario, igual que en el resto del producto: el
 * público son dueños de bares, cafeterías y gimnasios de barrio, y el "usted"
 * suena a carta del banco.
 *
 * Ojo con "estimación" y "no es una garantía": son justamente las palabras que
 * mantienen honesta la cifra de ingresos, y por eso viven aquí y no en el texto
 * que genera el modelo.
 */
return [
    'greeting' => 'Hola :name:',

    'estimate_heading' => 'Lo que podría suponer una noche así',

    'estimate_line' => ':attendees personas en el local × :spend de gasto medio ≈ :total en la noche.',

    'estimate_disclaimer' => 'Es una estimación, no una garantía: parte del número de miembros que declara la comunidad y de un gasto medio habitual en un local como el tuyo. Cambia cualquiera de los dos números y la cuenta te sale sola.',

    'cta_button' => 'Ver la colaboración',

    'signoff' => 'Si te encaja, responde a este correo y lo organizamos.'."\n\n".'— El equipo de Kolabing',
];
