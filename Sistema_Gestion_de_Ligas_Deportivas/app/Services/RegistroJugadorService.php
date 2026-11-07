<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\Jugador;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class RegistroJugadorService
{
    public function __construct(
        private ReglaSaltoCategoriaService $reglaSaltoCategoria,
        private AuditService $auditoria,
    ) {
    }

    /** @param array{persona_id: int|string, dorsal?: int|string|null, documentos?: array<string, UploadedFile>} $datos */
    public function registrar(Equipo $equipoDestino, array $datos): Jugador
    {
        return $this->auditoria->conMotivo('Inscripción de jugador en equipo', function () use ($equipoDestino, $datos): Jugador {
            $documentos = $datos['documentos'] ?? [];
            unset($datos['documentos']);
            $inscripcionAnterior = Jugador::query()
                ->where('persona_id', $datos['persona_id'])
                ->with('equipo.grupo.categoria')
                ->latest('id')
                ->first();

            if ($inscripcionAnterior !== null) {
                $equipoOrigen = $inscripcionAnterior->equipo;
                $categoriaOrigen = $equipoOrigen->grupo->categoria;
                $categoriaDestino = $equipoDestino->grupo->categoria;

                $this->reglaSaltoCategoria->validar(
                    $categoriaOrigen,
                    $categoriaDestino,
                );
            }

            $jugador = $equipoDestino->jugadores()->create($datos);

            foreach ($documentos as $tipo => $archivo) {
                $tipoDocumento = match ($tipo) {
                    'ine' => 'INE',
                    'curp' => 'CURP',
                    'fotografia' => 'Fotografia',
                    'comprobante' => 'Comprobante',
                    default => null,
                };

                if ($tipoDocumento === null) {
                    continue;
                }

                $ruta = $archivo->store('jugadores/'.$jugador->getKey(), 'private');

                if ($ruta === false) {
                    throw new RuntimeException('No fue posible almacenar el documento confidencial del jugador.');
                }

                $jugador->documentos()->create([
                    'tipo' => $tipoDocumento,
                    'disco' => 'private',
                    'ruta_storage' => $ruta,
                    'mime_type' => (string) $archivo->getMimeType(),
                    'tamano_bytes' => $archivo->getSize() ?: 0,
                ]);
            }

            return $jugador;
        });
    }
}
