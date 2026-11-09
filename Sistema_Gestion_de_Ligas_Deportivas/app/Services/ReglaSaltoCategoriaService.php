<?php

namespace App\Services;

use App\Models\Categoria;
use Illuminate\Validation\ValidationException;

class ReglaSaltoCategoriaService
{
    public function validar(Categoria $categoriaOrigen, Categoria $categoriaDestino): void
    {
        if (abs($categoriaDestino->jerarquia_salto - $categoriaOrigen->jerarquia_salto) > 1) {
            throw ValidationException::withMessages([
                'categoria_id' => 'La inscripción solo permite una diferencia máxima de un nivel respecto a la categoría de origen.',
            ]);
        }
    }
}
