<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use JsonException;

class StoreReglasTemporadaRequest extends FormRequest
{
    /**
     * Catálogo provisional de criterios de desempate.
     *
     * @var array<int, string>
     */
    private const CRITERIOS_DESEMPATE = [
        'diferencia_goles',
        'goles_favor',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('reglas_temporada.editar') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reglas_json' => ['required', 'string', 'max:20000'],
            'motivo' => ['required', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $json = $this->input('reglas_json');

            if (! is_string($json)) {
                return;
            }

            try {
                $reglas = json_decode(
                    $json,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            } catch (JsonException) {
                $validator->errors()->add(
                    'reglas_json',
                    'El contenido debe ser un JSON válido.'
                );

                return;
            }

            if (! is_array($reglas) || array_is_list($reglas)) {
                $validator->errors()->add(
                    'reglas_json',
                    'Las reglas deben ser un objeto JSON, no una lista.'
                );

                return;
            }

            $categoriasEsperadas = [
                'cuotas',
                'fechas_limite',
                'clasificacion',
                'desempates',
                'ascenso_descenso',
            ];

            if (! $this->tieneClavesExactas($reglas, $categoriasEsperadas)) {
                $validator->errors()->add(
                    'reglas_json',
                    'Las reglas deben contener exactamente las cinco categorías requeridas.'
                );

                return;
            }

            /*
             * Cuotas: montos numéricos no negativos.
             */
            if (! $this->esObjetoNoVacio($reglas['cuotas'])) {
                $this->agregarError(
                    $validator,
                    'La categoría cuotas debe ser un objeto JSON no vacío.'
                );

                return;
            }

            foreach ($reglas['cuotas'] as $nombre => $monto) {
                if (
                    ! is_string($nombre)
                    || trim($nombre) === ''
                    || (! is_int($monto) && ! is_float($monto))
                    || ! is_finite((float) $monto)
                    || $monto < 0
                ) {
                    $this->agregarError(
                        $validator,
                        'Cada cuota debe tener un nombre y un monto numérico mayor o igual a cero.'
                    );

                    return;
                }
            }

            /*
             * Fechas límite: fechas reales con formato YYYY-MM-DD.
             */
            if (! $this->esObjetoNoVacio($reglas['fechas_limite'])) {
                $this->agregarError(
                    $validator,
                    'La categoría fechas_limite debe ser un objeto JSON no vacío.'
                );

                return;
            }

            foreach ($reglas['fechas_limite'] as $nombre => $fecha) {
                if (
                    ! is_string($nombre)
                    || trim($nombre) === ''
                    || ! is_string($fecha)
                    || ! $this->esFechaValida($fecha)
                ) {
                    $this->agregarError(
                        $validator,
                        'Cada fecha límite debe tener un nombre y una fecha válida en formato YYYY-MM-DD.'
                    );

                    return;
                }
            }

            /*
             * Clasificación: claves fijas y puntos enteros no negativos.
             */
            $camposClasificacion = [
                'puntos_victoria',
                'puntos_empate',
                'puntos_derrota',
            ];

            if (
                ! $this->esObjetoNoVacio($reglas['clasificacion'])
                || ! $this->tieneClavesExactas(
                    $reglas['clasificacion'],
                    $camposClasificacion
                )
            ) {
                $this->agregarError(
                    $validator,
                    'La categoría clasificacion debe contener exactamente puntos_victoria, puntos_empate y puntos_derrota.'
                );

                return;
            }

            foreach ($camposClasificacion as $campo) {
                if (
                    ! is_int($reglas['clasificacion'][$campo])
                    || $reglas['clasificacion'][$campo] < 0
                ) {
                    $this->agregarError(
                        $validator,
                        "El campo {$campo} debe ser un entero mayor o igual a cero."
                    );

                    return;
                }
            }

            /*
             * Desempates: lista ordenada, sin duplicados,
             * limitada al catálogo admitido.
             */
            $desempates = $reglas['desempates'];

            if (
                ! is_array($desempates)
                || ! array_is_list($desempates)
                || $desempates === []
            ) {
                $this->agregarError(
                    $validator,
                    'La categoría desempates debe ser una lista JSON no vacía.'
                );

                return;
            }

            if (count($desempates) !== count(array_unique($desempates, SORT_REGULAR))) {
                $this->agregarError(
                    $validator,
                    'La lista de desempates no puede contener criterios duplicados.'
                );

                return;
            }

            foreach ($desempates as $criterio) {
                if (
                    ! is_string($criterio)
                    || ! in_array($criterio, self::CRITERIOS_DESEMPATE, true)
                ) {
                    $this->agregarError(
                        $validator,
                        'Los criterios de desempate deben pertenecer al catálogo permitido: diferencia_goles, goles_favor.'
                    );

                    return;
                }
            }

            /*
             * Ascenso y descenso: cantidades enteras no negativas.
             */
            $camposAscensoDescenso = [
                'equipos_ascienden',
                'equipos_descienden',
            ];

            if (
                ! $this->esObjetoNoVacio($reglas['ascenso_descenso'])
                || ! $this->tieneClavesExactas(
                    $reglas['ascenso_descenso'],
                    $camposAscensoDescenso
                )
            ) {
                $this->agregarError(
                    $validator,
                    'La categoría ascenso_descenso debe contener exactamente equipos_ascienden y equipos_descienden.'
                );

                return;
            }

            foreach ($camposAscensoDescenso as $campo) {
                if (
                    ! is_int($reglas['ascenso_descenso'][$campo])
                    || $reglas['ascenso_descenso'][$campo] < 0
                ) {
                    $this->agregarError(
                        $validator,
                        "El campo {$campo} debe ser un entero mayor o igual a cero."
                    );

                    return;
                }
            }
        });
    }

    /**
     * @param array<mixed> $datos
     * @param array<int, string> $clavesEsperadas
     */
    private function tieneClavesExactas(
        array $datos,
        array $clavesEsperadas
    ): bool {
        $clavesRecibidas = array_keys($datos);

        return array_diff($clavesEsperadas, $clavesRecibidas) === []
            && array_diff($clavesRecibidas, $clavesEsperadas) === [];
    }

    private function esObjetoNoVacio(mixed $valor): bool
    {
        if (! is_array($valor) || $valor === []) {
            return false;
        }

        return ! array_is_list($valor);
    }

    private function esFechaValida(string $fecha): bool
    {
        $objetoFecha = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        return $objetoFecha !== false
            && $objetoFecha->format('Y-m-d') === $fecha;
    }

    private function agregarError(
        Validator $validator,
        string $mensaje
    ): void {
        $validator->errors()->add('reglas_json', $mensaje);
    }
}
