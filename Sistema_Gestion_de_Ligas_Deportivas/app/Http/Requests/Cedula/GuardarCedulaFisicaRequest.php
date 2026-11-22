<?php

namespace App\Http\Requests\Cedula;

use App\Http\Requests\Concerns\DescartaArreglosEnCamposDeTexto;
use App\Models\EventoPartido;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * HU-037: formulario rápido para transcribir una cédula en papel. Las filas de eventos e incidencias
 * vacías se ignoran; una fila a medias se rechaza con su número. El resto lo valida CedulaService,
 * igual que en la captura desde el celular.
 */
class GuardarCedulaFisicaRequest extends CapturaCedulaRequest
{
    use DescartaArreglosEnCamposDeTexto;

    public const FILAS_EVENTOS = 30;

    public const FILAS_INCIDENCIAS = 10;

    protected string $habilidad = 'capturarFisica';

    /** @return list<string> */
    protected function camposDeTexto(): array
    {
        return ['goles_local', 'goles_visitante', 'informe_arbitral', 'incidencias.*.descripcion', 'incidencias.*.minuto', 'eventos.*.minuto'];
    }

    protected function reglas(): array
    {
        return [
            'goles_local' => ['bail', 'required', 'integer', 'between:0,99'],
            'goles_visitante' => ['bail', 'required', 'integer', 'between:0,99'],
            'alineacion' => ['bail', 'sometimes', 'array'],
            'alineacion.*' => ['bail', 'array'],
            'alineacion.*.asistio' => ['bail', 'sometimes', 'boolean'],
            'alineacion.*.numero_camiseta' => ['bail', 'nullable', 'integer', 'between:0,999'],
            'capitanes' => ['bail', 'sometimes', 'array'],
            'capitanes.*' => ['bail', 'nullable', 'integer'],
            'eventos' => ['bail', 'sometimes', 'array', 'max:'.self::FILAS_EVENTOS],
            'eventos.*' => ['bail', 'array'],
            'eventos.*.jugador_id' => ['bail', 'nullable', 'integer'],
            'eventos.*.tipo' => ['bail', 'nullable', 'string', Rule::in(EventoPartido::TIPOS)],
            'eventos.*.minuto' => ['bail', 'nullable', 'integer', 'between:0,150'],
            'eventos.*.autogol' => ['bail', 'sometimes', 'boolean'],
            'incidencias' => ['bail', 'sometimes', 'array', 'max:'.self::FILAS_INCIDENCIAS],
            'incidencias.*' => ['bail', 'array'],
            'incidencias.*.descripcion' => ['bail', 'nullable', 'string', 'max:1000'],
            'incidencias.*.minuto' => ['bail', 'nullable', 'integer', 'between:0,150'],
            'incidencias.*.equipo_id' => ['bail', 'nullable', 'integer'],
            'informe_arbitral' => ['bail', 'nullable', 'string', 'max:5000'],
        ];
    }

    protected function mensajes(): array
    {
        return [
            'goles_local.required' => 'Escribe los goles del equipo local; si no anotó, escribe 0.',
            'goles_visitante.required' => 'Escribe los goles del equipo visitante; si no anotó, escribe 0.',
            'goles_local.integer' => 'Los goles de cada equipo deben estar entre 0 y 99.',
            'goles_visitante.integer' => 'Los goles de cada equipo deben estar entre 0 y 99.',
            'goles_local.between' => 'Los goles de cada equipo deben estar entre 0 y 99.',
            'goles_visitante.between' => 'Los goles de cada equipo deben estar entre 0 y 99.',
            'alineacion.array' => 'La alineación no es válida.',
            'alineacion.*.array' => 'La fila de la alineación no es válida.',
            'alineacion.*.asistio.boolean' => 'La asistencia debe ser sí o no.',
            'alineacion.*.numero_camiseta.integer' => 'El número de camiseta debe estar entre 0 y 999.',
            'alineacion.*.numero_camiseta.between' => 'El número de camiseta debe estar entre 0 y 999.',
            'capitanes.array' => 'Los capitanes no son válidos.',
            'capitanes.*.integer' => 'El capitán elegido no es válido.',
            'eventos.array' => 'Los goles y tarjetas no son válidos.',
            'eventos.max' => 'La cédula admite hasta '.self::FILAS_EVENTOS.' goles y tarjetas por captura.',
            'eventos.*.array' => 'La fila del evento no es válida.',
            'eventos.*.jugador_id.integer' => 'El jugador elegido no es válido.',
            'eventos.*.tipo.string' => 'El evento debe ser Gol, Amarilla o Roja.',
            'eventos.*.tipo.in' => 'El evento debe ser Gol, Amarilla o Roja.',
            'eventos.*.minuto.integer' => 'El minuto debe ser un número entero entre 0 y 150.',
            'eventos.*.minuto.between' => 'El minuto debe ser un número entero entre 0 y 150.',
            'eventos.*.autogol.boolean' => 'El autogol debe ser sí o no.',
            'incidencias.array' => 'Las incidencias no son válidas.',
            'incidencias.max' => 'La cédula admite hasta '.self::FILAS_INCIDENCIAS.' incidencias por captura.',
            'incidencias.*.array' => 'La fila de la incidencia no es válida.',
            'incidencias.*.descripcion.string' => 'La descripción de la incidencia debe ser texto.',
            'incidencias.*.descripcion.max' => 'La descripción de la incidencia admite hasta 1000 caracteres.',
            'incidencias.*.minuto.integer' => 'El minuto debe ser un número entero entre 0 y 150.',
            'incidencias.*.minuto.between' => 'El minuto debe ser un número entero entre 0 y 150.',
            'incidencias.*.equipo_id.integer' => 'El equipo elegido no es válido.',
            'informe_arbitral.string' => 'El reporte arbitral debe ser texto.',
            'informe_arbitral.max' => 'El reporte arbitral admite hasta 5000 caracteres.',
        ];
    }

    /** Una fila de evento con jugador o tipo debe tener los dos; una incidencia con minuto o equipo necesita descripción. */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('eventos', []) as $fila => $evento) {
                if (! is_array($evento) || ! $this->filaLlena($evento, ['jugador_id', 'tipo', 'minuto'])) {
                    continue;
                }

                $numero = is_int($fila) ? $fila + 1 : $fila;

                if (blank($evento['jugador_id'] ?? null) && ! $validator->errors()->has("eventos.$fila.jugador_id")) {
                    $validator->errors()->add("eventos.$fila.jugador_id", "Evento $numero: elige al jugador.");
                }

                if (blank($evento['tipo'] ?? null) && ! $validator->errors()->has("eventos.$fila.tipo")) {
                    $validator->errors()->add("eventos.$fila.tipo", "Evento $numero: elige si es gol, amarilla o roja.");
                }
            }

            foreach ((array) $this->input('incidencias', []) as $fila => $incidencia) {
                if (is_array($incidencia) && $this->filaLlena($incidencia, ['minuto', 'equipo_id']) && blank($incidencia['descripcion'] ?? null)) {
                    $numero = is_int($fila) ? $fila + 1 : $fila;
                    $validator->errors()->add("incidencias.$fila.descripcion", "Incidencia $numero: describe lo que pasó.");
                }
            }
        }];
    }

    /**
     * Datos limpios para CapturaCedulaFisicaService: sin las filas vacías.
     *
     * @return array{
     *     goles_local: int,
     *     goles_visitante: int,
     *     alineacion: array<int, array{asistio: bool, numero_camiseta: int|null}>,
     *     capitanes: array<int, int|null>,
     *     eventos: array<int, array{jugador_id: int, tipo: string, minuto: int|null, autogol: bool}>,
     *     incidencias: array<int, array{descripcion: string, minuto: int|null, equipo_id: int|null}>,
     *     informe_arbitral: string|null
     * }
     */
    public function datos(): array
    {
        $validados = $this->validated();
        $entero = static fn (mixed $valor): ?int => $valor === null || $valor === '' ? null : (int) $valor;

        $alineacion = [];
        foreach ((array) ($validados['alineacion'] ?? []) as $jugadorId => $fila) {
            $alineacion[(int) $jugadorId] = [
                'asistio' => filter_var($fila['asistio'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'numero_camiseta' => $entero($fila['numero_camiseta'] ?? null),
            ];
        }

        $capitanes = [];
        foreach ((array) ($validados['capitanes'] ?? []) as $equipoId => $jugadorId) {
            $capitanes[(int) $equipoId] = $entero($jugadorId);
        }

        $eventos = [];
        foreach (array_values((array) ($validados['eventos'] ?? [])) as $fila => $evento) {
            if ($this->filaLlena($evento, ['jugador_id', 'tipo', 'minuto'])) {
                $eventos[$fila] = [
                    'jugador_id' => (int) $evento['jugador_id'],
                    'tipo' => (string) $evento['tipo'],
                    'minuto' => $entero($evento['minuto'] ?? null),
                    'autogol' => filter_var($evento['autogol'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            }
        }

        $incidencias = [];
        foreach (array_values((array) ($validados['incidencias'] ?? [])) as $fila => $incidencia) {
            if ($this->filaLlena($incidencia, ['descripcion', 'minuto', 'equipo_id'])) {
                $incidencias[$fila] = [
                    'descripcion' => (string) ($incidencia['descripcion'] ?? ''),
                    'minuto' => $entero($incidencia['minuto'] ?? null),
                    'equipo_id' => $entero($incidencia['equipo_id'] ?? null),
                ];
            }
        }

        $informe = isset($validados['informe_arbitral']) ? trim((string) $validados['informe_arbitral']) : '';

        return [
            'goles_local' => (int) $validados['goles_local'],
            'goles_visitante' => (int) $validados['goles_visitante'],
            'alineacion' => $alineacion,
            'capitanes' => $capitanes,
            'eventos' => $eventos,
            'incidencias' => $incidencias,
            'informe_arbitral' => $informe === '' ? null : $informe,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $fila
     * @param  list<string>  $campos
     */
    private function filaLlena(array $fila, array $campos): bool
    {
        foreach ($campos as $campo) {
            if (! blank($fila[$campo] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
