<?php

return [
    // Tiempo que un partido ocupa su cancha y ventana de disponibilidad arbitral (HU-025 / HU-026 / HU-074).
    'duracion_partido_minutos' => (int) env('PARTIDO_DURACION_MINUTOS', 120),

    // Horarios base utilizados por el motor de programación automática cuando el coordinador no especifica franjas (HU-074)
    'horarios_defecto' => ['09:00', '11:00', '13:00', '15:00'],

    // Pesos iniciales de restricciones blandas por equipo (PO #4 / HU-074 / RF-07.2)
    'pesos_preferencias' => [
        'local' => [
            'cancha' => (int) env('PROGRAMACION_PESO_LOCAL_CANCHA', 40),
            'dia' => (int) env('PROGRAMACION_PESO_LOCAL_DIA', 25),
            'hora' => (int) env('PROGRAMACION_PESO_LOCAL_HORA', 20),
        ],
        'visitante' => [
            'cancha' => (int) env('PROGRAMACION_PESO_VISITANTE_CANCHA', 15),
            'dia' => (int) env('PROGRAMACION_PESO_VISITANTE_DIA', 10),
            'hora' => (int) env('PROGRAMACION_PESO_VISITANTE_HORA', 8),
        ],
    ],
];
