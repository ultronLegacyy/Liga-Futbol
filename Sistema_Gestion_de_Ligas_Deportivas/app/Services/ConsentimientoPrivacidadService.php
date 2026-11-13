<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\ConsentimientoPrivacidadJugador;
use App\Models\Jugador;
use App\Models\Persona;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Aviso de privacidad y consentimiento del tutor al registrar a un jugador (HU-020, OR-01).
 *
 * El registro (RegistroJugadorService) llama a exigir() antes de crear la inscripción y a
 * registrar() después, dentro de la misma transacción: sin constancia no hay inscripción.
 */
class ConsentimientoPrivacidadService
{
    public function versionAviso(): string
    {
        return (string) config('privacidad.aviso_jugador.version');
    }

    /**
     * Por qué el registro exige el consentimiento del tutor, o null si no lo exige.
     * Prioridad: rama Infantil, categoría Juvenil y, para el resto, minoría de edad a la fecha del registro.
     */
    public function motivoTutor(Persona $persona, Categoria $categoria, ?CarbonInterface $fecha = null): ?string
    {
        $motivoCategoria = $this->motivoTutorPorCategoria($categoria);

        if ($motivoCategoria !== null) {
            return $motivoCategoria;
        }

        // A la fecha del registro con la hora de México (ReglaEdadCategoriaService::edadCumplida).
        return ReglaEdadCategoriaService::edadCumplida($persona, $fecha) < (int) config('privacidad.mayoria_de_edad')
            ? ConsentimientoPrivacidadJugador::MOTIVO_MENOR_DE_EDAD
            : null;
    }

    /** Motivo por el que la categoría exige siempre al tutor (rama Infantil o categoría Juvenil), o null. */
    public function motivoTutorPorCategoria(Categoria $categoria): ?string
    {
        if (in_array($categoria->rama, (array) config('privacidad.ramas_con_tutor'), true)) {
            return ConsentimientoPrivacidadJugador::MOTIVO_RAMA_INFANTIL;
        }

        $nombre = Str::lower(Str::ascii($categoria->nombre));

        foreach ((array) config('privacidad.prefijos_categoria_con_tutor') as $prefijo) {
            if (Str::startsWith($nombre, Str::lower(Str::ascii((string) $prefijo)))) {
                return ConsentimientoPrivacidadJugador::MOTIVO_CATEGORIA_JUVENIL;
            }
        }

        return null;
    }

    /**
     * Comprueba que se mostró y aceptó el aviso y, si corresponde, que el tutor dio su consentimiento.
     *
     * @param  array<string, mixed>  $datos
     * @return array{motivo: string|null, tutor: array{nombre_completo: string, parentesco: string, telefono: string, correo: string|null}|null}
     */
    public function exigir(Persona $persona, Categoria $categoria, array $datos): array
    {
        if (! filter_var($datos['aviso_privacidad_aceptado'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw ValidationException::withMessages([
                'aviso_privacidad_aceptado' => 'Debes mostrar el aviso de privacidad y confirmar que el jugador o su tutor lo leyó antes de guardar el registro.',
            ]);
        }

        $motivo = $this->motivoTutor($persona, $categoria);

        if ($motivo === null) {
            return ['motivo' => null, 'tutor' => null];
        }

        $tutor = is_array($datos['tutor'] ?? null) ? $datos['tutor'] : [];
        $texto = static fn (string $campo): string => trim((string) ($tutor[$campo] ?? ''));
        $errores = [];

        if ($texto('nombre_completo') === '') {
            $errores['tutor.nombre_completo'] = 'El nombre completo del tutor es obligatorio para registrar a un jugador menor de edad.';
        }

        if ($texto('parentesco') === '') {
            $errores['tutor.parentesco'] = 'Indica el parentesco del tutor con el jugador.';
        }

        if ($texto('telefono') === '') {
            $errores['tutor.telefono'] = 'El teléfono del tutor es obligatorio.';
        }

        if (! filter_var($tutor['consentimiento'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $errores['tutor.consentimiento'] = 'Sin el consentimiento del tutor no se puede registrar a un jugador de la rama infantil, de categoría Juvenil o menor de edad.';
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        $correo = $texto('correo');

        return [
            'motivo' => $motivo,
            'tutor' => [
                'nombre_completo' => preg_replace('/\s+/u', ' ', $texto('nombre_completo')) ?? $texto('nombre_completo'),
                'parentesco' => $texto('parentesco'),
                'telefono' => $texto('telefono'),
                'correo' => $correo === '' ? null : Str::lower($correo),
            ],
        ];
    }

    /**
     * Guarda la constancia de la inscripción. Debe llamarse dentro de la transacción del registro.
     *
     * @param  array{motivo: string|null, tutor: array{nombre_completo: string, parentesco: string, telefono: string, correo: string|null}|null}  $exigido
     */
    public function registrar(Jugador $jugador, array $exigido, User $usuario, ?string $ip = null): ConsentimientoPrivacidadJugador
    {
        $ahora = now();
        $tutor = $exigido['tutor'];

        return ConsentimientoPrivacidadJugador::query()->create([
            'persona_id' => $jugador->persona_id,
            'jugador_id' => $jugador->getKey(),
            'version_aviso' => $this->versionAviso(),
            'aviso_mostrado_en' => $ahora,
            'requiere_tutor' => $exigido['motivo'] !== null,
            'motivo_tutor' => $exigido['motivo'],
            'tutor_nombre_completo' => $tutor['nombre_completo'] ?? null,
            'tutor_parentesco' => $tutor['parentesco'] ?? null,
            'tutor_telefono' => $tutor['telefono'] ?? null,
            'tutor_correo' => $tutor['correo'] ?? null,
            'tutor_consintio_en' => $tutor === null ? null : $ahora,
            'registrado_por_usuario_id' => $usuario->getKey(),
            'ip' => $ip,
        ]);
    }
}
