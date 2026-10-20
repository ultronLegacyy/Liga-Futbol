<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Verificar que todas las tablas principales del esquema existan.
     */
    public function test_todas_las_tablas_del_esquema_existen(): void
    {
        $tablasRequeridas = [
            // Core y gobernanza
            'ligas',
            'clubes',
            'usuarios',
            'auditoria_logs',
            'asambleas',
            'comunicados_noticias',
            'registro_notificaciones',

            // Estructura deportiva
            'temporadas',
            'convocatorias',
            'categorias',
            'grupos',
            'fases_torneo',

            // Entidades participantes
            'directivos_club',
            'historial_ventas_club',
            'equipos',
            'personas',
            'documentos_persona',
            'jugadores_equipos',
            'arbitros',

            // Logística y programación
            'canchas',
            'bloqueos_cancha',
            'prestamos_cancha',
            'preferencias_equipo',
            'jornadas',
            'partidos',

            // Operación y disciplina
            'cedulas_arbitrales',
            'eventos_partido',
            'catalogo_articulos',
            'expedientes_disciplinarios',
            'sanciones',
            'recursos_inconformidad',

            // Competencia y finanzas
            'tablas_posiciones',
            'llaves_liguilla',
            'cargos_financieros',
            'pagos',
        ];

        foreach ($tablasRequeridas as $tabla) {
            $this->assertTrue(
                Schema::hasTable($tabla),
                "Falta la tabla requerida: {$tabla}"
            );
        }
    }

    /**
     * Verificar que DatabaseSeeder registre datos iniciales consistentes.
     */
    public function test_seeder_puebla_datos_consistentes(): void
    {
        $this->seed(DatabaseSeeder::class);

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
