<?php

// Respaldos cifrados de la base de datos y los documentos (HU-009, RNF-23, D-036)
return [

    // Disco de destino: «respaldos» (local) en desarrollo y «r2» en producción, sin cambiar código
    'disco' => env('RESPALDO_DISCO') ?: 'respaldos',

    // Días que se conserva cada respaldo (criterio 3)
    'retencion_dias' => 30,

    // Clave de cifrado en base64 (32 bytes). Nunca se escribe en logs ni mensajes
    'clave' => env('RESPALDO_CLAVE'),

    // Programas de volcado e importación. Pueden llevar argumentos separados por espacios,
    // por ejemplo «docker exec -i -e MYSQL_PWD sgld-mariadb mariadb-dump»
    'volcado' => env('RESPALDO_VOLCADO') ?: 'mariadb-dump',
    'importacion' => env('RESPALDO_IMPORTACION') ?: 'mariadb',

    // Fechas de los nombres de los respaldos y de la programación
    'zona_horaria' => 'America/Mexico_City',

    // Carpeta para los archivos temporales; se vacía siempre al terminar
    'trabajo' => storage_path('app/respaldos-trabajo'),

    // Restauración aparte: nunca sobre la base ni la carpeta de operación sin --operacion
    'restauracion' => [
        'base' => env('RESPALDO_BASE_RESTAURACION') ?: 'sgld_restauracion',
        'carpeta' => storage_path('app/restauracion'),
    ],

    // Prueba mensual (criterio 4)
    'prueba' => [
        'rpo_horas' => 24,
        'rto_horas' => 4,
        'tablas' => ['migrations', 'usuarios', 'roles', 'temporadas', 'clubes', 'auditoria_logs'],
    ],

];
