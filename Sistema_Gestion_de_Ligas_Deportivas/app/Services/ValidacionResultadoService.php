<?php

namespace App\Services;

use App\Events\ResultadoValidado;
use App\Models\CedulaArbitral;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * HU-040 (RF-08.4): validación oficial de un resultado capturado.
 * Solo se valida una cédula cerrada (HU-036/HU-037 la capturan y HU-075 la cierra con las firmas).
 * Mientras no se valide, el resultado no es oficial y no cuenta para la tabla de posiciones (HU-041).
 */
class ValidacionResultadoService
{
    public function __construct(private readonly AuditService $auditoria)
    {
    }

    /**
     * Resultados cerrados que esperan la validación, los más antiguos primero.
     *
     * @return Collection<int, CedulaArbitral>
     */
    public function pendientes(): Collection
    {
        return CedulaArbitral::query()
            ->porValidar()
            ->with(['partido.local', 'partido.visitante', 'partido.jornada.grupo.categoria', 'partido.jornada.grupo.temporada'])
            ->orderBy('cerrada_en')
            ->orderBy('id')
            ->get();
    }

    /**
     * Últimos resultados validados, para consulta.
     *
     * @return Collection<int, CedulaArbitral>
     */
    public function recientes(int $limite = 20): Collection
    {
        return CedulaArbitral::query()
            ->oficiales()
            ->with(['partido.local', 'partido.visitante', 'partido.jornada.grupo.categoria', 'validadaPor:id,name'])
            ->orderByDesc('validada_en')
            ->orderByDesc('id')
            ->limit($limite)
            ->get();
    }

    /**
     * Valida el resultado y avisa a HU-041 para recalcular la tabla, todo en una sola transacción:
     * si el registro en la bitácora o el cálculo fallan, el resultado sigue sin validar.
     */
    public function validar(CedulaArbitral $cedula, User $usuario): CedulaArbitral
    {
        return $this->auditoria->conMotivo(
            'Validación oficial del resultado del partido '.$cedula->partido_id,
            function () use ($cedula, $usuario): CedulaArbitral {
                $bloqueada = CedulaArbitral::query()->whereKey($cedula->getKey())->lockForUpdate()->firstOrFail();

                if (! $bloqueada->estaCerrada()) {
                    throw ValidationException::withMessages([
                        'resultado' => 'Solo se valida un resultado con la cédula cerrada y firmada por los capitanes.',
                    ]);
                }

                if ($bloqueada->validada_en !== null) {
                    throw ValidationException::withMessages([
                        'resultado' => 'Este resultado ya fue validado y es oficial.',
                    ]);
                }

                if ($bloqueada->partido?->jornada?->grupo?->estaCerrado()) {
                    throw ValidationException::withMessages([
                        'resultado' => 'La temporada de este partido está cerrada: sus resultados solo se consultan.',
                    ]);
                }

                $bloqueada->update([
                    'validada_por_id' => $usuario->id,
                    'validada_en' => now(),
                ]);

                ResultadoValidado::dispatch($bloqueada);

                return $bloqueada;
            }
        );
    }
}
