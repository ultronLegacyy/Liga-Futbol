<?php

namespace App\Services;

use App\Models\Club;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AsignacionDelegadoClubService
{
    public function asignar(Club $club, int $usuarioId): User
    {
        return DB::transaction(function () use ($club, $usuarioId): User {
            $club = Club::query()->whereKey($club->getKey())->lockForUpdate()->firstOrFail();
            Gate::authorize('assignDelegates', $club);
            $delegado = User::query()->whereKey($usuarioId)->lockForUpdate()->firstOrFail();

            if (! $delegado->hasRole('Delegado del Club')) {
                throw ValidationException::withMessages([
                    'usuario_id' => 'La cuenta seleccionada no tiene el rol Delegado del Club.',
                ]);
            }

            if ($delegado->club_id !== null && (int) $delegado->club_id !== (int) $club->getKey()) {
                throw ValidationException::withMessages([
                    'usuario_id' => 'La cuenta ya esta adscrita a otro club; esa relacion debe conservarse.',
                ]);
            }

            if ((int) $delegado->club_id !== (int) $club->getKey()) {
                $delegado->forceFill(['club_id' => $club->getKey()])->save();
            }

            return $delegado;
        });
    }
}
