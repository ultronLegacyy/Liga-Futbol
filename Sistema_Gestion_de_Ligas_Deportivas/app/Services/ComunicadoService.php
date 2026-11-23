<?php

namespace App\Services;

use App\Mail\CorreoComunicado;
use App\Models\Comunicado;
use App\Models\Equipo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Comunicados por correo a grupos de usuarios (HU-066, RF-15.3). El grupo son uno o varios roles, con categoría
 * y clubes opcionales (decisión 5 de Daniel). Solo cuentas activas y con correo.
 */
class ComunicadoService
{
    private const LOTE = 200;

    public function __construct(private readonly AuditService $auditoria)
    {
    }

    /**
     * Cuántas personas recibirían el comunicado; un grupo vacío se rechaza con mensaje.
     *
     * @param  array{roles: list<string>, categoria_id: int|null, clubes: list<int>}  $filtros
     */
    public function contar(array $filtros): int
    {
        $total = $this->destinatarios($filtros)->count();

        if ($total === 0) {
            throw ValidationException::withMessages([
                'roles' => 'El grupo elegido no tiene destinatarios: ninguna cuenta activa con correo cumple esos roles, categoría y clubes.',
            ]);
        }

        return $total;
    }

    /**
     * Guarda el comunicado (auditado) y encola un correo por persona, por lotes, sin cargar todo el grupo en memoria.
     * El token viene de la revisión: un segundo envío con el mismo token (doble clic, «Atrás» y reenviar) se rechaza.
     *
     * @param  array{roles: list<string>, categoria_id: int|null, clubes: list<int>}  $filtros
     */
    public function enviar(string $asunto, string $mensaje, array $filtros, string $token, User $autor): Comunicado
    {
        $total = $this->contar($filtros);

        try {
            // Comunicado y correos en la misma transacción: con la cola `database`, si el encolado falla a la mitad se
            // revierte todo y el Secretario puede volver a enviar sin duplicar a nadie.
            // shortcut: atómico solo con la cola `database` en la misma conexión; con Redis u otra cola, revisar este orden
            return $this->auditoria->conMotivo('Envío de comunicado por correo', function () use ($asunto, $mensaje, $filtros, $token, $autor, $total): Comunicado {
                $comunicado = Comunicado::query()->create([
                    'enviado_por_usuario_id' => $autor->id,
                    'asunto' => $asunto,
                    'mensaje' => $mensaje,
                    'filtros' => $filtros,
                    'total_destinatarios' => $total,
                    'token' => $token,
                ]);

                $this->destinatarios($filtros)->select(['id', 'name', 'email'])->chunkById(self::LOTE, function (Collection $lote) use ($comunicado): void {
                    foreach ($lote as $usuario) {
                        Mail::to($usuario)->queue(new CorreoComunicado($comunicado->asunto, $comunicado->mensaje, 'comunicado:'.$comunicado->id.':'.$usuario->id));
                    }
                });

                return $comunicado;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'token' => 'Este comunicado ya se envió. Para mandarlo otra vez, revisa de nuevo los destinatarios.',
            ]);
        }
    }

    /**
     * @param  array{roles: list<string>, categoria_id: int|null, clubes: list<int>}  $filtros
     * @return Builder<User>
     */
    private function destinatarios(array $filtros): Builder
    {
        return User::query()
            ->where('estatus', 'activo')
            ->whereNotNull('email')->where('email', '!=', '')
            ->whereHas('roles', fn ($consulta) => $consulta->whereIn('name', $filtros['roles']))
            // Con categoría: las cuentas de los clubes que tienen un equipo en ella
            ->when($filtros['categoria_id'] !== null, fn (Builder $consulta) => $consulta->whereIn('club_id', Equipo::query()->select('club_id')
                ->whereHas('grupo', fn ($grupo) => $grupo->where('categoria_id', $filtros['categoria_id']))))
            ->when($filtros['clubes'] !== [], fn (Builder $consulta) => $consulta->whereIn('club_id', $filtros['clubes']));
    }
}
