<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('jugador_documentos')) {
            return;
        }

        if (! Schema::hasTable('documentos_persona')) {
            throw new RuntimeException('No existe documentos_persona para consolidar los documentos anteriores.');
        }

        if (! Schema::hasTable('jugador_documentos_consolidacion')) {
            Schema::create('jugador_documentos_consolidacion', function (Blueprint $table): void {
                $table->unsignedBigInteger('legacy_documento_id')->primary();
                $table->unsignedBigInteger('legacy_jugador_id');
                $table->unsignedBigInteger('documento_persona_id')->unique();
            });
        }

        if (! Schema::hasTable('jugador_documentos_consolidacion_meta')) {
            Schema::create('jugador_documentos_consolidacion_meta', function (Blueprint $table): void {
                $table->unsignedTinyInteger('id')->primary();
                $table->unsignedBigInteger('total_documentos');
            });
        }

        $documentosSinPersona = DB::table('jugador_documentos as jd')
            ->leftJoin('jugadores_equipos as je', 'je.id', '=', 'jd.jugador_id')
            ->whereNull('je.persona_id')
            ->exists();

        if ($documentosSinPersona) {
            throw new RuntimeException('Hay documentos legados sin una inscripción de jugador vinculada; no se eliminará la tabla origen.');
        }

        $totalDocumentosLegados = DB::table('jugador_documentos')->count();
        DB::table('jugador_documentos_consolidacion_meta')->updateOrInsert(
            ['id' => 1],
            ['total_documentos' => $totalDocumentosLegados],
        );

        DB::table('jugador_documentos as jd')
            ->join('jugadores_equipos as je', 'je.id', '=', 'jd.jugador_id')
            ->leftJoin('jugador_documentos_consolidacion as jm', 'jm.legacy_documento_id', '=', 'jd.id')
            ->whereNull('jm.legacy_documento_id')
            ->select([
                'jd.id as legacy_documento_id',
                'jd.jugador_id as legacy_jugador_id',
                'je.persona_id',
                'jd.tipo',
                'jd.disco',
                'jd.ruta_storage',
                'jd.mime_type',
                'jd.tamano_bytes',
                'jd.created_at',
                'jd.updated_at',
            ])
            ->orderBy('jd.id')
            ->chunkById(200, function ($documentos): void {
                foreach ($documentos as $documento) {
                    DB::transaction(function () use ($documento): void {
                        $documentoPersonaId = DB::table('documentos_persona')->insertGetId([
                            'persona_id' => $documento->persona_id,
                            'tipo' => $documento->tipo === 'Fotografia' ? 'Foto' : $documento->tipo,
                            'disco' => $documento->disco,
                            'ruta_storage' => $documento->ruta_storage,
                            'mime_type' => $documento->mime_type,
                            'tamano_bytes' => $documento->tamano_bytes,
                            'created_at' => $documento->created_at,
                            'updated_at' => $documento->updated_at,
                        ]);

                        DB::table('jugador_documentos_consolidacion')->insert([
                            'legacy_documento_id' => $documento->legacy_documento_id,
                            'legacy_jugador_id' => $documento->legacy_jugador_id,
                            'documento_persona_id' => $documentoPersonaId,
                        ]);
                    });
                }
            }, 'jd.id', 'legacy_documento_id');

        $documentosConsolidados = DB::table('jugador_documentos_consolidacion')->count();

        if ($documentosConsolidados !== $totalDocumentosLegados) {
            throw new RuntimeException('La consolidación no verificó todas las filas; se conservará la tabla origen.');
        }

        Schema::drop('jugador_documentos');
    }

    public function down(): void
    {
        if (! Schema::hasTable('jugador_documentos_consolidacion')
            || ! Schema::hasTable('jugador_documentos_consolidacion_meta')) {
            throw new RuntimeException('No se puede revertir la consolidación: falta el mapa o su cantidad esperada.');
        }

        $cantidadEsperada = DB::table('jugador_documentos_consolidacion_meta')->where('id', 1)->value('total_documentos');
        $mapeos = DB::table('jugador_documentos_consolidacion')->count();

        if ($cantidadEsperada === null || $mapeos !== (int) $cantidadEsperada) {
            throw new RuntimeException('No se puede revertir la consolidación: el mapa de documentos está incompleto.');
        }

        if (DB::table('jugador_documentos_consolidacion as jm')
            ->leftJoin('documentos_persona as dp', 'dp.id', '=', 'jm.documento_persona_id')
            ->whereNull('dp.id')
            ->exists()) {
            throw new RuntimeException('No se puede revertir la consolidación: faltan documentos destino.');
        }

        if (DB::table('jugador_documentos_consolidacion as jm')
            ->leftJoin('jugadores_equipos as je', 'je.id', '=', 'jm.legacy_jugador_id')
            ->whereNull('je.id')
            ->exists()) {
            throw new RuntimeException('No se puede revertir la consolidación: faltan inscripciones de jugador origen.');
        }

        if (! Schema::hasTable('jugador_documentos')) {
            Schema::create('jugador_documentos', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('jugador_id')->constrained('jugadores_equipos')->cascadeOnDelete();
                $table->enum('tipo', ['INE', 'CURP', 'Fotografia', 'Comprobante']);
                $table->string('disco')->default('private');
                $table->string('ruta_storage');
                $table->string('mime_type', 100);
                $table->unsignedBigInteger('tamano_bytes');
                $table->timestamps();
                $table->unique(['jugador_id', 'tipo']);
            });
        }

        $documentos = DB::table('jugador_documentos_consolidacion as jm')
            ->join('documentos_persona as dp', 'dp.id', '=', 'jm.documento_persona_id')
            ->get([
                'jm.legacy_documento_id',
                'jm.legacy_jugador_id',
                'dp.tipo',
                'dp.disco',
                'dp.ruta_storage',
                'dp.mime_type',
                'dp.tamano_bytes',
                'dp.created_at',
                'dp.updated_at',
            ]);

        foreach ($documentos as $documento) {
            if (! in_array($documento->tipo, ['INE', 'CURP', 'Foto', 'Comprobante', 'Fotografia'], true)) {
                throw new RuntimeException('No se puede revertir la consolidación: existe un tipo de documento no representable.');
            }

            $legacyTipo = $documento->tipo === 'Foto' ? 'Fotografia' : $documento->tipo;
            $existente = DB::table('jugador_documentos')
                ->where(function ($query) use ($documento): void {
                    $query->where('id', $documento->legacy_documento_id)
                        ->orWhere(function ($query) use ($documento): void {
                            $query->where('jugador_id', $documento->legacy_jugador_id)
                                ->where('tipo', $documento->tipo === 'Foto' ? 'Fotografia' : $documento->tipo);
                        });
                })
                ->first();

            if ($existente !== null && ((int) $existente->id !== (int) $documento->legacy_documento_id
                || (int) $existente->jugador_id !== (int) $documento->legacy_jugador_id
                || $existente->tipo !== $legacyTipo
                || $existente->disco !== $documento->disco
                || $existente->ruta_storage !== $documento->ruta_storage
                || $existente->mime_type !== $documento->mime_type
                || (int) $existente->tamano_bytes !== (int) $documento->tamano_bytes
                || (string) $existente->created_at !== (string) $documento->created_at
                || (string) $existente->updated_at !== (string) $documento->updated_at)) {
                throw new RuntimeException('No se puede revertir la consolidación: hay un conflicto con un documento legado existente.');
            }
        }

        DB::transaction(function () use ($documentos, $cantidadEsperada): void {
            foreach ($documentos as $documento) {
                $legacyTipo = $documento->tipo === 'Foto' ? 'Fotografia' : $documento->tipo;
                $existente = DB::table('jugador_documentos')->where('id', $documento->legacy_documento_id)->exists();

                if (! $existente) {
                    DB::table('jugador_documentos')->insert([
                        'id' => $documento->legacy_documento_id,
                        'jugador_id' => $documento->legacy_jugador_id,
                        'tipo' => $legacyTipo,
                        'disco' => $documento->disco,
                        'ruta_storage' => $documento->ruta_storage,
                        'mime_type' => $documento->mime_type,
                        'tamano_bytes' => $documento->tamano_bytes,
                        'created_at' => $documento->created_at,
                        'updated_at' => $documento->updated_at,
                    ]);
                }

                $restaurado = DB::table('jugador_documentos')->where('id', $documento->legacy_documento_id)->first();

                if ($restaurado === null
                    || (int) $restaurado->jugador_id !== (int) $documento->legacy_jugador_id
                    || $restaurado->tipo !== $legacyTipo
                    || $restaurado->disco !== $documento->disco
                    || $restaurado->ruta_storage !== $documento->ruta_storage
                    || $restaurado->mime_type !== $documento->mime_type
                    || (int) $restaurado->tamano_bytes !== (int) $documento->tamano_bytes
                    || (string) $restaurado->created_at !== (string) $documento->created_at
                    || (string) $restaurado->updated_at !== (string) $documento->updated_at) {
                    throw new RuntimeException('La reversión no restauró exactamente los campos del documento legado.');
                }
            }

            $restaurados = DB::table('jugador_documentos_consolidacion as jm')
                ->join('jugador_documentos as jd', 'jd.id', '=', 'jm.legacy_documento_id')
                ->count();

            if ($restaurados !== (int) $cantidadEsperada) {
                throw new RuntimeException('La reversión no restauró todas las filas; se conservarán los documentos consolidados.');
            }

            $ids = DB::table('jugador_documentos_consolidacion')->pluck('documento_persona_id');
            $eliminados = DB::table('documentos_persona')->whereIn('id', $ids)->delete();

            if ($eliminados !== (int) $cantidadEsperada) {
                throw new RuntimeException('La reversión no pudo verificar las filas consolidadas; se conservarán los documentos destino.');
            }
        });

        Schema::drop('jugador_documentos_consolidacion');
        Schema::drop('jugador_documentos_consolidacion_meta');
    }
};
