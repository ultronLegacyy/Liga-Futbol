<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\Persona;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class RegistroJugadorService
{
    public function __construct(
        private ReglaSaltoCategoriaService $reglaSaltoCategoria,
        private AuditService $auditoria,
    ) {
    }

    /**
     * @param array{
     *     persona_id?: int|string|null,
     *     dorsal?: int|string|null,
     *     nombre?: string,
     *     apellidos?: string,
     *     fecha_nacimiento?: string,
     *     curp?: string,
     *     documentos?: array<string, UploadedFile>,
     *     fotografia_base64?: string
     * } $datos
     */
    public function registrar(Equipo $equipoDestino, array $datos): Jugador
    {
        return $this->auditoria->conMotivo('Inscripción de jugador en equipo', function () use ($equipoDestino, $datos): Jugador {
            $archivos = $datos['documentos'] ?? [];
            $fotografiaBase64 = $datos['fotografia_base64'] ?? null;
            unset($datos['documentos'], $datos['fotografia_base64']);

            if (isset($datos['persona_id']) && $datos['persona_id'] !== '') {
                $persona = Persona::query()->findOrFail($datos['persona_id']);
            } else {
                if (! isset($datos['nombre'], $datos['apellidos'], $datos['fecha_nacimiento'], $datos['curp'])) {
                    throw new InvalidArgumentException('Los datos personales del jugador son obligatorios.');
                }

                $curp = strtoupper(trim($datos['curp']));
                $persona = Persona::query()->firstOrCreate(
                    ['curp' => $curp],
                    [
                        'nombre' => $datos['nombre'],
                        'apellidos' => $datos['apellidos'],
                        'fecha_nacimiento' => $datos['fecha_nacimiento'],
                    ],
                );
            }

            $inscripcionAnterior = Jugador::query()
                ->where('persona_id', $persona->getKey())
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

            $jugador = $equipoDestino->jugadores()->create([
                'persona_id' => $persona->getKey(),
                'dorsal' => $datos['dorsal'] ?? null,
            ]);

            $tipos = [
                'ine' => 'INE',
                'curp' => 'CURP',
                'fotografia' => 'Foto',
                'comprobante' => 'Comprobante',
            ];

            foreach ($tipos as $campo => $tipo) {
                $archivo = $archivos[$campo] ?? null;
                $documentoArchivo = null;

                if ($campo === 'fotografia' && $archivo === null && is_string($fotografiaBase64)) {
                    $documentoArchivo = $this->guardarFotografiaBase64($persona, $fotografiaBase64);
                } elseif ($archivo instanceof UploadedFile) {
                    $ruta = $archivo->store('personas/'.$persona->getKey(), 'private');

                    if ($ruta === false) {
                        throw new RuntimeException('No fue posible guardar el documento privado.');
                    }

                    $documentoArchivo = [
                        'ruta' => $ruta,
                        'mime' => (string) $archivo->getMimeType(),
                        'bytes' => $archivo->getSize() ?: 0,
                    ];
                }

                if ($documentoArchivo === null) {
                    continue;
                }

                $documento = $persona->documentos()->create([
                    'tipo' => $tipo,
                    'ruta_storage' => $documentoArchivo['ruta'],
                    'disco' => 'private',
                    'mime_type' => $documentoArchivo['mime'],
                    'tamano_bytes' => $documentoArchivo['bytes'],
                ]);

                $this->auditoria->registrar('documento_jugador_registrado', $documento, null, [
                    'tipo' => $tipo,
                    'disco' => 'private',
                ]);
            }

            return $jugador;
        });
    }

    /** @return array{ruta: string, mime: string, bytes: int} */
    private function guardarFotografiaBase64(Persona $persona, string $dataUrl): array
    {
        preg_match('/^data:image\/(jpeg|png);base64,([A-Za-z0-9+\/=]+)$/', $dataUrl, $partes);
        $bytes = base64_decode($partes[2] ?? '', true);

        if ($bytes === false) {
            throw new RuntimeException('La fotografía capturada no es válida.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        if (! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            throw new RuntimeException('La fotografía debe estar en formato JPG o PNG.');
        }

        $extension = $mime === 'image/png' ? 'png' : 'jpg';
        $ruta = 'personas/'.$persona->getKey().'/fotografia-'.str()->uuid().'.'.$extension;

        if (! Storage::disk('private')->put($ruta, $bytes)) {
            throw new RuntimeException('No fue posible guardar la fotografía privada.');
        }

        return ['ruta' => $ruta, 'mime' => $mime, 'bytes' => strlen($bytes)];
    }
}
