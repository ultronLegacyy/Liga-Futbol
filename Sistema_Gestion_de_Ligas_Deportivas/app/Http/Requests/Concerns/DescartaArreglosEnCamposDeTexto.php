<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Arr;

/**
 * Antes de validar:
 * - se descartan los campos que no son de este formulario (claves de primer nivel de
 *   rules(), más _token y _method). En una pantalla con varios formularios, old() solo
 *   guarda los del formulario enviado, y un arreglo en un campo de otro formulario
 *   (por ejemplo `descripcion[]=x` al enviar un comunicado) ya no llega a la vista;
 * - los campos de texto que llegan como arreglo (por ejemplo `lugar[]=x`) se vuelven null.
 *
 * Así la validación responde con su mensaje y el formulario se vuelve a pintar con old()
 * sin el error 500 de htmlspecialchars() al recibir un arreglo.
 */
trait DescartaArreglosEnCamposDeTexto
{
    /** Campos que no están en rules() pero que el formulario siempre envía. */
    private const CAMPOS_DEL_FORMULARIO = ['_token', '_method'];

    /**
     * Campos de texto en notación de puntos; un * recorre las claves de un arreglo
     * (por ejemplo asistencias.*.nombre_asistente).
     *
     * @return list<string>
     */
    abstract protected function camposDeTexto(): array;

    /** @return array<string, mixed> */
    abstract public function rules(): array;

    protected function prepareForValidation(): void
    {
        // Solo los datos del formulario: los archivos subidos van en otra bolsa y no se tocan.
        $permitidos = array_unique(array_merge(
            self::CAMPOS_DEL_FORMULARIO,
            array_map(static fn (string $regla): string => explode('.', $regla, 2)[0], array_keys($this->rules())),
        ));
        $datos = Arr::only($this->input(), $permitidos);

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
