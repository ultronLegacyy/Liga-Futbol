<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\Persona;
use App\Models\PersonaRol;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class RegistroJugadorService
{
    public function __construct(
        private ReglaSaltoCategoriaService $reglaSaltoCategoria,
        private AuditService $auditoria,
        private ReglaEdadCategoriaService $reglaEdad,
        private ConsentimientoPrivacidadService $consentimiento,
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
     *     fotografia_base64?: string,
     *     aviso_privacidad_aceptado?: bool|int|string|null,
     *     tutor?: array<string, mixed>|null
     * } $datos
     */
    public function registrar(Equipo $equipoDestino, array $datos, User $usuario): Jugador
    {
        $equipoDestinoId = (int) $equipoDestino->getKey();

        $archivosPersistidos = [];

        try {
            return $this->auditoria->conMotivo('Inscripción de jugador en equipo', function () use ($equipoDestinoId, $datos, $usuario, &$archivosPersistidos): Jugador {
                $clubIdInicial = Equipo::query()->whereKey($equipoDestinoId)->value('club_id');

                if ($clubIdInicial === null) {
                    throw ValidationException::withMessages([
                        'equipo_id' => 'El equipo destino ya no está disponible.',
                    ]);
                }

                $club = Club::query()->whereKey($clubIdInicial)->lockForUpdate()->firstOrFail();
                $equipoDestino = Equipo::query()->whereKey($equipoDestinoId)->lockForUpdate()->firstOrFail();
                $usuario = User::query()->whereKey($usuario->getKey())->lockForUpdate()->firstOrFail();

                if ((int) $equipoDestino->club_id !== (int) $club->getKey()) {
                    throw ValidationException::withMessages([
                        'equipo_id' => 'El equipo cambió de club mientras se procesaba la inscripción.',
                    ]);
                }

                if ($usuario->hasRole('Delegado del Club')) {
                    Gate::forUser($usuario)->authorize('addPlayer', $equipoDestino);
                }

                $archivos = $datos['documentos'] ?? [];
                $fotografiaBase64 = $datos['fotografia_base64'] ?? null;
                $datosPrivacidad = [
                    'aviso_privacidad_aceptado' => $datos['aviso_privacidad_aceptado'] ?? null,
                    'tutor' => $datos['tutor'] ?? null,
                ];
                unset($datos['documentos'], $datos['fotografia_base64'], $datos['aviso_privacidad_aceptado'], $datos['tutor']);

                $persona = $this->resolverPersona($datos);
                $persona = Persona::query()
                    ->whereKey($persona->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $equipoDestino->load('temporada', 'grupo.categoria');
                $temporadaDestino = $equipoDestino->temporada_id === null
                    ? null
                    : Temporada::query()->whereKey($equipoDestino->temporada_id)->lockForUpdate()->first();
                $grupoDestino = $equipoDestino->grupo;
                $categoriaDestino = $grupoDestino?->categoria;

                if ($temporadaDestino === null || $grupoDestino === null || $categoriaDestino === null
                    || (int) $grupoDestino->temporada_id !== (int) $temporadaDestino->getKey()
                    || (int) $categoriaDestino->temporada_id !== (int) $temporadaDestino->getKey()) {
                    throw ValidationException::withMessages([
                        'equipo_id' => 'El equipo destino no tiene una temporada y categoría válidas.',
                    ]);
                }

                if ($temporadaDestino->estado !== 'activa') {
                    throw ValidationException::withMessages([
                        'equipo_id' => 'Solo se permiten inscripciones en temporadas activas.',
                    ]);
                }

                $equipoDestino->setRelation('temporada', $temporadaDestino);

                $inscripcionEnMismoEquipo = Jugador::query()
                    ->where('persona_id', $persona->getKey())
                    ->where('equipo_id', $equipoDestino->getKey())
                    ->exists();

                if ($inscripcionEnMismoEquipo) {
                    throw ValidationException::withMessages([
                        'persona_id' => 'El jugador ya está registrado en este equipo.',
                    ]);
                }

                $inscripcionActivaEnTemporada = Jugador::query()
                    ->where('persona_id', $persona->getKey())
                    ->where('estado_elegibilidad', 'Activo')
                    ->whereHas('equipo', fn ($query) => $query->where('temporada_id', $equipoDestino->temporada_id))
                    ->exists();

                if ($inscripcionActivaEnTemporada) {
                    throw ValidationException::withMessages([
                        'persona_id' => 'El jugador ya tiene una inscripción activa en la temporada destino.',
                    ]);
                }

                $inscripcionBloqueanteEnTemporada = Jugador::query()
                    ->where('persona_id', $persona->getKey())
                    ->whereIn('estado_elegibilidad', ['Suspendido', 'Inelegible', 'Baja'])
                    // La baja de una inscripción transferida no bloquea (HU-017): el historial de transferencias la identifica.
                    ->whereDoesntHave('transferenciasOrigen')
                    ->where('equipo_id', '!=', $equipoDestino->getKey())
                    ->whereHas('equipo', fn ($query) => $query->where('temporada_id', $equipoDestino->temporada_id))
                    ->exists();

                if ($inscripcionBloqueanteEnTemporada) {
                    throw ValidationException::withMessages([
                        'persona_id' => 'El jugador tiene una inscripción suspendida, inelegible o dada de baja en otro equipo de la temporada destino.',
                    ]);
                }

                $this->validarSaltoCategoria($persona, $equipoDestino);
                // Reglas de edad de la categoría, compartidas con la renovación y la transferencia (HU-017).
                $this->reglaEdad->validar($persona, $categoriaDestino, $temporadaDestino);
                // Aviso de privacidad y consentimiento del tutor (HU-020): sin ellos no se crea la inscripción.
                $privacidad = $this->consentimiento->exigir($persona, $categoriaDestino, $datosPrivacidad);

                PersonaRol::query()->createOrFirst([
                    'persona_id' => $persona->getKey(),
                    'rol' => 'jugador',
                    'temporada_id' => $equipoDestino->temporada->getKey(),
                ]);

                $jugador = $equipoDestino->jugadores()->create([
                    'persona_id' => $persona->getKey(),
                    'dorsal' => $datos['dorsal'] ?? null,
                ]);

                $constancia = $this->consentimiento->registrar($jugador, $privacidad, $usuario, request()->ip());
                // Sin el teléfono ni el correo del tutor: la bitácora no copia sus datos de contacto.
                $this->auditoria->registrar('consentimiento_privacidad_registrado', $constancia, null, [
                    'version_aviso' => $constancia->version_aviso,
                    'requiere_tutor' => $constancia->requiere_tutor,
                    'motivo_tutor' => $constancia->motivo_tutor,
                ]);

                $tipos = [
                    'ine' => 'INE',
                    'curp' => 'CURP',
                    'fotografia' => 'Foto',
                    'comprobante' => 'Comprobante',
                ];

                foreach ($tipos as $campo => $tipo) {
                    $archivo = $archivos[$campo] ?? null;
                    $metadatos = null;

                    if ($campo === 'fotografia' && $archivo === null && is_string($fotografiaBase64)) {
                        $metadatos = $this->guardarFotografiaBase64($persona, $fotografiaBase64);
                    } elseif ($archivo instanceof UploadedFile) {
                        $ruta = $archivo->store('personas/'.$persona->getKey(), 'private');

                        if ($ruta === false) {
                            throw new RuntimeException('No fue posible almacenar el documento confidencial del jugador.');
                        }

                        $metadatos = [
                            'ruta' => $ruta,
                            'mime' => (string) $archivo->getMimeType(),
                            'bytes' => $archivo->getSize() ?: 0,
                        ];
                    }

                    if ($metadatos === null) {
                        continue;
                    }

                    $archivosPersistidos[] = $metadatos['ruta'];

                    $documento = $persona->documentos()->create([
                        'tipo' => $tipo,
                        'ruta_storage' => $metadatos['ruta'],
                        'disco' => 'private',
                        'mime_type' => $metadatos['mime'],
                        'tamano_bytes' => $metadatos['bytes'],
                    ]);

                    $this->auditoria->registrar('documento_jugador_registrado', $documento, null, [
                        'tipo' => $tipo,
                        'disco' => 'private',
                    ]);
                }

                return $jugador;
            });
        } catch (Throwable $exception) {
            foreach (array_reverse(array_unique($archivosPersistidos)) as $rutaPersistida) {
                try {
                    if (! Storage::disk('private')->delete($rutaPersistida)) {
                        Log::critical('No se pudo limpiar un archivo privado tras revertir el registro de jugador.', [
                            'ruta' => $rutaPersistida,
                            'exception' => $exception::class,
                        ]);
                    }
                } catch (Throwable $errorLimpieza) {
                    Log::critical('Falló la limpieza de un archivo privado tras revertir el registro de jugador.', [
                        'ruta' => $rutaPersistida,
                        'exception' => $errorLimpieza::class,
                    ]);
                }
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $datos */
    private function resolverPersona(array $datos): Persona
    {
        if (isset($datos['persona_id']) && $datos['persona_id'] !== '') {
            return Persona::query()->findOrFail($datos['persona_id']);
        }

        if (! isset($datos['nombre'], $datos['apellidos'], $datos['fecha_nacimiento'], $datos['curp'])) {
            throw new InvalidArgumentException('Los datos personales del jugador son obligatorios.');
        }

        $curp = strtoupper(trim($datos['curp']));

        $persona = Persona::query()->createOrFirst(
            ['curp' => $curp],
            [
                'nombre' => $datos['nombre'],
                'apellidos' => $datos['apellidos'],
                'fecha_nacimiento' => $datos['fecha_nacimiento'],
            ],
        );

        $normalizar = static function (string $valor): string {
            $espaciosNormalizados = preg_replace('/\s+/u', ' ', trim($valor)) ?? trim($valor);

            return mb_strtoupper($espaciosNormalizados);
        };

        if (
            $normalizar($persona->nombre) !== $normalizar($datos['nombre'])
            || $normalizar($persona->apellidos) !== $normalizar($datos['apellidos'])
            || $persona->fecha_nacimiento->format('Y-m-d') !== $datos['fecha_nacimiento']
        ) {
            throw ValidationException::withMessages([
                'curp' => 'La CURP ya está registrada con datos personales diferentes. Verifica la información antes de continuar.',
            ]);
        }

        return $persona;
    }

    private function validarSaltoCategoria(Persona $persona, Equipo $equipoDestino): void
    {
        $inscripcionAnterior = Jugador::query()
            ->where('persona_id', $persona->getKey())
            ->with('equipo.grupo.categoria')
            ->latest('id')
            ->first();

        if ($inscripcionAnterior === null) {
            return;
        }

        $this->reglaSaltoCategoria->validar(
            $inscripcionAnterior->equipo->grupo->categoria,
            $equipoDestino->grupo->categoria,
        );
    }

    /** @return array{ruta: string, mime: string, bytes: int} */
    private function guardarFotografiaBase64(Persona $persona, string $dataUrl): array
    {
        preg_match('/^data:image\/(jpeg|png);base64,([A-Za-z0-9+\/=]+)$/', $dataUrl, $matches);
        $bytes = base64_decode($matches[2] ?? '', true);

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
