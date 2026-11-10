<?php

/*
 * Reglas de la Liga para inscribir jugadores.
 *
 * edad_minima: regla de la Liga (Medina, 10/11/2026): solo se inscriben jugadores mayores
 * de edad. Se valida en el registro (HU-016), la renovación y la transferencia (HU-017) a la
 * fecha de la operación. La ERS contempla la rama infantil (HU-020, HU-021, Comisión de la
 * Rama Infantil); si el Product Owner la confirma, dejar JUGADOR_EDAD_MINIMA vacío y la regla
 * se desactiva sin tocar el código.
 */
return [
    'edad_minima' => filter_var(env('JUGADOR_EDAD_MINIMA', 18), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
];
