<?php

namespace App\Support;

use App\Models\Arbitro;
use App\Models\Asamblea;
use App\Models\Cancha;
use App\Models\CargoFinanciero;
use App\Models\CedulaArbitral;
use App\Models\Equipo;
use App\Models\ExpedienteDisciplinario;
use App\Models\Jugador;
use App\Models\Persona;
use App\Models\Sancion;
use App\Models\Temporada;
use App\Models\User;

/**
 * Menú del panel por área (HAB-S7 · S7-T02, IU-02, RNF-29). Cada opción se muestra solo si el rol tiene el permiso
 * o la policy que ya protege su ruta, así el menú nunca ofrece una pantalla que respondería 403.
 * Lo usan el layout del panel (menú lateral y menú inferior en celular) y la pantalla de inicio por rol.
 */
class MenuPanel
{
    // Íconos de 24×24 con trazo; se dibujan en línea, sin recursos externos
    public const ICONOS = [
        'inicio' => 'M3 10.5 12 3l9 7.5V21h-6v-6H9v6H3z',
        'tablero' => 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',
        'escudo' => 'M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z',
        'usuarios' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
        'llave' => 'M15 7a4 4 0 1 1-3.9 5H9v2H7v2H4v-3l6.1-6.1A4 4 0 0 1 15 7z',
        'calendario' => 'M4 5h16v16H4zM4 9h16M8 3v4M16 3v4',
        'cancha' => 'M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12zM12 11a2 2 0 1 0 0-4 2 2 0 0 0 0 4',
        'camiseta' => 'M8 3 3 6l2 5 3-1v11h8V10l3 1 2-5-5-3c-.5 1.5-2 2.5-4 2.5S8.5 4.5 8 3z',
        'alta' => 'M15 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M8.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M19 8v6M22 11h-6',
        'enlace' => 'M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1',
        'credencial' => 'M3 5h18v14H3zM8 11a2 2 0 1 0 0-4 2 2 0 0 0 0 4M5 16c.6-1.8 1.8-3 3-3s2.4 1.2 3 3M14 9h4M14 13h4',
        'historial' => 'M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 7v5l3 2',
        'silbato' => 'M14 9h7v4h-4a6 6 0 1 1-6-6h3zM8 13h.01',
        'cedula' => 'M9 3h6v3H9zM6 5h12v16H6zM9 11h6M9 15h4',
        'papel' => 'M14 3H6v18h12V7zM14 3v4h4M9 13h6M9 17h6',
        'mazo' => 'M14 5l5 5M11 8l5 5M9.5 9.5l-6 6 2 2 6-6M13 21h8',
        'alerta' => 'M12 3 2 21h20zM12 10v5M12 18h.01',
        'monedas' => 'M12 8c4.4 0 8-1.3 8-3s-3.6-3-8-3-8 1.3-8 3 3.6 3 8 3zM4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6',
        'cartera' => 'M3 7h18v13H3zM3 7l3-4h12l3 4M16 13h2',
        'grafica' => 'M4 20V10M10 20V4M16 20v-7M22 20H2',
        'correo' => 'M3 5h18v14H3zM3 6l9 7 9-7',
        'megafono' => 'M3 10v4h4l6 4V6L7 10zM17 9a4 4 0 0 1 0 6',
        'asamblea' => 'M17 20v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M10 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6M21 20v-2a3 3 0 0 0-2-2.8',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'salir' => 'M9 21H5V3h4M16 17l5-5-5-5M21 12H9',
        'portal' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18',
    ];

    /**
     * Áreas con sus opciones visibles para el usuario; las áreas vacías no se devuelven.
     *
     * @return list<array{area: string, opciones: list<array{texto: string, ruta: string, activa: string, icono: string, descripcion: string}>}>
     */
    public static function para(User $usuario): array
    {
        $admin = $usuario->hasRole('Administrador General');

        $areas = [
            'Gobierno' => [
                [$admin, 'Dashboard', 'admin.dashboard', 'admin.dashboard', 'tablero', 'Resumen general del sistema.'],
                [$admin, 'Gobernanza', 'admin.gobernanza', 'admin.gobernanza', 'escudo', 'Consejo directivo y cargos.'],
                [$admin, 'Usuarios', 'admin.usuarios.index', 'admin.usuarios.*', 'usuarios', 'Cuentas y accesos.'],
                [$admin, 'Roles y permisos', 'admin.roles.index', 'admin.roles.*', 'llave', 'Qué puede hacer cada rol.'],
                [$usuario->can('viewAny', Asamblea::class), 'Asambleas', 'asambleas.index', 'asambleas.*', 'asamblea', 'Convocatorias, asistencia y acuerdos.'],
                [$usuario->can('comunicados.enviar'), 'Comunicados', 'comunicados.create', 'comunicados.*', 'megafono', 'Avisos por correo a grupos.'],
                [$usuario->can('correos.ver'), 'Registro de correos', 'correos.index', 'correos.*', 'correo', 'Cada correo enviado por el sistema.'],
            ],
            'Operación deportiva' => [
                [$usuario->hasRole('Árbitro en Campo'), 'Mis partidos', 'cedulas.mis-partidos', 'cedulas.*', 'cedula', 'Tus partidos y su cédula.'],
                [$usuario->can('capturarFisica', CedulaArbitral::class), 'Cédulas en papel', 'cedulas.fisicas.index', 'cedulas.fisica*', 'papel', 'Transcribir cédulas de papel.'],
                [$usuario->can('viewAny', Temporada::class), 'Temporadas', 'temporadas.index', 'temporadas.*', 'calendario', 'Temporadas, categorías y grupos.'],
                [$usuario->can('viewAny', Cancha::class), 'Canchas', 'canchas.index', 'canchas.*', 'cancha', 'Sedes y disponibilidad.'],
                [$usuario->can('viewAny', Jugador::class), 'Jugadores', 'jugadores.index', 'jugadores.index', 'camiseta', 'Padrón de jugadores.'],
                [$usuario->can('jugadores.crear'), 'Registrar jugadores', 'jugadores.create', 'jugadores.create', 'alta', 'Alta de jugadores del club.'],
                [$usuario->can('link', Jugador::class), 'Vincular mi cuenta', 'jugadores.vincular.form', 'jugadores.vincular*', 'enlace', 'Une tu cuenta con tu registro de jugador.'],
                [$usuario->can('viewAny', Persona::class), 'Personas', 'personas.index', 'personas.*', 'credencial', 'Padrón general de personas.'],
                [$usuario->can('viewAnyHistorial', Equipo::class), 'Historial de equipos', 'equipos.historial.index', 'equipos.historial.*', 'historial', 'Cambios de nombre y venta de clubes.'],
                [$usuario->can('viewAny', Arbitro::class), 'Árbitros', 'arbitros.index', 'arbitros.*', 'silbato', 'Colegio arbitral y asignaciones.'],
            ],
            'Disciplina' => [
                [$usuario->can('viewAny', ExpedienteDisciplinario::class), 'Expedientes disciplinarios', 'disciplina.expedientes.index', 'disciplina.expedientes.*', 'mazo', 'Expedientes, audiencias y resoluciones.'],
                [$usuario->can('verBoletin', Sancion::class), 'Boletín de castigados', 'disciplina.boletin.index', 'disciplina.boletin.*', 'alerta', 'Sanciones vigentes y su publicación.'],
            ],
            'Finanzas' => [
                [$usuario->can('viewAny', CargoFinanciero::class), 'Cuotas y cargos', 'cargos.index', 'cargos.*', 'monedas', 'Cuotas, cargos y pagos.'],
                [$usuario->can('viewAny', CargoFinanciero::class), 'Adeudos por club', 'adeudos.index', 'adeudos.*', 'cartera', 'Quién está al corriente.'],
                [$usuario->can('verReporte', CargoFinanciero::class), 'Reporte financiero', 'reportes.financiero', 'reportes.financiero*', 'grafica', 'Cargado, cobrado y saldo por periodo.'],
            ],
        ];

        $menu = [];

        foreach ($areas as $area => $opciones) {
            $visibles = [];

            foreach ($opciones as [$visible, $texto, $ruta, $activa, $icono, $descripcion]) {
                if ($visible) {
                    $visibles[] = ['texto' => $texto, 'ruta' => $ruta, 'activa' => $activa, 'icono' => $icono, 'descripcion' => $descripcion];
                }
            }

            if ($visibles !== []) {
                $menu[] = ['area' => $area, 'opciones' => $visibles];
            }
        }

        return $menu;
    }

    /**
     * Las primeras opciones del usuario, para el menú inferior del celular.
     *
     * @return list<array{texto: string, ruta: string, activa: string, icono: string, descripcion: string}>
     */
    public static function frecuentes(User $usuario, int $cuantas = 3): array
    {
        $todas = [];

        foreach (self::para($usuario) as $area) {
            foreach ($area['opciones'] as $opcion) {
                $todas[] = $opcion;
            }
        }

        return array_slice($todas, 0, $cuantas);
    }
}
