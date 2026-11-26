<?php

return [
    // Tiempo que un partido ocupa su cancha. La ERS no lo define: lo confirma el Product Owner (HU-025)
    'duracion_partido_minutos' => (int) env('PARTIDO_DURACION_MINUTOS', 120),

    // Horarios base utilizados por el motor de programación automática cuando el coordinador no especifica franjas (HU-074)
    'horarios_defecto' => ['09:00', '11:00', '13:00', '15:00'],
];
