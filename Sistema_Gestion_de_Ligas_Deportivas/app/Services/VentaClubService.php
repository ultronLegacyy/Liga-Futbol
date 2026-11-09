<?php

namespace App\Services;

use App\Models\Club;
use App\Models\HistorialVentaClub;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VentaClubService
{
    public function vender(Club $club, int $nuevoPropietarioId, string $fechaVenta, ?string $cartaVentaPdf = null): HistorialVentaClub
    {
        return DB::transaction(function () use ($club, $nuevoPropietarioId, $fechaVenta, $cartaVentaPdf): HistorialVentaClub {
            $club = Club::query()->lockForUpdate()->findOrFail($club->getKey());
            $nuevoPropietario = User::query()->findOrFail($nuevoPropietarioId);
            $propietarioAnterior = $club->propietario;

            if ($propietarioAnterior === null) {
                $propietarioAnterior = User::query()->where('club_id', $club->getKey())->first();
            }

            if ($propietarioAnterior !== null && $propietarioAnterior->is($nuevoPropietario)) {
                throw ValidationException::withMessages([
                    'propietario_usuario_id' => 'El nuevo propietario debe ser distinto al propietario actual.',
                ]);
            }

            $equipoIds = $club->equipos()->orderBy('id')->lockForUpdate()->pluck('id');
            $hayInscripciones = DB::table('jugadores_equipos')
                ->whereIn('equipo_id', $equipoIds)
                ->exists();

            if ($hayInscripciones) {
                throw ValidationException::withMessages([
                    'club' => 'No se puede vender el club mientras existan inscripciones de jugadores; la venta se canceló para conservar la plantilla, los documentos y su historial.',
                ]);
            }

            $delegadosAnteriores = User::query()
                ->where('club_id', $club->getKey())
                ->whereKeyNot($nuevoPropietario->getKey())
                ->whereHas('roles', fn ($query) => $query->where('name', 'Delegado del Club'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($delegadosAnteriores as $delegado) {
                if ((int) $delegado->club_id === (int) $club->getKey()) {
                    $delegado->forceFill(['club_id' => null])->save();
                }
            }

            $venta = HistorialVentaClub::query()->create([
                'club_id' => $club->getKey(),
                'propietario_anterior' => $propietarioAnterior === null ? 'Sin propietario registrado' : $propietarioAnterior->name,
                'propietario_nuevo' => $nuevoPropietario->name,
                'propietario_anterior_usuario_id' => $propietarioAnterior?->getKey(),
                'propietario_nuevo_usuario_id' => $nuevoPropietario->getKey(),
                'carta_venta_pdf' => $cartaVentaPdf,
                'fecha_venta' => $fechaVenta,
            ]);

            $club->equipos()->update(['partidos_default_acumulados' => 0]);
            $club->update(['propietario_usuario_id' => $nuevoPropietario->getKey()]);

            return $venta;
        });
    }
}
