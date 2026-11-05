<?php

namespace App\Observers;

use App\Models\Club;
use App\Services\TarjetonDigitalService;

class ClubObserver
{
    public function __construct(private TarjetonDigitalService $tarjetonDigital)
    {
    }

    /**
     * Handle the Club "created" event.
     */
    public function created(Club $club): void
    {
        //
    }

    /**
     * Handle the Club "updated" event.
     */
    public function updated(Club $club): void
    {
        $this->tarjetonDigital->reemitirParaClub((int) $club->getKey());
    }

    /**
     * Handle the Club "deleted" event.
     */
    public function deleted(Club $club): void
    {
        //
    }

    /**
     * Handle the Club "restored" event.
     */
    public function restored(Club $club): void
    {
        //
    }

    /**
     * Handle the Club "force deleted" event.
     */
    public function forceDeleted(Club $club): void
    {
        //
    }
}
