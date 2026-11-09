<?php

namespace App\Services;

use App\Models\Categoria;
use Illuminate\Validation\ValidationException;

class ReglaSaltoCategoriaService
{
    public function validar(Categoria $categoriaOrigen, Categoria $categoriaDestino): void
    {
        if ($categoriaDestino->jerarquia_salto > $categoriaOrigen->jerarquia_salto + 1) {
            throw ValidationException::withMessages([
                'categoria_id' => 'La inscripción solo permite avanzar una categoría respecto a la categoría de origen del club.',
            ]);
        }
    }
}
