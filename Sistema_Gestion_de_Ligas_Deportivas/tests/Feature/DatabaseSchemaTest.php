<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class DatabaseSchemaTest extends TestCase
{
    /**
     * Verificar que todas las tablas de BASE_DE_DATOS.md existan en la base de datos.
     */
    public function test_todas_las_tablas_del_esquema_existen(): void
    {
        $tablasRequeridas = [
            // Core y Gobernanza
            'ligas', 'clubes', 'usuarios', 'auditoria_logs', 'asambleas', 'comunicados_noticias', 'registro_notificaciones',
            // Estructura Deportiva
            'temporadas', 'convocatorias', 'categorias', 'grupos', 'fases_torneo',
            // Entidades Participantes
            'directivos_club', 'historial_ventas_club', 'equipos', 'personas', 'documentos_persona', 'jugadores_equipos', 'arbitros',
            // Logistica y Programacion
            'canchas', 'bloqueos_cancha', 'prestamos_cancha', 'preferencias_equipo', 'jornadas', 'partidos',
            // Operacion y Disciplina
            'cedulas_arbitrales', 'eventos_partido', 'catalogo_articulos', 'expedientes_disciplinarios', 'sanciones', 'recursos_inconformidad',
            // Competencia y Finanzas
            'tablas_posiciones', 'llaves_liguilla', 'cargos_financieros', 'pagos'
        ];

        foreach ($tablasRequeridas as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla requerida: {$tabla}");
        }
    }

    /**
     * Verificar que el Seeder poblara datos consistentes en las entidades clave.
     */
    public function test_seeder_puebla_datos_consistentes(): void
    {
        $this->assertDatabaseHas('ligas', [
            'nombre' => 'Liga Municipal de Futbol Amateur de Morelia, A.C.',
            'estado' => 'activa',
        ]);

        $this->assertDatabaseHas('usuarios', [
            'email' => 'admin@ligafutbol.com',
            'rol' => 'Administrador General',
        ]);

        $this->assertDatabaseHas('temporadas', [
            'nombre' => 'Temporada 2026-2027',
            'estado' => 'activa',
        ]);

        $this->assertDatabaseHas('clubes', [
            'nombre' => 'Club Deportivo Morelia',
            'estado' => 'activo',
        ]);

        $this->assertDatabaseHas('cedulas_arbitrales', [
            'goles_local' => 2,
            'goles_visitante' => 1,
        ]);
    }
}
