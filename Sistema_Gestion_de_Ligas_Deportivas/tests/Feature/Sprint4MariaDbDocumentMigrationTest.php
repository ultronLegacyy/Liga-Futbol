<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class Sprint4MariaDbDocumentMigrationTest extends TestCase
{
    public function test_maria_db_revierte_multiples_documentos_con_timestamps_colisiones_y_mapa_incompleto(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('sgld_scrum31_testing', DB::connection()->getDatabaseName());
        $this->assertFalse(Schema::hasTable('jugador_documentos'));
        $this->assertTrue(Schema::hasTable('jugador_documentos_consolidacion'));
        $this->assertTrue(Schema::hasTable('jugador_documentos_consolidacion_meta'));
        $this->limpiarFixturesInterrumpidos();

        $timestamp = now();
        $ligaId = DB::table('ligas')->insertGetId([
            'nombre' => 'Liga migracion MariaDB '.str()->uuid(),
            'estado' => 'activa',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $temporadaId = DB::table('temporadas')->insertGetId([
            'liga_id' => $ligaId,
            'nombre' => 'Temporada migracion MariaDB',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'estado' => 'activa',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $categoriaId = DB::table('categorias')->insertGetId([
            'nombre' => 'Categoria migracion MariaDB',
            'edad_min' => 10,
            'edad_max' => 20,
            'jerarquia_salto' => 1,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $grupoId = DB::table('grupos')->insertGetId([
            'categoria_id' => $categoriaId,
            'temporada_id' => $temporadaId,
            'nombre' => 'Grupo migracion MariaDB',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $clubId = DB::table('clubes')->insertGetId([
            'nombre' => 'Club migracion MariaDB '.str()->uuid(),
            'estado' => 'activo',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $equipoId = DB::table('equipos')->insertGetId([
            'club_id' => $clubId,
            'grupo_id' => $grupoId,
            'nombre' => 'Equipo migracion MariaDB',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $fixtures = [];
        $documentIds = [];
        $nextLegacyId = (int) DB::table('jugador_documentos_consolidacion')->max('legacy_documento_id');
        $personaIds = [];
        $jugadorIds = [];
        $migracion = require database_path('migrations/2026_11_07_120001_remove_legacy_jugador_documentos_table.php');

        foreach (['INE', 'CURP'] as $indice => $tipo) {
            $personaId = DB::table('personas')->insertGetId([
                'nombre' => 'Persona de migracion',
                'apellidos' => $tipo,
                'fecha_nacimiento' => '2000-01-01',
                'curp' => strtoupper(substr(str_replace('-', '', (string) str()->uuid()), 0, 18)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $personaIds[] = $personaId;
            $jugadorId = DB::table('jugadores_equipos')->insertGetId([
                'persona_id' => $personaId,
                'equipo_id' => $equipoId,
                'estado_elegibilidad' => 'Activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $jugadorIds[] = $jugadorId;
            $createdAt = now()->subDays(20 + $indice)->startOfSecond();
            $updatedAt = now()->subDays(10 + $indice)->startOfSecond();
            $fixtures[] = [
                'id' => $nextLegacyId + $indice + 1,
                'jugador_id' => $jugadorId,
                'tipo' => $tipo,
                'disco' => 'private',
                'ruta_storage' => 'maria-migration/'.$personaId.'/'.$tipo.'.pdf',
                'mime_type' => 'application/pdf',
                'tamano_bytes' => 200 + $indice,
                'created_at' => $createdAt->toDateTimeString(),
                'updated_at' => $updatedAt->toDateTimeString(),
            ];
        }

        try {
            Schema::create('jugador_documentos', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('jugador_id');
                $table->string('tipo');
                $table->string('disco');
                $table->string('ruta_storage');
                $table->string('mime_type');
                $table->unsignedBigInteger('tamano_bytes');
                $table->timestamps();
                $table->unique(['jugador_id', 'tipo']);
            });

            $mapeados = DB::table('jugador_documentos_consolidacion as jm')
                ->join('documentos_persona as dp', 'dp.id', '=', 'jm.documento_persona_id')
                ->get(['jm.legacy_documento_id', 'jm.legacy_jugador_id', 'dp.tipo', 'dp.disco', 'dp.ruta_storage', 'dp.mime_type', 'dp.tamano_bytes', 'dp.created_at', 'dp.updated_at']);

            foreach ($mapeados as $documento) {
                DB::table('jugador_documentos')->insert([
                    'id' => $documento->legacy_documento_id,
                    'jugador_id' => $documento->legacy_jugador_id,
                    'tipo' => $documento->tipo === 'Foto' ? 'Fotografia' : $documento->tipo,
                    'disco' => $documento->disco,
                    'ruta_storage' => $documento->ruta_storage,
                    'mime_type' => $documento->mime_type,
                    'tamano_bytes' => $documento->tamano_bytes,
                    'created_at' => $documento->created_at,
                    'updated_at' => $documento->updated_at,
                ]);
            }

            foreach ($fixtures as $fixture) {
                DB::table('jugador_documentos')->insert($fixture);
                $documentIds[] = $fixture['id'];
            }

            $migracion->up();
            $this->assertFalse(Schema::hasTable('jugador_documentos'));

            $mapeoIncompleto = DB::table('jugador_documentos_consolidacion')->where('legacy_documento_id', $documentIds[0])->firstOrFail();
            DB::table('jugador_documentos_consolidacion')->where('legacy_documento_id', $documentIds[0])->delete();
            try {
                $migracion->down();
                $this->fail('El rollback debe rechazar un mapa incompleto.');
            } catch (RuntimeException) {
                $this->assertSame(2, count($fixtures));
            }
            DB::table('jugador_documentos_consolidacion')->insert((array) $mapeoIncompleto);

            Schema::create('jugador_documentos', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('jugador_id');
                $table->string('tipo');
                $table->string('disco');
                $table->string('ruta_storage');
                $table->string('mime_type');
                $table->unsignedBigInteger('tamano_bytes');
                $table->timestamps();
                $table->unique(['jugador_id', 'tipo']);
            });
            $first = $fixtures[0];
            DB::table('jugador_documentos')->insert([...$first, 'ruta_storage' => 'colision-distinta.pdf']);
            try {
                $migracion->down();
                $this->fail('El rollback debe rechazar una colision de contenido.');
            } catch (RuntimeException) {
                $this->assertSame(2, (int) DB::table('documentos_persona')->whereIn('id', DB::table('jugador_documentos_consolidacion')->select('documento_persona_id'))->whereIn('ruta_storage', array_column($fixtures, 'ruta_storage'))->count());
            }
            Schema::drop('jugador_documentos');

            $migracion->down();
            foreach ($fixtures as $fixture) {
                $restaurado = DB::table('jugador_documentos')->where('id', $fixture['id'])->firstOrFail();
                foreach ($fixture as $campo => $valor) {
                    $this->assertSame((string) $valor, (string) $restaurado->{$campo}, $campo.' debe restaurarse exactamente.');
                }
            }

            $migracion->up();
            foreach ($fixtures as $fixture) {
                $this->assertDatabaseHas('documentos_persona', [
                    'id' => DB::table('jugador_documentos_consolidacion')->where('legacy_documento_id', $fixture['id'])->value('documento_persona_id'),
                    'ruta_storage' => $fixture['ruta_storage'],
                    'created_at' => $fixture['created_at'],
                    'updated_at' => $fixture['updated_at'],
                ]);
            }

            DB::table('documentos_persona')->whereIn('id', DB::table('jugador_documentos_consolidacion')->whereIn('legacy_documento_id', $documentIds)->select('documento_persona_id'))->delete();
            DB::table('jugador_documentos_consolidacion')->whereIn('legacy_documento_id', $documentIds)->delete();
            DB::table('jugador_documentos_consolidacion_meta')->where('id', 1)->update(['total_documentos' => DB::table('jugador_documentos_consolidacion')->count()]);
        } finally {
            if (Schema::hasTable('jugador_documentos')) {
                if (! Schema::hasTable('jugador_documentos_consolidacion')
                    || ! Schema::hasTable('jugador_documentos_consolidacion_meta')) {
                    $migracion->up();
                } else {
                    Schema::drop('jugador_documentos');
                }
            }

            if (Schema::hasTable('jugador_documentos_consolidacion')) {
                $fixtureDocumentoIds = DB::table('jugador_documentos_consolidacion')
                    ->whereIn('legacy_documento_id', $documentIds)
                    ->pluck('documento_persona_id');
                DB::table('documentos_persona')->whereIn('id', $fixtureDocumentoIds)->delete();
                DB::table('jugador_documentos_consolidacion')->whereIn('legacy_documento_id', $documentIds)->delete();
                DB::table('jugador_documentos_consolidacion_meta')->where('id', 1)->update([
                    'total_documentos' => DB::table('jugador_documentos_consolidacion')->count(),
                ]);
            }

            DB::table('jugadores_equipos')->whereIn('id', $jugadorIds)->delete();
            DB::table('personas')->whereIn('id', $personaIds)->delete();
            DB::table('equipos')->where('id', $equipoId)->delete();
            DB::table('clubes')->where('id', $clubId)->delete();
            DB::table('grupos')->where('id', $grupoId)->delete();
            DB::table('categorias')->where('id', $categoriaId)->delete();
            DB::table('temporadas')->where('id', $temporadaId)->delete();
            DB::table('ligas')->where('id', $ligaId)->delete();
        }
    }

    private function limpiarFixturesInterrumpidos(): void
    {
        $ligaIds = DB::table('ligas')->where('nombre', 'like', 'Liga migracion MariaDB %')->pluck('id');
        $temporadaIds = DB::table('temporadas')->whereIn('liga_id', $ligaIds)->pluck('id');
        $categoriaIds = DB::table('categorias')->where('nombre', 'Categoria migracion MariaDB')->pluck('id');
        $grupoIds = DB::table('grupos')->whereIn('categoria_id', $categoriaIds)->whereIn('temporada_id', $temporadaIds)->pluck('id');
        $clubIds = DB::table('clubes')->where('nombre', 'like', 'Club migracion MariaDB %')->pluck('id');
        $equipoIds = DB::table('equipos')->where('nombre', 'Equipo migracion MariaDB')->whereIn('club_id', $clubIds)->whereIn('grupo_id', $grupoIds)->pluck('id');
        $jugadorIds = DB::table('jugadores_equipos')->whereIn('equipo_id', $equipoIds)->pluck('id');
        $personaIds = DB::table('jugadores_equipos')->whereIn('id', $jugadorIds)->pluck('persona_id');

        if ($jugadorIds->isEmpty()) {
            return;
        }

        DB::table('documentos_persona')->whereIn('persona_id', $personaIds)->delete();
        DB::table('jugadores_equipos')->whereIn('id', $jugadorIds)->delete();
        DB::table('personas')->whereIn('id', $personaIds)->delete();
        DB::table('equipos')->whereIn('id', $equipoIds)->delete();
        DB::table('clubes')->whereIn('id', $clubIds)->delete();
        DB::table('grupos')->whereIn('id', $grupoIds)->delete();
        DB::table('categorias')->whereIn('id', $categoriaIds)->delete();
        DB::table('temporadas')->whereIn('id', $temporadaIds)->delete();
        DB::table('ligas')->whereIn('id', $ligaIds)->delete();
    }
}
