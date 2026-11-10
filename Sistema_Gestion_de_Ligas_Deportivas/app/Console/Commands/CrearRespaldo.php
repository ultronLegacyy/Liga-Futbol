<?php

namespace App\Console\Commands;

use App\Services\Respaldos\AlmacenRespaldos;
use App\Services\Respaldos\CreadorRespaldo;
use App\Services\Respaldos\RespaldoException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// Respaldo diario o completo, cifrado y subido al disco configurado (HU-009, criterios 1 a 3)
class CrearRespaldo extends Command
{
    protected $signature = 'respaldo:crear {--completo : Respaldo completo de la base y de todos los documentos}';

    protected $description = 'Crea un respaldo cifrado de la base y los documentos: diario (solo los documentos que cambiaron) o completo';

    public function handle(CreadorRespaldo $creador, AlmacenRespaldos $almacen): int
    {
        // En producción un disco local no cumple el criterio 3, pero el respaldo se crea igual
        $aviso = $almacen->avisoDiscoLocal();

        if ($aviso !== null) {
            $this->warn($aviso);
            Log::warning('Respaldo en un disco local en producción', ['disco' => $almacen->nombreDisco()]);
        }

        try {
            $respaldo = $creador->crear((bool) $this->option('completo'));
        } catch (\Throwable $error) {
            $mensaje = $error instanceof RespaldoException ? $error->getMessage() : 'Error inesperado: '.RespaldoException::sinSecretos($error->getMessage());
            $this->error('No se creó el respaldo. '.$mensaje);
            Log::error('Respaldo no creado', ['motivo' => $mensaje]);

            return self::FAILURE;
        }

        if ($respaldo['aviso'] !== null) {
            $this->warn($respaldo['aviso']);
        }

        $detalle = $respaldo['tipo'] === 'diario'
            ? "la base completa y {$respaldo['documentos']} documentos que cambiaron desde {$respaldo['depende_de']}"
            : "la base completa y {$respaldo['documentos']} documentos";

        $this->info("Respaldo {$respaldo['nombre']} creado en el disco «".config('respaldos.disco')."»: {$detalle}.");
        Log::info('Respaldo creado', ['respaldo' => $respaldo['nombre'], 'documentos' => $respaldo['documentos'], 'aviso' => $respaldo['aviso']]);

        return self::SUCCESS;
    }
}
