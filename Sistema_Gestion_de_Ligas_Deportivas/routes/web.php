<?php

use App\Http\Controllers\AdeudoClubController;
use App\Http\Controllers\ArbitroController;
use App\Http\Controllers\AsambleaController;
use App\Http\Controllers\CanchaBloqueoController;
use App\Http\Controllers\CanchaController;
use App\Http\Controllers\CargoFinancieroController;
use App\Http\Controllers\CedulaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\ForcePasswordChangeController;
use App\Http\Controllers\GeneradorEnfrentamientosController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\HistorialEquipoController;
use App\Http\Controllers\JornadaController;
use App\Http\Controllers\JugadorController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PartidoController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\RecuperacionContrasenaController;
use App\Http\Controllers\ReglaTemporadaController;
use App\Http\Controllers\RolPermisoController;
use App\Http\Controllers\TarjetonDigitalController;
use App\Http\Controllers\TemporadaController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\ForcePasswordChange;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('auth.login');
})->name('inicio');

// Recuperación de contraseña por correo (HU-003). Cada envío tiene su propio contador de intentos por IP
Route::middleware(['guest'])->group(function () {
    Route::get('/forgot-password', [RecuperacionContrasenaController::class, 'solicitud'])
        ->name('password.request');
    Route::post('/forgot-password', [RecuperacionContrasenaController::class, 'enviarEnlace'])
        ->middleware('throttle:5,1,solicitar-enlace')
        ->name('password.email');
    Route::get('/reset-password/{token}', [RecuperacionContrasenaController::class, 'formulario'])
        ->middleware('throttle:20,1,abrir-enlace')
        ->name('password.reset');
    Route::post('/reset-password', [RecuperacionContrasenaController::class, 'restablecer'])
        ->middleware('throttle:5,1,restablecer')
        ->name('password.update');
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

        // Formato de competencia y criterios de clasificación del grupo (HU-008, RF-02.3)
        Route::get('/grupos/{grupo}/configuracion', [GrupoController::class, 'configuracion'])
            ->name('grupos.configuracion');
        Route::put('/grupos/{grupo}/configuracion', [GrupoController::class, 'guardarConfiguracion'])
            ->name('grupos.configuracion.update');

        // Programación de jornadas y descansos por grupo (HU-027, HU-028, RF-06.1, RF-06.2)
        Route::resource('grupos.jornadas', JornadaController::class)->shallow()->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
        Route::match(['put', 'patch'], '/jornadas/{jornada}/descanso', [JornadaController::class, 'actualizarDescanso'])
            ->name('jornadas.descanso.update');

        // Generación automática de rol regular y enfrentamientos (HU-032, RF-07.1)
        Route::get('/grupos/{grupo}/generar-rol', [GeneradorEnfrentamientosController::class, 'configurar'])
            ->name('grupos.generar-rol');
        Route::post('/grupos/{grupo}/previsualizar-rol', [GeneradorEnfrentamientosController::class, 'previsualizar'])
            ->name('grupos.previsualizar-rol');
        Route::post('/grupos/{grupo}/confirmar-rol', [GeneradorEnfrentamientosController::class, 'confirmar'])
            ->name('grupos.confirmar-rol');
        Route::post('/jornadas/{jornada}/generar-partidos', [GeneradorEnfrentamientosController::class, 'generarJornada'])
            ->name('jornadas.generar-partidos');

        // Alta manual y reprogramación de partidos en jornadas (HU-027, RF-06.1)
        Route::resource('jornadas.partidos', PartidoController::class)->shallow()->only(['create', 'store', 'show', 'edit', 'update', 'destroy']);

        // Padrón de personas y sus roles por temporada (HU-013, RF-03.2)
        Route::resource('personas', PersonaController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('/personas/{persona}/roles', [PersonaController::class, 'asignarRol'])
            ->name('personas.roles.store');

        // Historial de equipos por temporada (HU-015, RF-03.4)
        Route::get('/historial-equipos', [HistorialEquipoController::class, 'index'])->name('equipos.historial.index');
        Route::get('/historial-equipos/{equipo}', [HistorialEquipoController::class, 'show'])->name('equipos.historial.show');

        // Padrón de árbitros y su asignación a partidos programados (HU-026, RF-06.1)
        Route::get('/arbitros/asignaciones', [ArbitroController::class, 'asignaciones'])
            ->name('arbitros.asignaciones');
        Route::resource('arbitros', ArbitroController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        // Cédula arbitral del partido: el Árbitro en Campo solo entra a sus partidos (HU-036, RNF-29)
        Route::get('/arbitro/partidos', [CedulaController::class, 'misPartidos'])
            ->name('cedulas.mis-partidos');
        Route::get('/partidos/{partido}/cedula', [CedulaController::class, 'show'])
            ->whereNumber('partido')
            ->name('cedulas.show');
        Route::post('/partidos/{partido}/cedula', [CedulaController::class, 'abrir'])
            ->whereNumber('partido')
            ->name('cedulas.abrir');
        // Captura desde el celular: cada cambio lleva su clave de envío y un reenvío no duplica nada (RNF-21)
        Route::put('/partidos/{partido}/cedula/alineacion/{jugador}', [CedulaController::class, 'alineacion'])
            ->whereNumber(['partido', 'jugador'])
            ->name('cedulas.alineacion');
        Route::put('/partidos/{partido}/cedula/marcador', [CedulaController::class, 'marcador'])
            ->whereNumber('partido')
            ->name('cedulas.marcador');
        Route::post('/partidos/{partido}/cedula/eventos', [CedulaController::class, 'evento'])
            ->whereNumber('partido')
            ->name('cedulas.eventos');
        Route::post('/partidos/{partido}/cedula/incidencias', [CedulaController::class, 'incidencia'])
            ->whereNumber('partido')
            ->name('cedulas.incidencias');

        Route::get('/partidos/{partido}/arbitro', [ArbitroController::class, 'editarAsignacion'])
            ->name('partidos.arbitro.edit');
        Route::put('/partidos/{partido}/arbitro', [ArbitroController::class, 'asignar'])
            ->name('partidos.arbitro.update');

        // Asambleas de delegados: convocatoria y asistencia (HU-053), acuerdos y comunicados (HU-054), RF-14
        Route::resource('asambleas', AsambleaController::class)->only(['index', 'create', 'store', 'show']);
        Route::get('/asambleas/{asamblea}/asistencia', [AsambleaController::class, 'editarAsistencia'])
            ->name('asambleas.asistencia.edit');
        Route::put('/asambleas/{asamblea}/asistencia', [AsambleaController::class, 'guardarAsistencia'])
            ->name('asambleas.asistencia.update');
        Route::post('/asambleas/{asamblea}/acuerdos', [AsambleaController::class, 'guardarAcuerdo'])
            ->name('asambleas.acuerdos.store');
        Route::post('/asambleas/{asamblea}/acuerdos/{acuerdo}/regla-permanente', [AsambleaController::class, 'autorizarAcuerdo'])
            ->name('asambleas.acuerdos.autorizar');
        Route::post('/asambleas/{asamblea}/comunicados', [AsambleaController::class, 'guardarComunicado'])
            ->name('asambleas.comunicados.store');

        // Adeudos por club y temporada: quién está al corriente (HU-051, RF-13.2)
        Route::get('/adeudos', [AdeudoClubController::class, 'index'])
            ->name('adeudos.index');

        // Cuotas de inscripción y cargos de arbitraje y cancha por temporada (HU-050, RF-13.1)
        Route::resource('cargos', CargoFinancieroController::class)->only(['index', 'create', 'store', 'show']);

        // Pagos de los cargos y sus comprobantes, en el disco privado (HU-051, RF-13.2)
        Route::post('/cargos/{cargo}/pagos', [PagoController::class, 'store'])
            ->name('cargos.pagos.store');
        Route::get('/pagos/{pago}/comprobante', [PagoController::class, 'comprobante'])
            ->name('pagos.comprobante');

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

            // Permisos de cada rol en solo lectura (HU-081, RF-12.1); cualquier cambio se rechaza (CA3)
            Route::get('/roles', [RolPermisoController::class, 'index'])->name('roles.index');
            Route::get('/roles/{rol}', [RolPermisoController::class, 'show'])->whereNumber('rol')->name('roles.show');
            Route::match(['post', 'put', 'patch', 'delete'], '/roles/{rol?}', [RolPermisoController::class, 'rechazarCambio'])
                ->whereNumber('rol')->name('roles.cambio');
            Route::match(['post', 'put', 'patch', 'delete'], '/roles/{rol}/permisos/{permiso?}', [RolPermisoController::class, 'rechazarCambio'])
                ->whereNumber(['rol', 'permiso'])->name('roles.permisos.cambio');
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

        // Registrar un club (HU-011)
        Route::post('/clubes', [ClubController::class, 'store'])->name('clubes.store');

        Route::get('/clubes/{club}', [ClubController::class, 'show'])->name('clubes.show');
        Route::put('/clubes/{club}', [ClubController::class, 'update'])->name('clubes.update');
        Route::get('/clubes/{club}/equipos', [ClubController::class, 'equipos'])->name('clubes.equipos');
        Route::post('/clubes/{club}/venta', [ClubController::class, 'vender'])->name('clubes.venta');

        Route::get('/clubes/{club}/temporadas/{temporada}/tarjetones', [TarjetonDigitalController::class, 'index'])
            ->name('clubes.temporadas.tarjetones.index');
        Route::post('/clubes/{club}/temporadas/{temporada}/tarjetones', [TarjetonDigitalController::class, 'store'])
            ->name('clubes.temporadas.tarjetones.store');
        Route::get('/clubes/{club}/temporadas/{temporada}/tarjetones/{version}', [TarjetonDigitalController::class, 'show'])
            ->whereNumber('version')
            ->name('clubes.temporadas.tarjetones.show');

        Route::get('/equipos/{equipo}', [EquipoController::class, 'show'])->name('equipos.show');
        Route::post('/equipos', [EquipoController::class, 'store'])->name('equipos.store');
        Route::get('/jugadores', [JugadorController::class, 'index'])->name('jugadores.index');
        Route::get('/jugadores/create', [JugadorController::class, 'create'])->name('jugadores.create');
        Route::post('/jugadores', [JugadorController::class, 'store'])->name('jugadores.store');
        Route::post('/equipos/{equipo}/jugadores', [JugadorController::class, 'store'])->name('equipos.jugadores.store');
        Route::get('/jugadores/{jugador}/gestionar', [JugadorController::class, 'gestion'])->name('jugadores.gestion');
        Route::get('/jugadores/vincular', [JugadorController::class, 'vincularForm'])->name('jugadores.vincular.form');
        Route::post('/jugadores/{jugador}/baja', [JugadorController::class, 'darDeBaja'])->name('jugadores.baja');
        Route::post('/jugadores/{jugador}/renovar', [JugadorController::class, 'renovar'])->name('jugadores.renovar');
        Route::post('/jugadores/{jugador}/transferir', [JugadorController::class, 'transferir'])->name('jugadores.transferir');
        Route::put('/jugadores/{jugador}/estado', [JugadorController::class, 'cambiarEstado'])->name('jugadores.estado.update');
        Route::post('/jugadores/vincular', [JugadorController::class, 'vincular'])
            ->middleware('throttle:jugador-vinculacion')->name('jugadores.vincular');
        Route::get('/jugadores/{jugador}', [JugadorController::class, 'show'])->name('jugadores.show');
        Route::put('/jugadores/{jugador}', [JugadorController::class, 'update'])->name('jugadores.update');
        Route::get('/jugadores/{jugador}/documentos/{documento}', [JugadorController::class, 'documento'])
            ->name('jugadores.documentos.show');
        Route::delete('/jugadores/{jugador}/vinculo', [JugadorController::class, 'desvincular'])
            ->name('jugadores.vinculo.destroy');
        Route::delete('/admin/jugadores/{usuario}/vinculo', [JugadorController::class, 'desvincularCuenta'])
            ->name('admin.jugadores.vinculo.destroy');
        Route::put('/equipos/{equipo}', [EquipoController::class, 'update'])->name('equipos.update');
    });
});
