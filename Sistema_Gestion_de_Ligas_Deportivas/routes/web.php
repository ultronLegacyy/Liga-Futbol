<?php

use App\Http\Controllers\AdeudoClubController;
use App\Http\Controllers\ArbitroController;
use App\Http\Controllers\AsambleaController;
use App\Http\Controllers\CanchaBloqueoController;
use App\Http\Controllers\CanchaController;
use App\Http\Controllers\CargoFinancieroController;
use App\Http\Controllers\CedulaController;
use App\Http\Controllers\AudienciaDisciplinariaController;
use App\Http\Controllers\BoletinCastigadosController;
use App\Http\Controllers\CedulaFisicaController;
use App\Http\Controllers\CierreCedulaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\ComunicadoController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\EstadisticasGrupoController;
use App\Http\Controllers\ExpedienteDisciplinarioController;
use App\Http\Controllers\ForcePasswordChangeController;
use App\Http\Controllers\GeneradorEnfrentamientosController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\HistorialEquipoController;
use App\Http\Controllers\JornadaController;
use App\Http\Controllers\LiguillaController;
use App\Http\Controllers\JugadorController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PartidoController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\RecuperacionContrasenaController;
use App\Http\Controllers\RegistroCorreoController;
use App\Http\Controllers\ReporteFinancieroController;
use App\Http\Controllers\ReglaTemporadaController;
use App\Http\Controllers\ResultadoController;
use App\Http\Controllers\RolPermisoController;
use App\Http\Controllers\SancionController;
use App\Http\Controllers\TablaPosicionesController;
use App\Http\Controllers\TarjetonDigitalController;
use App\Http\Controllers\TemporadaController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\ForcePasswordChange;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('auth.login');
})->name('inicio');

// Portal público (RF-12.3, IU-01): sin cuenta y sin privilegios de edición. 300 consultas por minuto por IP,
// porque en 4G muchos aficionados comparten la misma IP pública y HU-077 pide 200 usuarios simultáneos
Route::get('/programacion', [PortalController::class, 'programacion'])
    ->middleware('throttle:300,1')
    ->name('portal.programacion');

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

        // Programación de jornadas y descansos por grupo (HU-027, HU-028, HU-030, RF-06.1, RF-06.2, RF-06.5)
        Route::resource('grupos.jornadas', JornadaController::class)->shallow()->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
        Route::match(['put', 'patch'], '/jornadas/{jornada}/descanso', [JornadaController::class, 'actualizarDescanso'])
            ->name('jornadas.descanso.update');
        Route::post('/jornadas/{jornada}/enviar-revision', [JornadaController::class, 'enviarRevision'])
            ->name('jornadas.enviar-revision');
        Route::post('/jornadas/{jornada}/aprobar', [JornadaController::class, 'aprobar'])
            ->name('jornadas.aprobar');
        Route::post('/jornadas/{jornada}/publicar', [JornadaController::class, 'publicar'])
            ->name('jornadas.publicar');
        Route::get('/jornadas/{jornada}/conflictos', [JornadaController::class, 'conflictos'])
            ->name('jornadas.conflictos');

        // Tabla de posiciones calculada con los resultados oficiales (HU-041, RF-09.1)
        Route::get('/grupos/{grupo}/tabla', [TablaPosicionesController::class, 'show'])
            ->whereNumber('grupo')
            ->name('grupos.tabla');
        // Goleo, tarjetas, sanciones y tabla histórica por jornada (HU-043, RF-09.3)
        Route::get('/grupos/{grupo}/estadisticas', [EstadisticasGrupoController::class, 'show'])
            ->whereNumber('grupo')
            ->name('grupos.estadisticas');
        // Clasificación a la liguilla y llaves desde la tabla final (HU-055, RF-11.1)
        Route::get('/grupos/{grupo}/liguilla', [LiguillaController::class, 'show'])
            ->whereNumber('grupo')
            ->name('grupos.liguilla');
        Route::post('/grupos/{grupo}/liguilla', [LiguillaController::class, 'store'])
            ->whereNumber('grupo')
            ->name('grupos.liguilla.store');
        // Rondas de la liguilla: avances, eliminaciones, campeón y subcampeón (HU-056, RF-11.2)
        Route::post('/grupos/{grupo}/liguilla/rondas', [LiguillaController::class, 'crearRonda'])
            ->whereNumber('grupo')
            ->name('grupos.liguilla.rondas');

        // Generación automática de rol regular y enfrentamientos (HU-032, RF-07.1)
        Route::get('/grupos/{grupo}/generar-rol', [GeneradorEnfrentamientosController::class, 'configurar'])
            ->name('grupos.generar-rol');
        Route::post('/grupos/{grupo}/previsualizar-rol', [GeneradorEnfrentamientosController::class, 'previsualizar'])
            ->name('grupos.previsualizar-rol');
        Route::post('/grupos/{grupo}/confirmar-rol', [GeneradorEnfrentamientosController::class, 'confirmar'])
            ->name('grupos.confirmar-rol');
        Route::post('/jornadas/{jornada}/generar-partidos', [GeneradorEnfrentamientosController::class, 'generarJornada'])
            ->name('jornadas.generar-partidos');

        // Alta manual, reprogramación, gestión de estados e historial de partidos (HU-027, HU-029, HU-031, RF-06.1, RF-06.3, RF-06.4, RF-06.6)
        Route::get('/partidos/{partido}/reprogramar', [PartidoController::class, 'formularioReprogramar'])
            ->name('partidos.reprogramar');
        Route::post('/partidos/{partido}/reprogramar', [PartidoController::class, 'reprogramar'])
            ->name('partidos.reprogramar.store');
        Route::patch('/partidos/{partido}/estado', [PartidoController::class, 'cambiarEstado'])
            ->name('partidos.estado.update');
        Route::get('/partidos/{partido}/historial', [PartidoController::class, 'historial'])
            ->name('partidos.historial');
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
        // HU-075: reporte arbitral, anulaciones, firmas de los capitanes y cierre
        Route::put('/partidos/{partido}/cedula/informe', [CedulaController::class, 'informe'])
            ->whereNumber('partido')
            ->name('cedulas.informe');
        Route::delete('/partidos/{partido}/cedula/eventos/{evento}', [CedulaController::class, 'anularEvento'])
            ->whereNumber(['partido', 'evento'])
            ->name('cedulas.eventos.anular');
        Route::delete('/partidos/{partido}/cedula/incidencias/{incidencia}', [CedulaController::class, 'anularIncidencia'])
            ->whereNumber(['partido', 'incidencia'])
            ->name('cedulas.incidencias.anular');
        Route::get('/partidos/{partido}/cedula/cierre', [CierreCedulaController::class, 'resumen'])
            ->whereNumber('partido')
            ->name('cedulas.cierre');
        Route::post('/partidos/{partido}/cedula/firmas/{lado}', [CierreCedulaController::class, 'firmar'])
            ->whereNumber('partido')
            ->whereIn('lado', ['local', 'visitante'])
            ->name('cedulas.firmar');
        Route::get('/partidos/{partido}/cedula/firmas/{lado}', [CierreCedulaController::class, 'firma'])
            ->whereNumber('partido')
            ->whereIn('lado', ['local', 'visitante'])
            ->name('cedulas.firma');
        Route::post('/partidos/{partido}/cedula/cerrar', [CierreCedulaController::class, 'cerrar'])
            ->whereNumber('partido')
            ->name('cedulas.cerrar');

        // HU-037: el Capturista transcribe en la sede las cédulas que llegan en papel, con las validaciones de HU-036
        Route::get('/cedulas/papel', [CedulaFisicaController::class, 'index'])
            ->name('cedulas.fisicas.index');
        Route::get('/partidos/{partido}/cedula/papel', [CedulaFisicaController::class, 'create'])
            ->whereNumber('partido')
            ->name('cedulas.fisica.create');
        Route::post('/partidos/{partido}/cedula/papel', [CedulaFisicaController::class, 'store'])
            ->whereNumber('partido')
            ->name('cedulas.fisica.store');
        Route::post('/partidos/{partido}/cedula/papel/cerrar', [CedulaFisicaController::class, 'cerrar'])
            ->whereNumber('partido')
            ->name('cedulas.fisica.cerrar');
        Route::get('/partidos/{partido}/cedula/papel/escaneo', [CedulaFisicaController::class, 'escaneo'])
            ->whereNumber('partido')
            ->name('cedulas.fisica.escaneo');

        // Régimen disciplinario y Tribunal de Penas: expedientes y presuntos artículos (HU-044 / RF-10.1)
        Route::get('/disciplina/expedientes', [ExpedienteDisciplinarioController::class, 'index'])
            ->name('disciplina.expedientes.index');
        Route::get('/disciplina/expedientes/{expediente}', [ExpedienteDisciplinarioController::class, 'show'])
            ->whereNumber('expediente')
            ->name('disciplina.expedientes.show');
        Route::post('/disciplina/expedientes/{expediente}/articulos', [ExpedienteDisciplinarioController::class, 'asociarArticulo'])
            ->whereNumber('expediente')
            ->name('disciplina.expedientes.articulos.store');
        Route::delete('/disciplina/expedientes/{expediente}/articulos/{articulo}', [ExpedienteDisciplinarioController::class, 'desasociarArticulo'])
            ->whereNumber(['expediente', 'articulo'])
            ->name('disciplina.expedientes.articulos.destroy');

        // Validación oficial de resultados: sin validar no cuentan para la tabla (HU-040, RF-08.4).
        // Autoriza CedulaArbitralPolicy::validarResultados, que responde el 403 en español
        Route::get('/resultados', [ResultadoController::class, 'index'])
            ->name('resultados.index');
        Route::post('/resultados/{cedula}/validar', [ResultadoController::class, 'validar'])
            ->whereNumber('cedula')
            ->name('resultados.validar');

        // Audiencias disciplinarias (HU-045 / RF-10.2)
        Route::post('/disciplina/expedientes/{expediente}/audiencias', [AudienciaDisciplinariaController::class, 'store'])
            ->whereNumber('expediente')
            ->name('disciplina.expedientes.audiencias.store');

        // Dictamen y validación de sanciones (HU-045, HU-046 / RF-10.2, RF-10.3)
        Route::post('/disciplina/expedientes/{expediente}/sanciones', [SancionController::class, 'store'])
            ->whereNumber('expediente')
            ->name('disciplina.expedientes.sanciones.store');
        Route::post('/disciplina/sanciones/{sancion}/validar', [SancionController::class, 'validar'])
            ->whereNumber('sancion')
            ->name('disciplina.sanciones.validar');
        Route::post('/disciplina/sanciones/{sancion}/publicar-boletin', [SancionController::class, 'publicarBoletin'])
            ->whereNumber('sancion')
            ->name('disciplina.sanciones.publicar-boletin');

        // Boletín Oficial de Castigados (HU-046 / RF-10.3, RNF-44)
        Route::get('/disciplina/boletin', [BoletinCastigadosController::class, 'index'])
            ->name('disciplina.boletin.index');
        Route::post('/disciplina/boletin/publicar', [BoletinCastigadosController::class, 'publicar'])
            ->name('disciplina.boletin.publicar');
        Route::post('/disciplina/sanciones/{sancion}/despublicar', [BoletinCastigadosController::class, 'despublicar'])
            ->whereNumber('sancion')
            ->name('disciplina.sanciones.despublicar');

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

        // Reporte financiero por periodo, club, temporada y concepto, y su descarga (HU-052, RF-13.3)
        Route::get('/reportes/financiero', [ReporteFinancieroController::class, 'index'])
            ->name('reportes.financiero');
        Route::get('/reportes/financiero/csv', [ReporteFinancieroController::class, 'csv'])
            ->name('reportes.financiero.csv');
        Route::get('/reportes/financiero/pdf', [ReporteFinancieroController::class, 'pdf'])
            ->name('reportes.financiero.pdf');

        // HU-067: registro de cada correo del sistema, solo para quien tiene correos.ver
        Route::get('/correos', [RegistroCorreoController::class, 'index'])
            ->middleware('permission:correos.ver')
            ->name('correos.index');

        // HU-066: comunicados por correo a grupos de usuarios; antes de enviar se revisa cuántos destinatarios tiene el grupo
        Route::middleware('permission:comunicados.enviar')->group(function () {
            Route::get('/comunicados/crear', [ComunicadoController::class, 'create'])->name('comunicados.create');
            Route::post('/comunicados/revisar', [ComunicadoController::class, 'revisar'])->name('comunicados.revisar');
            Route::post('/comunicados', [ComunicadoController::class, 'store'])->name('comunicados.store');
        });

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

            // Permisos de cada rol: consulta (HU-081) y edición (HU-083), RF-12.1; crear, renombrar o borrar roles se rechaza
            Route::get('/roles', [RolPermisoController::class, 'index'])->name('roles.index');
            Route::get('/roles/{rol}', [RolPermisoController::class, 'show'])->whereNumber('rol')->name('roles.show');
            // HU-083: edición de los permisos de un rol (va antes de las rutas que rechazan cambios)
            Route::get('/roles/{rol}/editar', [RolPermisoController::class, 'edit'])->whereNumber('rol')->name('roles.edit');
            Route::put('/roles/{rol}/permisos', [RolPermisoController::class, 'update'])->whereNumber('rol')->name('roles.permisos.update');
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
        Route::get('/jugadores/{jugador}/historial', [JugadorController::class, 'historial'])->name('jugadores.historial');
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
