<?php

namespace App\Http\Requests\Cedula;

// HU-036: incidencia en texto libre, con minuto opcional y el equipo, si aplica
class RegistrarIncidenciaRequest extends CapturaCedulaRequest
{
    protected function reglas(): array
    {
        return [
            'descripcion' => ['bail', 'required', 'string', 'max:1000'],
            'minuto' => ['bail', 'nullable', 'integer', 'between:0,150'],
            'equipo_id' => ['bail', 'nullable', 'integer'],
        ];
    }

    protected function mensajes(): array
    {
        return [
            'descripcion.required' => 'Describe la incidencia.',
            'descripcion.string' => 'La descripción de la incidencia no es válida.',
            'descripcion.max' => 'La descripción de la incidencia admite hasta :max caracteres.',
            'minuto.integer' => 'El minuto debe ser un número entero entre 0 y 150.',
            'minuto.between' => 'El minuto debe ser un número entero entre 0 y 150.',
            'equipo_id.integer' => 'El equipo elegido no es válido.',
        ];
    }

    /** @return array{descripcion: string, minuto: int|null, equipo_id: int|null} */
    public function datos(): array
    {
        $minuto = $this->validated('minuto');
        $equipo = $this->validated('equipo_id');

        return [
            'descripcion' => (string) $this->validated('descripcion'),
            'minuto' => $minuto === null ? null : (int) $minuto,
            'equipo_id' => $equipo === null ? null : (int) $equipo,
        ];
    }
}
