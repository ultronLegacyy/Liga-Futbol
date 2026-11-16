<?php

namespace App\Http\Requests\Cedula;

// HU-036: asistencia, número de camiseta y capitán de un jugador de la alineación
class GuardarAlineacionRequest extends CapturaCedulaRequest
{
    protected function reglas(): array
    {
        return [
            'asistio' => ['bail', 'sometimes', 'boolean'],
            'numero_camiseta' => ['bail', 'sometimes', 'nullable', 'integer', 'between:0,999'],
            'es_capitan' => ['bail', 'sometimes', 'boolean'],
        ];
    }

    protected function mensajes(): array
    {
        return [
            'asistio.boolean' => 'La asistencia debe ser sí o no.',
            'numero_camiseta.integer' => 'El número de camiseta debe ser un número entero.',
            'numero_camiseta.between' => 'El número de camiseta debe estar entre 0 y 999.',
            'es_capitan.boolean' => 'El capitán debe ser sí o no.',
        ];
    }

    /** @return array{asistio?: bool, numero_camiseta?: int|null, es_capitan?: bool} */
    public function datos(): array
    {
        $datos = [];

        if ($this->has('asistio')) {
            $datos['asistio'] = $this->boolean('asistio');
        }

        if ($this->has('numero_camiseta')) {
            $numero = $this->validated('numero_camiseta');
            $datos['numero_camiseta'] = $numero === null ? null : (int) $numero;
        }

        if ($this->has('es_capitan')) {
            $datos['es_capitan'] = $this->boolean('es_capitan');
        }

        return $datos;
    }
}
