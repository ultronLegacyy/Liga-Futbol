<?php

return [
    // Tiempo que un partido ocupa su cancha. La ERS no lo define: lo confirma el Product Owner (HU-025)
    'duracion_partido_minutos' => (int) env('PARTIDO_DURACION_MINUTOS', 120),
];
