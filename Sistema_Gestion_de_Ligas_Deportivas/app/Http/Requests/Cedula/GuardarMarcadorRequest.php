<?php

namespace App\Http\Requests\Cedula;

// HU-036: marcador del partido en la cédula
class GuardarMarcadorRequest extends CapturaCedulaRequest
{
    protected function reglas(): array
    {
        return [
            'goles_local' => ['bail', 'required', 'integer', 'between:0,99'],
            'goles_visitante' => ['bail', 'required', 'integer', 'between:0,99'],
        ];
    }

    protected function mensajes(): array
    {
        return [
            'goles_local.required' => 'Captura los goles del equipo local.',
            'goles_local.integer' => 'Los goles del equipo local deben ser un número entero.',
            'goles_local.between' => 'Los goles de cada equipo deben estar entre 0 y 99.',
            'goles_visitante.required' => 'Captura los goles del equipo visitante.',
            'goles_visitante.integer' => 'Los goles del equipo visitante deben ser un número entero.',
            'goles_visitante.between' => 'Los goles de cada equipo deben estar entre 0 y 99.',
        ];
    }
}
