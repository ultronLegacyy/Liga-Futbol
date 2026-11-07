<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\Persona;
use Illuminate\Support\Str;

// Detección de posibles duplicados para mantener limpio el padrón (HU-014, RF-03.3).
// Solo alerta: quien registra decide si confirma, salvo la CURP idéntica, que siempre reutiliza la identidad (HU-013)
class DuplicadosService
{
    // Una CURP que coincide en sus primeros 16 caracteres comparte nombre, fecha, sexo y entidad; solo cambia la homoclave
    private const LARGO_RAIZ_CURP = 16;

    /**
     * Personas parecidas a los datos capturados, con el motivo de la alerta.
     *
     * @param  array{nombre: string, apellidos: string, fecha_nacimiento: string, curp: string}  $datos
     * @return list<array{persona: Persona, motivo: string}>
     */
    public function personasSimilares(array $datos): array
    {
        $curp = mb_strtoupper(trim($datos['curp']));
        $nombreCompleto = $this->normalizar($datos['nombre'].' '.$datos['apellidos']);

        $candidatas = Persona::query()
            ->where('curp', $curp)
            ->orWhere('curp', 'like', substr($curp, 0, self::LARGO_RAIZ_CURP).'%')
            ->orWhereDate('fecha_nacimiento', $datos['fecha_nacimiento'])
            ->orderBy('apellidos')
            ->orderBy('nombre')
            ->get();

        $similares = [];

        foreach ($candidatas as $persona) {
            $mismoNombre = $this->normalizar($persona->nombre.' '.$persona->apellidos) === $nombreCompleto;
            $mismaFecha = $persona->fecha_nacimiento->format('Y-m-d') === $datos['fecha_nacimiento'];

            $motivo = match (true) {
                $persona->curp === $curp && ! ($mismoNombre && $mismaFecha) => 'Ya existe esta CURP con otro nombre o fecha de nacimiento.',
                $persona->curp === $curp => null,
                str_starts_with($persona->curp, substr($curp, 0, self::LARGO_RAIZ_CURP)) => 'Su CURP solo difiere en los dos últimos caracteres.',
                $mismoNombre && $mismaFecha => 'Tiene el mismo nombre completo y la misma fecha de nacimiento.',
                default => null,
            };

            if ($motivo !== null) {
                $similares[] = ['persona' => $persona, 'motivo' => $motivo];
            }
        }

        return $similares;
    }

    /**
     * Equipos de la misma categoría con el mismo nombre; solo se ignoran mayúsculas, acentos y espacios de más (acuerdo del equipo, 05/11/2026).
     *
     * @return list<array{equipo: Equipo, motivo: string}>
     */
    public function equiposSimilares(string $nombre, int $categoriaId): array
    {
        $buscado = $this->normalizar($nombre);

        $equipos = Equipo::query()
            ->with('club:id,nombre')
            ->whereHas('grupo', fn ($grupo) => $grupo->where('categoria_id', $categoriaId))
            ->orderBy('nombre')
            ->get(['id', 'club_id', 'grupo_id', 'nombre']);

        $similares = [];

        foreach ($equipos as $equipo) {
            if ($this->normalizar($equipo->nombre) === $buscado) {
                $similares[] = ['equipo' => $equipo, 'motivo' => 'Tiene el mismo nombre.'];
            }
        }

        return $similares;
    }

    /**
     * Huella de los datos que el usuario confirmó, para que la confirmación no sirva si después cambia los datos.
     *
     * @param  array<string, mixed>  $datos
     */
    public function huella(array $datos): string
    {
        return hash('sha256', $this->normalizar(implode('|', array_map(fn ($valor) => (string) $valor, $datos))));
    }

    /**
     * Minúsculas, sin acentos, sin signos y con un solo espacio entre palabras.
     */
    private function normalizar(string $texto): string
    {
        $texto = Str::lower(Str::ascii($texto));
        $texto = (string) preg_replace('/[^a-z0-9]+/', ' ', $texto);

        return trim($texto);
    }

}
