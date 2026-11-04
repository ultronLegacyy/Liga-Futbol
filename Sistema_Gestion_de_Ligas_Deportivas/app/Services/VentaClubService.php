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

            $venta = HistorialVentaClub::query()->create([
                'club_id' => $club->getKey(),
                'propietario_anterior' => $propietarioAnterior === null ? 'Sin propietario registrado' : $propietarioAnterior->name,
                'propietario_nuevo' => $nuevoPropietario->name,
                'propietario_anterior_usuario_id' => $propietarioAnterior?->getKey(),
                'propietario_nuevo_usuario_id' => $nuevoPropietario->getKey(),
                'carta_venta_pdf' => $cartaVentaPdf,
                'fecha_venta' => $fechaVenta,
            ]);

            $equipoIds = $club->equipos()->lockForUpdate()->pluck('id');

            DB::table('jugadores_equipos')->whereIn('equipo_id', $equipoIds)->delete();
            $club->equipos()->update(['partidos_default_acumulados' => 0]);
            $club->update(['propietario_usuario_id' => $nuevoPropietario->getKey()]);

            return $venta;
        });
    }
}
