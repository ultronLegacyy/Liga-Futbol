<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Arr;

/**
 * Los campos de texto que llegan como arreglo (por ejemplo `lugar[]=x`) se vuelven null
 * antes de validar. Así la validación responde con su mensaje y el formulario se vuelve a
 * pintar con old() sin el error 500 de htmlspecialchars() al recibir un arreglo.
 */
trait DescartaArreglosEnCamposDeTexto
{
    /**
     * Campos de texto en notación de puntos; un * recorre las claves de un arreglo
     * (por ejemplo asistencias.*.nombre_asistente).
     *
     * @return list<string>
     */
    abstract protected function camposDeTexto(): array;

    protected function prepareForValidation(): void
    {
        // Solo los datos del formulario: los archivos subidos van en otra bolsa y no se tocan.
        $datos = $this->input();

        foreach ($this->camposDeTexto() as $campo) {
            $this->descartarArreglo($datos, explode('.', $campo));
        }

        $this->replace($datos);

        // El Form Request es una copia: al fallar la validación, la redirección guarda en
        // old() los datos de la petición original, así que también se limpian ahí.
        $original = request();

        if ($original !== $this) {
            $original->replace($datos);
        }
    }

    /**
     * @param  array<array-key, mixed>  $datos
     * @param  list<string>  $ruta
     */
    private function descartarArreglo(array &$datos, array $ruta): void
    {
        $clave = array_shift($ruta);

        if ($clave === '*') {
            foreach (array_keys($datos) as $indice) {
                if (is_array($datos[$indice])) {
                    $this->descartarArreglo($datos[$indice], $ruta);
                }
            }

            return;
        }

        if ($clave === null || ! Arr::exists($datos, $clave)) {
            return;
        }

        if ($ruta === []) {
            if (is_array($datos[$clave])) {
                $datos[$clave] = null;
            }

            return;
        }

        if (is_array($datos[$clave])) {
            $this->descartarArreglo($datos[$clave], $ruta);
        }
    }
}
