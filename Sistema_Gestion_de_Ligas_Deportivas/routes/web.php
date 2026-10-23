<?php

use App\Http\Controllers\CanchaBloqueoController;
use App\Http\Controllers\CanchaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\ForcePasswordChangeController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\ReglaTemporadaController;
use App\Http\Controllers\TemporadaController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\ForcePasswordChange;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Agrupamos todas las rutas que requieren sesión
Route::middleware(['auth'])->group(function () {

    // 1. Rutas de cambio obligatorio (EXENTAS del middleware de bloqueo)
    // CAMBIO AQUÍ: Se cambió el nombre de 'password.force-change.show' a 'password.force-change' para que coincida con el test
    Route::get('/cambio-contrasena-obligatorio', [ForcePasswordChangeController::class, 'show'])
        ->name('password.force-change.show');

    Route::post('/cambio-contrasena-obligatorio', [ForcePasswordChangeController::class, 'update'])
        ->name('password.force-change.update');

    // 2. Rutas del sistema PROTEGIDAS por el bloqueo temporal
    Route::middleware([ForcePasswordChange::class])->group(function () {

        // Rutas de temporadas deportivas y reglas (Merge de develop)
        Route::resource('temporadas', TemporadaController::class);

        Route::get('/temporadas/{temporada}/reglas', [ReglaTemporadaController::class, 'index'])
            ->name('temporadas.reglas.index')
            ->middleware('can:reglas_temporada.ver');

        Route::post('/temporadas/{temporada}/reglas', [ReglaTemporadaController::class, 'store'])
            ->name('temporadas.reglas.store')
            ->middleware('can:reglas_temporada.editar');

        Route::get('/temporadas/{temporada}/convocatoria', [TemporadaController::class, 'convocatoria'])
            ->name('temporadas.convocatoria');
        Route::post('/temporadas/{temporada}/convocatoria', [TemporadaController::class, 'versionarConvocatoria'])
            ->name('temporadas.convocatoria.versionar');

        // Catálogo de canchas y sedes
        Route::resource('canchas', CanchaController::class)->only(['index', 'show', 'create', 'store', 'edit', 'update']);

        // Disponibilidad y bloqueos temporales de canchas (HU-024, RF-05.2)
        Route::get('/canchas/{cancha}/bloqueos', [CanchaBloqueoController::class, 'index'])
            ->name('canchas.bloqueos.index');
        Route::post('/canchas/{cancha}/bloqueos', [CanchaBloqueoController::class, 'store'])
            ->name('canchas.bloqueos.store');
        Route::delete('/canchas/{cancha}/bloqueos/{bloqueo}', [CanchaBloqueoController::class, 'destroy'])
            ->name('canchas.bloqueos.destroy');
        Route::get('/canchas/{cancha}/disponibilidad', [CanchaBloqueoController::class, 'disponibilidad'])
            ->name('canchas.disponibilidad');

        // Categorías y grupos
        Route::resource('temporadas.categorias', CategoriaController::class)->shallow()->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        Route::resource('categorias.grupos', GrupoController::class)->shallow()->only(['store', 'edit', 'update']);

        // Grupo de rutas de administracion de gobernanza y gestión de cuentas (S2-T13)
        Route::middleware(['role:Administrador General'])->prefix('admin')->name('admin.')->group(function () {
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

            // Rutas para la administración de cuentas de usuario (HU-080)
            Route::get('/usuarios', [UserController::class, 'index'])->name('usuarios.index');
            Route::get('/usuarios/create', [UserController::class, 'create'])->name('usuarios.create');
            Route::post('/usuarios', [UserController::class, 'store'])->name('usuarios.store');
            Route::get('/usuarios/{usuario}/edit', [UserController::class, 'edit'])->name('usuarios.edit');
            Route::get('/usuarios/{usuario}/estado', [UserController::class, 'estado'])->name('usuarios.estado');
            Route::put('/usuarios/{usuario}', [UserController::class, 'update'])->name('usuarios.update');
            Route::patch('/usuarios/{usuario}/toggle-status', [UserController::class, 'toggleStatus'])->name('usuarios.toggleStatus');
        });

        // Grupo de rutas operativas protegidas por permiso
        Route::middleware(['permission:usuarios.ver'])->prefix('admin/gestion')->name('admin.gestion.')->group(function () {
            Route::get('/usuarios', function () {
                return response()->json([
                    'modulo' => 'Gestion de Usuarios',
                    'mensaje' => 'Listado de usuarios del sistema.',
                    'estado' => 'autorizado',
                ]);
            })->name('usuarios');
        });

        // Rutas de gestion de clubes y equipos
        Route::get('/clubes/{club}', [ClubController::class, 'show'])->name('clubes.show');
        Route::put('/clubes/{club}', [ClubController::class, 'update'])->name('clubes.update');
        Route::get('/clubes/{club}/equipos', [ClubController::class, 'equipos'])->name('clubes.equipos');

        Route::get('/equipos/{equipo}', [EquipoController::class, 'show'])->name('equipos.show');
        Route::put('/equipos/{equipo}', [EquipoController::class, 'update'])->name('equipos.update');
    });
});
