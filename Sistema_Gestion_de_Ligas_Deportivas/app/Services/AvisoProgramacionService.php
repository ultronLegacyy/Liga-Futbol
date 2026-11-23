<?php

namespace App\Services;

use App\Mail\AvisoProgramacion;
use App\Models\Cancha;
use App\Models\Equipo;
use App\Models\Jornada;
use App\Models\Partido;
use App\Models\ReprogramacionPartido;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Avisos por correo a los delegados de los clubes cuando se publica una jornada o se reprograma un partido
 * (HU-064, RF-15.1). Se llama dentro de la transacción de la operación; el correo sale al confirmarse.
 */
class AvisoProgramacionService
{
    // Publicación: un correo por delegado con los partidos de su club en la jornada (decisión de Daniel, 20/11)
    public function jornadaPublicada(Jornada $jornada): void
    {
        $partidos = $jornada->partidos()
            ->where('estado', '!=', Partido::ESTADO_CANCELADO)
            ->with(['local:id,nombre,club_id', 'visitante:id,nombre,club_id', 'cancha:id,nombre,direccion_ubicacion'])
            ->orderBy('fecha_partido')->orderBy('hora_partido')
            ->get();

        $grupo = $jornada->grupo()->with('categoria:id,nombre')->firstOrFail();
        $titulo = 'Jornada '.$jornada->numero_jornada.' · '.$grupo->nombre.' ('.$grupo->categoria?->nombre.')';
        $publicacion = $jornada->fecha_publicacion ? Carbon::parse($jornada->fecha_publicacion)->format('YmdHis') : now()->format('YmdHis');

        foreach ($this->delegados($this->clubes($partidos)) as $delegado) {
            $suyos = $partidos->filter(fn (Partido $partido): bool => in_array($delegado->club_id, [$partido->local?->club_id, $partido->visitante?->club_id], true));

            $mensaje = "Hola, {$delegado->name}:\n\nSe publicó la programación de la {$titulo}. Estos son los partidos de tu club:\n\n"
                .$suyos->map(fn (Partido $partido): string => $this->datosDelPartido($partido))->implode("\n\n");

            $this->enviar($delegado, 'Programación publicada: '.$titulo, $mensaje, 'publicacion:'.$jornada->id.':'.$publicacion.':'.$delegado->id);
        }
    }

    /**
     * Reprogramación o edición de sede u horario: un correo por delegado de los dos clubes, con lo anterior y lo nuevo.
     * Si cambiaron los equipos, también reciben el aviso los delegados de los clubes que salen del partido.
     *
     * @param  Collection<int, Equipo>|null  $equiposAnteriores
     */
    public function partidoReprogramado(Partido $partido, ReprogramacionPartido $registro, ?Collection $equiposAnteriores = null): void
    {
        $partido->load(['local:id,nombre,club_id', 'visitante:id,nombre,club_id', 'cancha:id,nombre,direccion_ubicacion']);
        $canchaAnterior = $registro->cancha_anterior_id ? Cancha::query()->find($registro->cancha_anterior_id) : null;
        $antes = $this->cuando($registro->fecha_anterior, $registro->hora_anterior).' · '.($canchaAnterior->nombre ?? 'Sin cancha');
        $equiposAntes = $equiposAnteriores ? "\nEquipos antes: ".$equiposAnteriores->pluck('nombre')->implode(' vs ') : '';
        $clubes = $this->clubes(new Collection([$partido]))->merge($equiposAnteriores?->pluck('club_id') ?? [])->filter()->unique()->values();

        foreach ($this->delegados($clubes) as $delegado) {
            $mensaje = "Hola, {$delegado->name}:\n\nCambió la programación de un partido de tu club.\n\n"
                .$this->datosDelPartido($partido)."\n\nAntes: {$antes}{$equiposAntes}\nMotivo: {$registro->motivo}";

            $this->enviar($delegado, 'Cambio de programación: '.($partido->local->nombre ?? 'Local').' vs '.($partido->visitante->nombre ?? 'Visitante'), $mensaje, 'reprogramacion:'.$registro->id.':'.$delegado->id);
        }
    }

    /**
     * Clubes que juegan esos partidos.
     *
     * @param  Collection<int, Partido>  $partidos
     * @return SupportCollection<int, mixed>
     */
    private function clubes(Collection $partidos): SupportCollection
    {
        return $partidos->toBase()->flatMap(fn (Partido $partido): array => [$partido->local?->club_id, $partido->visitante?->club_id])->filter()->unique()->values();
    }

    /**
     * Delegados del Club con cuenta de esos clubes (como AsambleaService::delegadosPorClub).
     *
     * @param  SupportCollection<int, mixed>  $clubes
     * @return Collection<int, User>
     */
    private function delegados(SupportCollection $clubes): Collection
    {
        // Una cuenta desactivada no recibe avisos
        return User::query()->delegadosActivos($clubes)->orderBy('id')->get();
    }

    private function datosDelPartido(Partido $partido): string
    {
        $cancha = $partido->cancha
            ? $partido->cancha->nombre.($partido->cancha->direccion_ubicacion ? ' ('.$partido->cancha->direccion_ubicacion.')' : '')
            : 'Sin cancha asignada';

        return ($partido->local->nombre ?? 'Local').' vs '.($partido->visitante->nombre ?? 'Visitante')."\n"
            .'Fecha y hora: '.$this->cuando($partido->fecha_partido, $partido->hora_partido)."\n"
            .'Cancha: '.$cancha;
    }

    private function cuando(mixed $fecha, mixed $hora): string
    {
        return ($fecha ? Carbon::parse($fecha)->format('d/m/Y') : 'Sin fecha')
            .($hora ? ' a las '.Carbon::parse((string) $hora)->format('H:i').' hrs' : ' · Sin hora');
    }

    // Se encola al confirmarse la transacción; si la operación se revierte, no sale nada
    private function enviar(User $delegado, string $asunto, string $mensaje, string $clave): void
    {
        DB::afterCommit(fn () => Mail::to($delegado)->queue(new AvisoProgramacion($asunto, $mensaje, $clave)));
    }
}
