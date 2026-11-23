<?php

namespace App\Console\Commands;

use App\Services\RecordatorioPagoService;
use Illuminate\Console\Command;

class EnviarRecordatoriosPago extends Command
{
    protected $signature = 'sgld:recordatorios-pago';

    protected $description = 'Encola los recordatorios de pago a los delegados de los clubes con adeudos vencidos o próximos a vencer (HU-065, RF-15.2)';

    public function handle(RecordatorioPagoService $recordatorios): int
    {
        $this->info('Recordatorios de pago encolados: '.$recordatorios->enviar().'.');

        return self::SUCCESS;
    }
}
