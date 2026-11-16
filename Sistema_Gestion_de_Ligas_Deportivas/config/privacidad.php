<?php

/*
 * Aviso de privacidad del registro de jugadores (HU-020, OR-01).
 *
 * Cada consentimiento guarda la versión del aviso que se mostró. Si cambia el texto
 * del aviso (resources/views/privacidad/_aviso_jugador.blade.php), hay que subir la
 * versión para distinguir qué aceptó cada jugador o tutor.
 */
return [
    'aviso_jugador' => [
        'version' => env('AVISO_PRIVACIDAD_JUGADOR_VERSION', '2026-11-v1'),
    ],

    // Mayoría de edad para exigir el consentimiento del tutor (Código Civil Federal, art. 646).
    'mayoria_de_edad' => 18,

    // Categorías que por nombre exigen el consentimiento del tutor, sin importar la rama.
    'prefijos_categoria_con_tutor' => ['Juvenil'],

    // Ramas que siempre exigen el consentimiento del tutor.
    'ramas_con_tutor' => ['Infantil'],

    'parentescos_tutor' => ['Madre', 'Padre', 'Tutor legal', 'Otro familiar responsable'],
];
