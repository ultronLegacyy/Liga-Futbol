<?php

namespace App\Services;

use App\Models\CedulaArbitral;
use App\Models\Jornada;
use App\Models\Partido;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Firmas de los capitanes y cierre de la cédula (HU-075, RF-08.1). La autorización le toca a quien lo llame.
 * La firma es un PNG dibujado con el dedo; va al disco privado `local` y la cédula guarda su ruta (decisión de Daniel).
 * El cierre es atómico (RNF-22): bloqueo, validación, estado de la cédula y del partido en una transacción.
 */
class CierreCedulaService
{
    public const LADOS = ['local', 'visitante'];

    // Tamaño máximo del PNG de una firma
    private const BYTES_MAXIMOS = 300 * 1024;

    public function __construct(
        private readonly AuditService $auditoria,
        private readonly CedulaService $cedulas,
        private readonly ValidacionCedulaService $validacion,
        private readonly ExpedienteDisciplinarioService $expedientes,
        private readonly GestionEstadoPartidoService $estados,
    ) {
    }

    // Guarda la firma del capitán de un lado; si ya había una, la reemplaza
    public function firmar(CedulaArbitral $cedula, string $lado, string $png): CedulaArbitral
    {
        if (! in_array($lado, self::LADOS, true)) {
            throw ValidationException::withMessages(['lado' => 'El equipo de la firma no es válido.']);
        }

        $this->validarPng($png);

        $ruta = 'cedulas/'.$cedula->id.'/firma-'.$lado.'-'.Str::uuid().'.png';
        Storage::disk('local')->put($ruta, $png);

        try {
            return $this->auditoria->conMotivo('Firma del capitán '.$lado.' en la cédula del partido '.$cedula->partido_id, function () use ($cedula, $lado, $ruta): CedulaArbitral {
                $bloqueada = $this->cedulas->bloquearAbierta($cedula);
                $partido = $bloqueada->partido()->with(['local:id,nombre', 'visitante:id,nombre'])->firstOrFail();
                $equipoId = $lado === 'local' ? $partido->equipo_local_id : $partido->equipo_visitante_id;
                $equipo = $lado === 'local' ? $partido->local?->nombre : $partido->visitante?->nombre;

                // Firma el capitán que el árbitro anotó en la alineación (decisión de Daniel)
                if (! $bloqueada->alineaciones()->where('equipo_id', $equipoId)->where('es_capitan', true)->whereNotNull('jugador_id')->exists()) {
                    throw ValidationException::withMessages(['firma' => 'Marca al capitán de '.$equipo.' en la alineación antes de su firma.']);
                }

                $columna = 'firma_capitan_'.$lado;
                $anterior = $bloqueada->{$columna};
                $bloqueada->update([$columna => $ruta]);

                if (is_string($anterior) && str_starts_with($anterior, 'cedulas/')) {
                    DB::afterCommit(fn () => Storage::disk('local')->delete($anterior));
                }

                return $bloqueada;
            });
        } catch (Throwable $error) {
            // Si no se guardó en la base, el archivo no se queda huérfano
            Storage::disk('local')->delete($ruta);

            throw $error;
        }
    }

    /**
     * Cierra la cédula: exige las dos firmas y que la validación no encuentre nada, y el partido pasa a finalizado
     * (respuesta 28 de Medina). Guarda todo o nada.
     */
    public function cerrar(CedulaArbitral $cedula, User $usuario): CedulaArbitral
    {
        $motivo = 'Cierre de la cédula arbitral del partido '.$cedula->partido_id;

        return $this->auditoria->conMotivo($motivo, function () use ($cedula, $usuario, $motivo): CedulaArbitral {
            // Mismo orden de bloqueo que los cambios de estado del partido: jornada, partido y luego la cédula
            $jornadaId = Partido::query()->whereKey($cedula->partido_id)->value('jornada_id');
            Jornada::query()->whereKey($jornadaId)->lockForUpdate()->firstOrFail();
            $partido = Partido::query()->whereKey($cedula->partido_id)->lockForUpdate()->firstOrFail();
            $bloqueada = $this->cedulas->bloquearAbierta($cedula);
            $faltantes = $this->firmasFaltantes($bloqueada);

            if ($faltantes !== []) {
                throw ValidationException::withMessages(['firmas' => 'No se puede cerrar la cédula sin la firma de los dos capitanes. Falta: '.implode(' y ', $faltantes).'.']);
            }

            return $this->finalizarCierre($bloqueada, $partido, $usuario, $motivo);
        });
    }

    /**
     * Respuesta 50 del PO (HU-037): el Capturista cierra la cédula transcrita del papel. El escaneo de la cédula
     * firmada por el árbitro y los capitanes sustituye a las firmas digitales; lo demás es igual al cierre digital:
     * se validan los hallazgos, el partido pasa a «finalizado» (respuesta 28) y se genera el expediente (HU-044).
     */
    public function cerrarDesdePapel(CedulaArbitral $cedula, UploadedFile $escaneo, User $usuario): CedulaArbitral
    {
        $extension = strtolower($escaneo->extension());
        $ruta = $escaneo->storeAs('cedulas/'.$cedula->id, 'papel-'.Str::uuid().'.'.$extension, 'local');

        if ($ruta === false) {
            throw ValidationException::withMessages(['cedula_escaneada' => 'No se pudo guardar el escaneo de la cédula; intenta de nuevo.']);
        }

        $motivo = 'Cierre de la cédula en papel del partido '.$cedula->partido_id;

        try {
            return $this->auditoria->conMotivo($motivo, function () use ($cedula, $usuario, $motivo, $ruta): CedulaArbitral {
                // Mismo orden de bloqueo que el cierre digital: jornada, partido y luego la cédula
                $jornadaId = Partido::query()->whereKey($cedula->partido_id)->value('jornada_id');
                Jornada::query()->whereKey($jornadaId)->lockForUpdate()->firstOrFail();
                $partido = Partido::query()->whereKey($cedula->partido_id)->lockForUpdate()->firstOrFail();
                $bloqueada = $this->cedulas->bloquearAbierta($cedula);

                if (! $bloqueada->esDePapel()) {
                    throw ValidationException::withMessages(['cedula' => 'Solo se cierra así una cédula transcrita desde el papel; la cédula capturada en campo la cierra el árbitro con las firmas.']);
                }

                $bloqueada->update(['cedula_papel_ruta' => $ruta]);

                return $this->finalizarCierre($bloqueada, $partido, $usuario, $motivo);
            });
        } catch (Throwable $error) {
            // Si el cierre no se guardó, el escaneo no se queda huérfano
            Storage::disk('local')->delete($ruta);

            throw $error;
        }
    }

    // Escaneo de la cédula en papel, solo si es de esta cédula y existe en el disco privado
    public function rutaDeEscaneo(CedulaArbitral $cedula): ?string
    {
        $ruta = $cedula->cedula_papel_ruta;

        return is_string($ruta) && str_starts_with($ruta, 'cedulas/'.$cedula->id.'/papel-') && Storage::disk('local')->exists($ruta) ? $ruta : null;
    }

    // Pasos comunes del cierre: hallazgos, estado de la cédula, partido finalizado y expediente disciplinario
    private function finalizarCierre(CedulaArbitral $bloqueada, Partido $partido, User $usuario, string $motivo): CedulaArbitral
    {
        $hallazgos = $this->validacion->validar($bloqueada);

        if ($hallazgos !== []) {
            throw ValidationException::withMessages(['validacion' => array_column($hallazgos, 'mensaje')]);
        }

        $bloqueada->update([
            'estado' => CedulaArbitral::CERRADA,
            'cerrada_por_id' => $usuario->id,
            'cerrada_en' => now(),
        ]);

        // Respuesta 28 del PO: el partido pasa a finalizado al cerrarse la cédula (HU-075)
        $this->estados->finalizarPorCierreDeCedula($partido, $usuario->id, $motivo);

        // HU-044 / RF-10.1: Generar expediente disciplinario si la cédula contiene incidencias
        $this->expedientes->generarDesdeCedulaSiAplica($bloqueada);

        return $bloqueada;
    }

    /**
     * Nombres de los equipos cuyo capitán todavía no firma.
     *
     * @return list<string>
     */
    public function firmasFaltantes(CedulaArbitral $cedula): array
    {
        $partido = $cedula->partido()->with(['local:id,nombre', 'visitante:id,nombre'])->firstOrFail();
        $faltantes = [];

        foreach (self::LADOS as $lado) {
            if (! $this->tieneFirma($cedula, $lado)) {
                $faltantes[] = 'el capitán de '.($lado === 'local' ? $partido->local?->nombre : $partido->visitante?->nombre);
            }
        }

        return $faltantes;
    }

    // Una firma cuenta si su archivo existe en el disco privado (las firmas sembradas sin archivo no cuentan)
    public function tieneFirma(CedulaArbitral $cedula, string $lado): bool
    {
        return $this->rutaDeFirma($cedula, $lado) !== null;
    }

    public function rutaDeFirma(CedulaArbitral $cedula, string $lado): ?string
    {
        $ruta = in_array($lado, self::LADOS, true) ? $cedula->{'firma_capitan_'.$lado} : null;

        return is_string($ruta) && str_starts_with($ruta, 'cedulas/'.$cedula->id.'/') && Storage::disk('local')->exists($ruta) ? $ruta : null;
    }

    // Un PNG real, de tamaño razonable; no se confía en lo que diga el navegador
    private function validarPng(string $png): void
    {
        $info = strlen($png) <= self::BYTES_MAXIMOS && str_starts_with($png, "\x89PNG\r\n\x1a\n") ? @getimagesizefromstring($png) : false;

        if ($info === false || $info[2] !== IMAGETYPE_PNG || $info[0] < 50 || $info[1] < 30 || $info[0] > 3000 || $info[1] > 3000) {
            throw ValidationException::withMessages(['firma' => 'La firma no es una imagen PNG válida de hasta 300 KB.']);
        }
    }
}
