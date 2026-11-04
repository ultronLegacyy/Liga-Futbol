<?php

namespace App\Observers;

use App\Models\DirectivoClub;
use App\Services\TarjetonDigitalService;

class DirectivoClubObserver
{
    public function __construct(private TarjetonDigitalService $tarjetonDigital)
    {
    }

    /**
     * Handle the DirectivoClub "created" event.
     */
    public function created(DirectivoClub $directivoClub): void
    {
        $this->tarjetonDigital->reemitirParaClub((int) $directivoClub->club_id);
    }

    /**
     * Handle the DirectivoClub "updated" event.
     */
    public function updated(DirectivoClub $directivoClub): void
    {
        $this->tarjetonDigital->reemitirParaClub((int) $directivoClub->club_id);

        $clubAnteriorId = $directivoClub->getPrevious()['club_id'] ?? null;

        if ($directivoClub->wasChanged('club_id') && is_numeric($clubAnteriorId)
            && (int) $clubAnteriorId !== (int) $directivoClub->club_id) {
            $this->tarjetonDigital->reemitirParaClub((int) $clubAnteriorId);
        }
    }

    /**
     * Handle the DirectivoClub "deleted" event.
     */
    public function deleted(DirectivoClub $directivoClub): void
    {
        $this->tarjetonDigital->reemitirParaClub((int) $directivoClub->club_id);
    }

    /**
     * Handle the DirectivoClub "restored" event.
     */
    public function restored(DirectivoClub $directivoClub): void
    {
        //
    }

    /**
     * Handle the DirectivoClub "force deleted" event.
     */
    public function forceDeleted(DirectivoClub $directivoClub): void
    {
        //
    }
}
