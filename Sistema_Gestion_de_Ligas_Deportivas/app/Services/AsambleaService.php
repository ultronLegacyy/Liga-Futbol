<?php

namespace App\Services;

use App\Models\AcuerdoAsamblea;
use App\Models\Asamblea;
use App\Models\AsistenciaAsamblea;
use App\Models\Club;
use App\Models\ComunicadoAsamblea;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Validation\ValidationException;

/**
 * Convocatoria y asistencia a las asambleas de delegados (HU-053, RF-14.1).
 */
class AsambleaService
{
    public function __construct(private AuditService $auditoria)
    {
    }

    /** @param array{tipo: string, fecha: string, hora: string, lugar: string, orden_del_dia: string, temporada_id?: int|string|null} $datos */
    public function convocar(array $datos, User $usuario): Asamblea
    {
        return $this->auditoria->conMotivo('Convocatoria a asamblea de delegados', fn (): Asamblea => Asamblea::query()->create([
            'tipo' => $datos['tipo'],
            'fecha' => $datos['fecha'],
            'hora' => $datos['hora'],
            'lugar' => trim($datos['lugar']),
            'orden_del_dia' => trim($datos['orden_del_dia']),
            'temporada_id' => $datos['temporada_id'] ?? null,
            'estado' => Asamblea::ESTADO_CONVOCADA,
            'convocada_por_usuario_id' => $usuario->getKey(),
        ]));
    }

    /**
     * Clubes que deben aparecer en la lista: los activos y los que ya tienen asistencia
     * registrada en esta asamblea (aunque después se hayan dado de baja).
     *
     * @return Collection<int, Club>
     */
    public function clubesConvocados(Asamblea $asamblea): Collection
    {
        $conAsistencia = $asamblea->asistencias()->pluck('club_id');

        return Club::query()
            ->where(fn ($consulta) => $consulta->where('estado', 'activo')->orWhereIn('id', $conAsistencia))
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Delegados del Club con cuenta, por club, para proponer al asistente.
     *
     * @param  Collection<int, Club>  $clubes
     * @return BaseCollection<array-key, Collection<int, User>>
     */
    public function delegadosPorClub(Collection $clubes): BaseCollection
    {
        return User::role('Delegado del Club')
            ->whereIn('club_id', $clubes->modelKeys())
            ->orderBy('name')
            ->get()
            ->groupBy('club_id')
            ->toBase();
    }

    /**
     * Guarda la lista de asistencia junto con la convocatoria. Se puede corregir después;
     * cada cambio queda en la auditoría.
     *
     * @param  array<int|string, array{asistio?: mixed, delegado_usuario_id?: int|string|null, nombre_asistente?: string|null, observaciones?: string|null}>  $lista
     */
    public function registrarAsistencia(Asamblea $asamblea, array $lista, User $usuario): Asamblea
    {
        return $this->auditoria->conMotivo('Registro de asistencia a asamblea de delegados', function () use ($asamblea, $lista, $usuario): Asamblea {
            $asamblea = Asamblea::query()->whereKey($asamblea->getKey())->lockForUpdate()->firstOrFail();

            // Decisión del Product Owner (respuesta 33 de la revisión del #73): la asistencia
            // se registra el día de la asamblea o después, con la hora de México.
            if (! $asamblea->admiteAsistencia()) {
                throw ValidationException::withMessages([
                    'asistencias' => 'La asistencia se registra el día de la asamblea ('.$asamblea->fecha->format('d/m/Y').') o después.',
                ]);
            }

            $clubes = $this->clubesConvocados($asamblea)->keyBy('id');
            $delegados = $this->delegadosPorClub($clubes->values());
            $errores = [];

            foreach ($lista as $clubId => $fila) {
                if (! $clubes->has((int) $clubId)) {
                    $errores["asistencias.$clubId"] = 'El club no está convocado a esta asamblea.';

                    continue;
                }

                $asistio = filter_var($fila['asistio'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $delegadoId = isset($fila['delegado_usuario_id']) && $fila['delegado_usuario_id'] !== '' ? (int) $fila['delegado_usuario_id'] : null;
                $nombre = trim((string) ($fila['nombre_asistente'] ?? ''));

                if ($delegadoId !== null && ! ($delegados->get((int) $clubId)?->contains('id', $delegadoId) ?? false)) {
                    $errores["asistencias.$clubId.delegado_usuario_id"] = 'El delegado elegido no pertenece a '.$clubes[(int) $clubId]->nombre.'.';
                }

                if ($asistio && $delegadoId === null && $nombre === '') {
                    $errores["asistencias.$clubId.nombre_asistente"] = 'Indica quién asistió por '.$clubes[(int) $clubId]->nombre.'.';
                }
            }

            if ($errores !== []) {
                throw ValidationException::withMessages($errores);
            }

            foreach ($clubes as $clubId => $club) {
                $fila = $lista[$clubId] ?? [];
                $asistio = filter_var($fila['asistio'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $delegadoId = isset($fila['delegado_usuario_id']) && $fila['delegado_usuario_id'] !== '' ? (int) $fila['delegado_usuario_id'] : null;
                $nombre = trim((string) ($fila['nombre_asistente'] ?? ''));

                if ($nombre === '' && $delegadoId !== null) {
                    $nombre = (string) $delegados->get($clubId)?->firstWhere('id', $delegadoId)?->name;
                }

                AsistenciaAsamblea::query()->updateOrCreate(
                    ['asamblea_id' => $asamblea->getKey(), 'club_id' => $clubId],
                    [
                        'asistio' => $asistio,
                        'delegado_usuario_id' => $asistio ? $delegadoId : null,
                        'nombre_asistente' => $asistio && $nombre !== '' ? $nombre : null,
                        'observaciones' => ($obs = trim((string) ($fila['observaciones'] ?? ''))) === '' ? null : $obs,
                        'registrado_por_usuario_id' => $usuario->getKey(),
                    ],
                );
            }

            $asamblea->update([
                'estado' => Asamblea::ESTADO_CELEBRADA,
                'asistencia_registrada_en' => $asamblea->asistencia_registrada_en ?? now(),
            ]);

            return $asamblea->refresh();
        });
    }

    /**
     * Registra un acuerdo de una asamblea ya celebrada (HU-054, RF-14.2). Si modifica el
     * reglamento, queda ligado a la temporada cuyo reglamento cambia.
     *
     * @param  array{descripcion: string, modifica_reglamento?: mixed, temporada_id?: int|string|null}  $datos
     */
    public function registrarAcuerdo(Asamblea $asamblea, array $datos, User $usuario): AcuerdoAsamblea
    {
        return $this->auditoria->conMotivo('Registro de acuerdo de asamblea', function () use ($asamblea, $datos, $usuario): AcuerdoAsamblea {
            $asamblea = $this->asambleaCelebrada($asamblea);
            $modifica = filter_var($datos['modifica_reglamento'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $temporadaId = isset($datos['temporada_id']) && $datos['temporada_id'] !== '' ? (int) $datos['temporada_id'] : $asamblea->temporada_id;

            if ($modifica && $temporadaId === null) {
                throw ValidationException::withMessages([
                    'temporada_id' => 'Indica la temporada cuyo reglamento modifica el acuerdo.',
                ]);
            }

            return AcuerdoAsamblea::query()->create([
                'asamblea_id' => $asamblea->getKey(),
                'numero' => ((int) $asamblea->acuerdosRegistrados()->max('numero')) + 1,
                'descripcion' => trim($datos['descripcion']),
                'modifica_reglamento' => $modifica,
                'temporada_id' => $modifica ? $temporadaId : null,
                'registrado_por_usuario_id' => $usuario->getKey(),
            ]);
        });
    }

    /**
     * Convierte un acuerdo en regla permanente (RF-14.3). Quien llama ya comprobó la
     * autorización con AsambleaPolicy::autorizarReglaPermanente; aquí se vuelve a exigir
     * para que ninguna otra ruta lo pueda hacer sin ella.
     */
    public function convertirEnReglaPermanente(AcuerdoAsamblea $acuerdo, string $motivo, User $usuario): AcuerdoAsamblea
    {
        if (! $usuario->hasRole('Administrador General') && ! $usuario->can('asambleas.acuerdos.autorizar')) {
            throw new AuthorizationException('No cuenta con autorización para convertir un acuerdo en regla permanente.');
        }

        return $this->auditoria->conMotivo('Acuerdo de asamblea convertido en regla permanente: '.trim($motivo), function () use ($acuerdo, $motivo, $usuario): AcuerdoAsamblea {
            $acuerdo = AcuerdoAsamblea::query()->whereKey($acuerdo->getKey())->lockForUpdate()->firstOrFail();

            if ($acuerdo->es_regla_permanente) {
                throw ValidationException::withMessages(['acuerdo' => 'El acuerdo ya es una regla permanente.']);
            }

            if (! $acuerdo->modifica_reglamento) {
                throw ValidationException::withMessages(['acuerdo' => 'Solo un acuerdo que modifica el reglamento puede convertirse en regla permanente.']);
            }

            $acuerdo->forceFill([
                'es_regla_permanente' => true,
                'autorizado_por_usuario_id' => $usuario->getKey(),
                'autorizado_en' => now(),
                'motivo_autorizacion' => trim($motivo),
            ])->save();

            return $acuerdo;
        });
    }

    /** @param array{titulo: string, contenido: string} $datos */
    public function registrarComunicado(Asamblea $asamblea, array $datos, User $usuario): ComunicadoAsamblea
    {
        return $this->auditoria->conMotivo('Registro de comunicado de asamblea', function () use ($asamblea, $datos, $usuario): ComunicadoAsamblea {
            $asamblea = $this->asambleaCelebrada($asamblea);

            return ComunicadoAsamblea::query()->create([
                'asamblea_id' => $asamblea->getKey(),
                'titulo' => trim($datos['titulo']),
                'contenido' => trim($datos['contenido']),
                'registrado_por_usuario_id' => $usuario->getKey(),
            ]);
        });
    }

    /** Los acuerdos y comunicados salen de la reunión: la asamblea debe tener su asistencia registrada. */
    private function asambleaCelebrada(Asamblea $asamblea): Asamblea
    {
        $asamblea = Asamblea::query()->whereKey($asamblea->getKey())->lockForUpdate()->firstOrFail();

        if ($asamblea->estado !== Asamblea::ESTADO_CELEBRADA) {
            throw ValidationException::withMessages([
                'asamblea' => 'Registra primero la asistencia: los acuerdos y comunicados solo se registran en una asamblea celebrada.',
            ]);
        }

        return $asamblea;
    }
}
