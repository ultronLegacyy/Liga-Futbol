<?php

namespace App\Services;

use App\Mail\RecordatorioPago;
use App\Models\CargoFinanciero;
use App\Models\Club;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Recordatorios de pago a los delegados de los clubes con adeudos vencidos o próximos a vencer (HU-065, RF-15.2).
 * Decisión 4 de Daniel: «próximo» = vence en 3 días o menos; un aviso al entrar en «próximo» y otro al vencer, sin repetir.
 * Decisión 8: un correo por delegado con los cargos de su club que entran a una etapa; el aviso de «vencido» sale
 * también para los cargos que ya estaban vencidos al instalarse.
 */
class RecordatorioPagoService
{
    public const PROXIMO = 'proximo';

    public const VENCIDO = 'vencido';

    private const DIAS_PROXIMO = 3;

    public function __construct(private readonly AdeudoClubService $adeudos)
    {
    }

    /**
     * Encola los recordatorios del día. Devuelve cuántos correos encoló.
     */
    public function enviar(): int
    {
        // Vencido desde el día siguiente al vencimiento, con la hora de México (respuesta 4 de Medina)
        $hoy = now('America/Mexico_City')->startOfDay();
        $cargos = $this->adeudos->cargosConSaldoQueVencenHasta($hoy->copy()->addDays(self::DIAS_PROXIMO));

        $enviados = DB::table('recordatorios_pago')->whereIn('cargo_id', $cargos->modelKeys())->get(['cargo_id', 'etapa'])
            ->map(fn (object $fila): string => $fila->cargo_id.':'.$fila->etapa)->flip();

        // Cada cargo con la etapa en la que está hoy; se quedan los que aún no tienen el aviso de esa etapa
        $pendientes = $cargos
            ->map(fn (CargoFinanciero $cargo): array => ['cargo' => $cargo, 'etapa' => $cargo->vencimiento->toDateString() < $hoy->toDateString() ? self::VENCIDO : self::PROXIMO])
            ->reject(fn (array $pendiente): bool => $enviados->has($pendiente['cargo']->id.':'.$pendiente['etapa']))
            ->groupBy(fn (array $pendiente): int => $pendiente['cargo']->club_id);

        $clubes = Club::query()->whereKey($pendientes->keys())->get(['id', 'nombre'])->keyBy('id');
        $delegados = User::query()->delegadosActivos($pendientes->keys())->orderBy('id')->get()->groupBy('club_id');
        $correos = 0;

        foreach ($pendientes as $clubId => $deClub) {
            // Un club sin delegado no recibe nada y sus cargos no se marcan: el aviso sale cuando tenga delegado
            if (! $delegados->has($clubId) || ! $clubes->has($clubId)) {
                continue;
            }

            $correos += $this->avisarClub($clubes[$clubId], $deClub, $delegados[$clubId]);
        }

        return $correos;
    }

    /**
     * @param  Collection<int, array{cargo: CargoFinanciero, etapa: string}>  $pendientes
     * @param  Collection<int, User>  $delegados
     */
    private function avisarClub(Club $club, Collection $pendientes, Collection $delegados): int
    {
        $lineas = $pendientes->map(fn (array $pendiente): string => $this->linea($pendiente['cargo'], $pendiente['etapa']))->implode("\n");
        // La misma lista de cargos y etapas no vuelve a salir: cada par se avisa una sola vez
        $huella = sha1($pendientes->map(fn (array $pendiente): string => $pendiente['cargo']->id.':'.$pendiente['etapa'])->implode(','));

        // Marcas y correos en la misma transacción: con la cola `database` los trabajos se guardan con las marcas, y si
        // falla el encolado se revierten juntos y el aviso sale en la siguiente corrida.
        // shortcut: atómico solo con la cola `database` en la misma conexión; con Redis u otra cola, revisar este orden
        try {
            DB::transaction(function () use ($club, $pendientes, $delegados, $lineas, $huella): void {
                $ahora = now();
                DB::table('recordatorios_pago')->insert($pendientes->map(fn (array $pendiente): array => [
                    'cargo_id' => $pendiente['cargo']->id, 'etapa' => $pendiente['etapa'], 'created_at' => $ahora, 'updated_at' => $ahora,
                ])->all());

                foreach ($delegados as $delegado) {
                    $mensaje = "Hola, {$delegado->name}:\n\nEste es un recordatorio de los adeudos del club {$club->nombre} con la liga:\n\n{$lineas}";

                    Mail::to($delegado)->queue(new RecordatorioPago('Recordatorio de pago: '.$club->nombre, $mensaje, 'recordatorio_pago:'.$huella.':'.$delegado->id));
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Otra corrida a la vez ya marcó estos cargos y manda su aviso; se sigue con el siguiente club
            return 0;
        }

        return $delegados->count();
    }

    private function linea(CargoFinanciero $cargo, string $etapa): string
    {
        $concepto = CargoFinanciero::CONCEPTOS[$cargo->concepto];
        $fecha = $cargo->vencimiento->format('d/m/Y');

        return '- '.$concepto.': saldo de $'.number_format($cargo->centavosDeSaldo() / 100, 2)
            .($etapa === self::VENCIDO ? ' · venció el '.$fecha : ' · vence el '.$fecha);
    }
}
