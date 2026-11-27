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
            'horarios' => ['bail', 'nullable', 'array', 'max:12'],
            'horarios.*' => ['bail', 'required', 'string', 'regex:'.self::REGEX_HORA_VALIDA],
            'horarios_texto' => ['bail', 'nullable', 'string', 'max:255'],
            'cancha_ids' => ['bail', 'nullable', 'array'],
            'cancha_ids.*' => ['bail', 'integer', 'exists:canchas,id'],
        ];
    }

    /**
     * Mensajes de error comunes en español para que no existan respuestas 422 en inglés.
     *
     * @return array<string, string>
     */
    protected function mensajesOpcionesHorarios(): array
    {
        return [
            'horarios.array' => 'Las franjas horarias deben enviarse como una lista válida.',
            'horarios.max' => 'No se permiten más de 12 franjas horarias personalizadas.',
            'horarios.*.required' => 'Cada franja horaria seleccionada es obligatoria.',
            'horarios.*.string' => 'Cada franja horaria debe ser una cadena de texto en formato HH:MM.',
            'horarios.*.regex' => 'Cada franja horaria debe tener formato válido HH:MM entre 00:00 y 23:59 (por ejemplo, 09:00).',
            'horarios_texto.string' => 'Las franjas horarias deben enviarse como texto separado por comas.',
            'horarios_texto.max' => 'Las franjas horarias personalizadas no deben exceder los 255 caracteres.',
            'cancha_ids.array' => 'La selección de canchas debe enviarse como una lista válida.',
            'cancha_ids.*.integer' => 'Cada identificador de cancha seleccionada debe ser un número entero.',
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
        ];
    }
}
