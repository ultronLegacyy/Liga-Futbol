<?php

namespace App\Http\Requests\Cedula;

use App\Models\EventoPartido;
use Illuminate\Validation\Rule;

// HU-036: gol, amarilla o roja de un jugador de la alineación; la expulsión es la Roja
class RegistrarEventoRequest extends CapturaCedulaRequest
{
    protected function reglas(): array
    {
        return [
            'jugador_id' => ['bail', 'required', 'integer'],
            'tipo' => ['bail', 'required', 'string', Rule::in(EventoPartido::TIPOS)],
            'minuto' => ['bail', 'nullable', 'integer', 'between:0,150'],
            'autogol' => ['bail', 'sometimes', 'boolean'],
        ];
    }

    protected function mensajes(): array
    {
        return [
            'jugador_id.required' => 'Elige al jugador.',
            'jugador_id.integer' => 'El jugador elegido no es válido.',
            'tipo.required' => 'Elige si es gol, amarilla o roja.',
            'tipo.string' => 'El evento debe ser Gol, Amarilla o Roja.',
            'tipo.in' => 'El evento debe ser Gol, Amarilla o Roja.',
            'minuto.integer' => 'El minuto debe ser un número entero entre 0 y 150.',
            'minuto.between' => 'El minuto debe ser un número entero entre 0 y 150.',
            'autogol.boolean' => 'El autogol debe ser sí o no.',
        ];
    }

    /** @return array{jugador_id: int, tipo: string, minuto: int|null, autogol: bool} */
    public function datos(): array
    {
        $minuto = $this->validated('minuto');

        return [
            'jugador_id' => (int) $this->validated('jugador_id'),
            'tipo' => (string) $this->validated('tipo'),
            'minuto' => $minuto === null ? null : (int) $minuto,
            'autogol' => $this->boolean('autogol'),
        ];
    }
}
