<?php

use App\Http\Controllers\ClubController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\TemporadaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Rutas de temporadas deportivas
Route::resource('temporadas', TemporadaController::class);

// Grupo de rutas de administracion de gobernanza protegidas estrictamente por rol Administrador General
Route::middleware(['auth', 'role:Administrador General'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', function () {
        return response()->json([
            'modulo' => 'Gobernanza y Configuracion',
            'mensaje' => 'Bienvenido al panel maestro de administracion general.',
            'estado' => 'autorizado',
        ]);
    })->name('dashboard');

    Route::get('/gobernanza', function () {
        return response()->json([
            'modulo' => 'Core de Gobernanza',
            'privilegio' => 'Administrador General',
            'acceso' => 'total',
        ]);
    })->name('gobernanza');
});

// Grupo de rutas operativas protegidas por permiso granular de Spatie
Route::middleware(['auth', 'permission:usuarios.ver'])->prefix('admin/gestion')->name('admin.gestion.')->group(function () {
    Route::get('/usuarios', function () {
        return response()->json([
            'modulo' => 'Gestion de Usuarios',
            'mensaje' => 'Listado de usuarios del sistema.',
            'estado' => 'autorizado',
        ]);
    })->name('usuarios');
});

// Rutas de gestion de clubes y equipos con proteccion contra ID Tampering (RNF-29)
Route::middleware(['auth'])->group(function () {
    Route::get('/clubes/{club}', [ClubController::class, 'show'])->name('clubes.show');
    Route::put('/clubes/{club}', [ClubController::class, 'update'])->name('clubes.update');
    Route::get('/clubes/{club}/equipos', [ClubController::class, 'equipos'])->name('clubes.equipos');

    Route::get('/equipos/{equipo}', [EquipoController::class, 'show'])->name('equipos.show');
    Route::put('/equipos/{equipo}', [EquipoController::class, 'update'])->name('equipos.update');
});
