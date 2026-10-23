<?php

use App\Http\Controllers\CanchaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\TemporadaController;
use App\Http\Controllers\ReglaTemporadaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Rutas de temporadas deportivas: requieren sesion y TemporadaPolicy autoriza cada accion (RNF-29)
Route::middleware(['auth'])->group(function () {
    Route::resource('temporadas', TemporadaController::class);
    Route::get('/temporadas/{temporada}/reglas', [ReglaTemporadaController::class, 'index'])
        ->name('temporadas.reglas.index')
        ->middleware('can:reglas_temporada.ver');

    Route::post('/temporadas/{temporada}/reglas', [ReglaTemporadaController::class, 'store'])
        ->name('temporadas.reglas.store')
        ->middleware('can:reglas_temporada.editar');

    // Entrega de la convocatoria desde almacenamiento privado, sin URL publica (RNF-30)
    Route::get('/temporadas/{temporada}/convocatoria', [TemporadaController::class, 'convocatoria'])
        ->name('temporadas.convocatoria');

    // Nueva version de la convocatoria cuando cambian las reglas, con motivo obligatorio (RF-01.3)
    Route::post('/temporadas/{temporada}/convocatoria', [TemporadaController::class, 'versionarConvocatoria'])
        ->name('temporadas.convocatoria.versionar');
});

// Catálogo de canchas y sedes (RF-05.1): CanchaPolicy autoriza cada acción; no hay baja (HU-023)
Route::middleware(['auth'])->group(function () {
    Route::resource('canchas', CanchaController::class)->only(['index', 'show', 'store', 'update']);
});

// Categorías y grupos de cada temporada (RF-02.1, RF-02.2): en una temporada cerrada solo se consultan (RF-01.4)
Route::middleware(['auth'])->group(function () {
    Route::resource('temporadas.categorias', CategoriaController::class)->shallow()->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::resource('categorias.grupos', GrupoController::class)->shallow()->only(['store', 'edit', 'update']);
});

// Grupo de rutas de administracion de gobernanza protegidas estrictamente por rol Administrador General
Route::middleware(['auth', 'role:Administrador General'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
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
