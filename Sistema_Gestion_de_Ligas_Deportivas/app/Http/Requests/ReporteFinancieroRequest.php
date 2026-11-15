<?php

namespace App\Http\Requests;

use App\Models\CargoFinanciero;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

// HU-052: filtros del reporte financiero. El periodo se mide con el vencimiento del cargo; periodo y club son obligatorios
class ReporteFinancieroRequest extends FormRequest
{
    private const CAMPOS = ['desde', 'hasta', 'club_id', 'temporada_id', 'concepto'];

    // Si la validación falla se vuelve al formulario sin filtros, que no exige nada y así no hay redirección en bucle
    protected $redirectRoute = 'reportes.financiero';

    /** Devuelve la respuesta de la política para propagar su mensaje en español. */
    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('verReporte', CargoFinanciero::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        // Sin filtros, la pantalla solo muestra el formulario; las descargas siempre los exigen
        $obligatorio = $this->debeGenerar() ? 'required' : 'nullable';

        $hasta = ['bail', $obligatorio, 'string', 'date_format:Y-m-d'];

        // after_or_equal falla con un error 500 si «desde» llega como arreglo; en ese caso el error ya lo da «desde»
        if (is_string($this->input('desde'))) {
            $hasta[] = 'after_or_equal:desde';
        }

        return [
            'desde' => ['bail', $obligatorio, 'string', 'date_format:Y-m-d'],
            'hasta' => $hasta,
            'club_id' => ['bail', $obligatorio, 'integer', 'exists:clubes,id'],
            'temporada_id' => ['bail', 'nullable', 'integer', 'exists:temporadas,id'],
            'concepto' => ['bail', 'nullable', 'string', Rule::in(array_keys(CargoFinanciero::CONCEPTOS))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'desde.required' => 'Indique la fecha de inicio del periodo.',
            'desde.string' => 'La fecha de inicio no es válida.',
            'desde.date_format' => 'La fecha de inicio debe tener el formato AAAA-MM-DD.',
            'hasta.required' => 'Indique la fecha final del periodo.',
            'hasta.string' => 'La fecha final no es válida.',
            'hasta.date_format' => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la fecha de inicio.',
            'club_id.required' => 'Elija un club.',
            'club_id.integer' => 'El club elegido no es válido.',
            'club_id.exists' => 'El club elegido no existe.',
            'temporada_id.integer' => 'La temporada elegida no es válida.',
            'temporada_id.exists' => 'La temporada elegida no existe.',
            'concepto.string' => 'El concepto elegido no es válido.',
            'concepto.in' => 'El concepto elegido no es válido.',
        ];
    }

    public function debeGenerar(): bool
    {
        return $this->routeIs('reportes.financiero.*') || collect(self::CAMPOS)->contains(fn (string $campo): bool => $this->filled($campo));
    }

    /**
     * Filtros ya validados, o null si todavía no se pidió el reporte.
     *
     * @return array{desde: string, hasta: string, clubId: int, temporadaId: ?int, concepto: ?string}|null
     */
    public function filtros(): ?array
    {
        if (! $this->debeGenerar()) {
            return null;
        }

        $datos = $this->validated();

        return [
            'desde' => (string) $datos['desde'],
            'hasta' => (string) $datos['hasta'],
            'clubId' => (int) $datos['club_id'],
            'temporadaId' => isset($datos['temporada_id']) ? (int) $datos['temporada_id'] : null,
            'concepto' => isset($datos['concepto']) ? (string) $datos['concepto'] : null,
        ];
    }
}
