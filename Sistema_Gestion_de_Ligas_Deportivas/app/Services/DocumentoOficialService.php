<?php

namespace App\Services;

use App\Models\CargoFinanciero;
use App\Models\CedulaArbitral;
use App\Models\Club;
use App\Models\Jugador;
use App\Models\Pago;
use App\Models\Partido;
use App\Models\Temporada;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Documentos oficiales en PDF (HU-069, RF-16.2, IH-03): estado de cuenta por club, cédula oficial de un partido
 * cerrado y constancia de registro de un jugador. Aquí solo se arman los datos; el PDF lo genera dompdf.
 */
class DocumentoOficialService
{
    public function __construct(private readonly CierreCedulaService $cierre)
    {
    }

    /**
     * Cargos del club con sus pagos y los totales en centavos, con las mismas reglas de saldo que HU-051.
     *
     * @return array{cargos: Collection<int, CargoFinanciero>, pagos: \Illuminate\Support\Collection<int, Pago>, cargado: int, pagado: int, saldo: int}
     */
    public function estadoDeCuenta(Club $club, ?Temporada $temporada = null): array
    {
        $cargos = CargoFinanciero::query()
            ->where('club_id', $club->getKey())
            ->when($temporada !== null, fn ($consulta) => $consulta->where('temporada_id', $temporada?->getKey()))
            ->with([
                'temporada:id,nombre',
                'equipo:id,nombre',
                'persona:id,nombre,apellidos',
                'pagos' => fn ($consulta) => $consulta->orderBy('created_at')->orderBy('id'),
            ])
            ->orderBy('vencimiento')
            ->orderBy('id')
            ->get();

        $pagos = $cargos->flatMap(fn (CargoFinanciero $cargo) => $cargo->pagos)
            ->sortBy([['created_at', 'asc'], ['id', 'asc']])
            ->values();

        $cargado = (int) $cargos->sum(fn (CargoFinanciero $cargo): int => CargoFinanciero::aCentavos($cargo->monto));
        $pagado = (int) $pagos->sum(fn (Pago $pago): int => CargoFinanciero::aCentavos($pago->monto));

        return [
            'cargos' => $cargos,
            'pagos' => $pagos,
            'cargado' => $cargado,
            'pagado' => $pagado,
            'saldo' => (int) $cargos->sum(fn (CargoFinanciero $cargo): int => $cargo->centavosDeSaldo()),
        ];
    }

    /**
     * Cédula cerrada con alineaciones, eventos, incidencias y las firmas en data URI (dompdf no lee el disco privado).
     *
     * @return array{cedula: CedulaArbitral, firmas: array<string, string>}
     */
    public function cedulaOficial(Partido $partido): array
    {
        $cedula = $partido->cedula()->first();

        // HU-040: una cédula cerrada no es oficial hasta que el resultado se valida
        if ($cedula === null || ! $cedula->esOficial()) {
            throw ValidationException::withMessages([
                'cedula' => 'La cédula oficial solo se genera cuando el resultado ya fue validado.',
            ]);
        }

        $partido->loadMissing([
            'local:id,nombre',
            'visitante:id,nombre',
            'cancha:id,nombre',
            'arbitro.persona:id,nombre,apellidos',
            'jornada:id,grupo_id,numero_jornada',
            'jornada.grupo:id,categoria_id,nombre',
            'jornada.grupo.categoria:id,nombre,rama',
        ]);

        // Sin la CURP: de cada persona solo el nombre, como en la pantalla de la cédula
        $cedula->load([
            'alineaciones' => fn ($consulta) => $consulta->with('jugador.persona:id,nombre,apellidos')->orderBy('equipo_id')->orderBy('numero_camiseta'),
            'eventos' => fn ($consulta) => $consulta->with('jugador.persona:id,nombre,apellidos')->orderBy('minuto')->orderBy('id'),
            'incidencias' => fn ($consulta) => $consulta->with('equipo:id,nombre')->orderBy('minuto')->orderBy('id'),
            'cerradaPor:id,name',
        ]);

        $firmas = [];
        foreach (CierreCedulaService::LADOS as $lado) {
            $ruta = $this->cierre->rutaDeFirma($cedula, $lado);
            if ($ruta !== null) {
                $firmas[$lado] = 'data:image/png;base64,'.base64_encode((string) Storage::disk('local')->get($ruta));
            }
        }

        return ['cedula' => $cedula, 'firmas' => $firmas];
    }

    /**
     * Datos de la constancia de registro: jugador, equipo, club, categoría y estado de elegibilidad.
     */
    public function constancia(Jugador $jugador): Jugador
    {
        return $jugador->load([
            'persona:id,nombre,apellidos',
            'equipo:id,nombre,club_id,grupo_id',
            'equipo.club:id,nombre',
            'equipo.grupo:id,categoria_id,nombre',
            'equipo.grupo.categoria:id,nombre,rama,temporada_id',
            'equipo.grupo.categoria.temporada:id,nombre',
        ]);
    }

    /**
     * Pantalla «Documentos oficiales» del Tesorero y el Presidente: partidos con resultado oficial, clubes y una
     * búsqueda de jugadores por nombre.
     *
     * @return array{partidos: Collection<int, Partido>, clubes: Collection<int, Club>, jugadores: Collection<int, Jugador>}
     */
    public function listado(string $buscar): array
    {
        $texto = '%'.addcslashes($buscar, '%_\\').'%';

        return [
            'partidos' => Partido::query()
                ->whereHas('cedula', fn ($cedula) => $cedula->oficiales())
                ->with('local:id,nombre', 'visitante:id,nombre', 'cedula:id,partido_id,goles_local,goles_visitante')
                ->orderByDesc('fecha_partido')
                ->orderByDesc('id')
                ->limit(30)
                ->get(),
            'clubes' => Club::query()->orderBy('nombre')->get(['id', 'nombre']),
            'jugadores' => mb_strlen($buscar) >= 2
                ? Jugador::query()
                    ->where('estado_elegibilidad', '!=', 'Baja')
                    ->whereHas('persona', fn ($persona) => $persona
                        ->where('nombre', 'like', $texto)
                        ->orWhere('apellidos', 'like', $texto))
                    ->with('persona:id,nombre,apellidos', 'equipo:id,nombre')
                    ->limit(20)
                    ->get()
                : new Collection(),
        ];
    }

    // Folio legible: tipo, identificador y fecha de emisión
    public static function folio(string $tipo, int $id): string
    {
        return strtoupper($tipo).'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT).'-'.now('America/Mexico_City')->format('Ymd');
    }
}
