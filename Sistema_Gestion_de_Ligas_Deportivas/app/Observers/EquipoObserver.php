<?php

namespace App\Observers;

use App\Models\Equipo;
use App\Services\TarjetonDigitalService;

class EquipoObserver
{
    public function __construct(private TarjetonDigitalService $tarjetonDigital)
    {
    }

    /**
     * Handle the Equipo "created" event.
     */
    public function created(Equipo $equipo): void
    {
        $this->tarjetonDigital->reemitirParaClub((int) $equipo->club_id);
    }

    /**
     * Handle the Equipo "updated" event.
     */
    public function updated(Equipo $equipo): void
    {
        $this->tarjetonDigital->reemitirParaClub((int) $equipo->club_id);

        $clubAnteriorId = $equipo->getPrevious()['club_id'] ?? null;

        if ($equipo->wasChanged('club_id') && is_numeric($clubAnteriorId)
            && (int) $clubAnteriorId !== (int) $equipo->club_id) {
            $this->tarjetonDigital->reemitirParaClub((int) $clubAnteriorId);
        }
    }

    /**
     * Handle the Equipo "deleted" event.
     */
    public function deleted(Equipo $equipo): void
    {
        $this->tarjetonDigital->reemitirParaClub((int) $equipo->club_id);
    }

    /**
     * Handle the Equipo "restored" event.
     */
    public function restored(Equipo $equipo): void
    {
        //
    }

    /**
     * Handle the Equipo "force deleted" event.
     */
    public function forceDeleted(Equipo $equipo): void
    {
        //
    }
}
