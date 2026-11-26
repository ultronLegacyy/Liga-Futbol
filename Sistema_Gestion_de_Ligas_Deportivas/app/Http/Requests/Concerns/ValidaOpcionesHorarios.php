<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

/**
 * Validación y extracción unificada de parámetros del motor de programación automática (HU-074).
 */
trait ValidaOpcionesHorarios
{
    private const REGEX_HORA_VALIDA = '/^(?:[01]\d|2[0-3]):[0-5]\d$/';

    /**
     * Reglas comunes para opciones de horarios y canchas.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function reglasOpcionesHorarios(): array
    {
        return [
            'horarios' => ['nullable', 'array', 'max:12'],
            'horarios.*' => ['required', 'string', 'regex:'.self::REGEX_HORA_VALIDA],
            'horarios_texto' => ['nullable', 'string', 'max:255'],
            'cancha_ids' => ['nullable', 'array'],
            'cancha_ids.*' => ['integer', 'exists:canchas,id'],
            'respetar_capacidad_cancha' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Mensajes de error comunes en español.
     *
     * @return array<string, string>
     */
    protected function mensajesOpcionesHorarios(): array
    {
        return [
            'horarios.*.regex' => 'Cada franja horaria debe tener formato válido HH:MM entre 00:00 y 23:59 (por ejemplo, 09:00).',
            'horarios_texto.string' => 'Las franjas horarias deben enviarse como texto separado por comas.',
            'cancha_ids.*.exists' => 'Alguna de las canchas seleccionadas no existe en el catálogo.',
        ];
    }

    /**
     * Valida cada franja separada por comas dentro de `horarios_texto`.
     */
    protected function validarHorariosTexto(Validator $validator): void
    {
        $texto = $this->input('horarios_texto');
        if (! is_string($texto) || trim($texto) === '') {
            return;
        }

        $partes = array_map('trim', explode(',', $texto));
        if (count($partes) > 12) {
            $validator->errors()->add('horarios_texto', 'No se permiten más de 12 franjas horarias personalizadas.');

            return;
        }

        foreach ($partes as $parte) {
            if ($parte === '' || preg_match(self::REGEX_HORA_VALIDA, $parte) !== 1) {
                $validator->errors()->add(
                    'horarios_texto',
                    'Cada horario en las franjas personalizadas debe tener formato válido HH:MM entre 00:00 y 23:59 (por ejemplo, 09:00, 11:00).'
                );

                return;
            }
        }
    }

    /**
     * Extrae las franjas horarias normalizadas desde `horarios` o `horarios_texto`.
     *
     * @return array<int, string>
     */
    public function horariosNormalizados(): array
    {
        $horarios = $this->validated('horarios', []);
        if (is_array($horarios) && $horarios !== []) {
            return array_values(array_unique(array_map('strval', $horarios)));
        }

        $texto = $this->validated('horarios_texto');
        if (! is_string($texto) || trim($texto) === '') {
            return [];
        }

        $validos = [];
        foreach (array_map('trim', explode(',', $texto)) as $parte) {
            if (preg_match(self::REGEX_HORA_VALIDA, $parte) === 1 && ! in_array($parte, $validos, true)) {
                $validos[] = $parte;
            }
        }

        return $validos;
    }

    /**
     * Arma el arreglo de opciones listo para ProgramacionAutomaticaService.
     *
     * @return array<string, mixed>
     */
    public function construirOpcionesHorarios(): array
    {
        return [
            'horarios' => $this->horariosNormalizados(),
            'cancha_ids' => array_map('intval', (array) ($this->validated('cancha_ids') ?? [])),
            'respetar_capacidad_cancha' => $this->has('respetar_capacidad_cancha')
                ? $this->boolean('respetar_capacidad_cancha')
                : true,
        ];
    }
}
