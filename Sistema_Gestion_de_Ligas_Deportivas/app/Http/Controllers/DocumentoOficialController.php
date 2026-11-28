<?php

namespace App\Http\Controllers;

use App\Models\CargoFinanciero;
use App\Models\CedulaArbitral;
use App\Models\Club;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Temporada;
use App\Services\DocumentoOficialService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Documentos oficiales en PDF (HU-069, RF-16.2, IH-03). Tamaño carta con márgenes de impresora,
 * sin recursos remotos ni colores de fondo indispensables, para que salgan bien en cualquier impresora.
 */
class DocumentoOficialController extends Controller
{
    public function __construct(private readonly DocumentoOficialService $documentos)
    {
    }

    // Punto de entrada del Tesorero y el Presidente (HU-069): partidos con resultado oficial, clubes y jugadores
    public function index(Request $request): View
    {
        Gate::authorize('verReporte', CargoFinanciero::class);

        $buscar = $request->query('jugador');
        $buscar = is_string($buscar) ? mb_substr(trim($buscar), 0, 60) : '';

        return view('documentos.index', [...$this->documentos->listado($buscar), 'buscar' => $buscar]);
    }

    // Estado de cuenta de un club, de una temporada o de todas (Tesorero y Presidente)
    public function estadoDeCuenta(Request $request, Club $club): Response
    {
        Gate::authorize('verReporte', CargoFinanciero::class);

        // Sin temporada_id es el de todas las temporadas; un valor que no existe o no es número no se toma como «todas»
        $temporada = null;
        if ($request->filled('temporada_id')) {
            $temporadaId = $request->query('temporada_id');
            abort_unless(is_string($temporadaId) && ctype_digit($temporadaId), 404, 'La temporada indicada no existe.');
            $temporada = Temporada::query()->find((int) $temporadaId);
            abort_if($temporada === null, 404, 'La temporada indicada no existe.');
        }

        return $this->pdf('documentos.estado-cuenta', [
            'club' => $club,
            'temporada' => $temporada,
            'estado' => $this->documentos->estadoDeCuenta($club, $temporada),
            'folio' => DocumentoOficialService::folio('EC', (int) $club->getKey()),
        ], 'estado-de-cuenta-'.Str::slug($club->nombre).($temporada !== null ? '-'.Str::slug($temporada->nombre) : '').'.pdf');
    }

    // Cédula oficial de un partido con la cédula cerrada: marcador, alineaciones, eventos e incidencias
    public function cedula(Partido $partido): Response|RedirectResponse
    {
        Gate::authorize('descargarOficial', [CedulaArbitral::class, $partido]);

        try {
            $datos = $this->documentos->cedulaOficial($partido);
        } catch (ValidationException $error) {
            // Regresa a una pantalla que esa persona sí puede abrir: «Documentos oficiales» para Tesorería y
            // Presidencia, la cédula del partido para el árbitro asignado
            $destino = Gate::allows('verReporte', CargoFinanciero::class)
                ? route('documentos.index')
                : route('cedulas.show', $partido);

            return redirect()->to($destino)->withErrors($error->errors());
        }

        return $this->pdf('documentos.cedula', [
            'partido' => $partido,
            'cedula' => $datos['cedula'],
            'firmas' => $datos['firmas'],
            'folio' => DocumentoOficialService::folio('CED', (int) $partido->getKey()),
        ], 'cedula-oficial-partido-'.$partido->getKey().'.pdf');
    }

    // Constancia de registro de un jugador en su equipo
    public function constancia(Jugador $jugador): Response
    {
        Gate::authorize('constancia', $jugador);

        return $this->pdf('documentos.constancia', [
            'jugador' => $this->documentos->constancia($jugador),
            'folio' => DocumentoOficialService::folio('CON', (int) $jugador->getKey()),
        ], 'constancia-'.Str::slug(trim(($jugador->persona->nombre ?? '').' '.($jugador->persona->apellidos ?? 'jugador'))).'.pdf');
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function pdf(string $vista, array $datos, string $archivo): Response
    {
        $datos['generado'] = now('America/Mexico_City')->format('d/m/Y H:i');

        return Pdf::loadView($vista, $datos)
            ->setPaper('letter', 'portrait')
            // Solo los caracteres usados de DejaVu: el PDF pesa menos para enviarlo por correo
            ->setOption('isFontSubsettingEnabled', true)
            ->download($archivo);
    }
}
