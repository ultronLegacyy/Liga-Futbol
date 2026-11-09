<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Respaldos cifrados (HU-009, RNF-23). Necesitan el cron de `php artisan schedule:run` en el servidor (HU-010)
$zona = 'America/Mexico_City';
$bitacora = storage_path('logs/respaldos.log');

// Criterio 2: completo cada semana, el domingo
Schedule::command('respaldo:crear --completo')->sundays()->at('03:00')->timezone($zona)
    ->withoutOverlapping()->appendOutputTo($bitacora);

// Criterio 1: diario los demás días, con la base completa y los documentos que cambiaron
Schedule::command('respaldo:crear')->days([1, 2, 3, 4, 5, 6])->at('03:00')->timezone($zona)
    ->withoutOverlapping()->appendOutputTo($bitacora);

// Criterio 3: retención de 30 días, después del respaldo
Schedule::command('respaldo:depurar')->dailyAt('04:00')->timezone($zona)
    ->withoutOverlapping()->appendOutputTo($bitacora);

// Criterio 4: prueba de restauración mensual, el día 1
Schedule::command('respaldo:probar')->monthlyOn(1, '05:00')->timezone($zona)
    ->withoutOverlapping()->appendOutputTo($bitacora);
