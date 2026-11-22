<?php

namespace App\Services;

use App\Mail\CorreoComunicado;
use App\Models\Comunicado;
use App\Models\Equipo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
     *
     * @param  array{roles: list<string>, categoria_id: int|null, clubes: list<int>}  $filtros
     */
    public function enviar(string $asunto, string $mensaje, array $filtros, User $autor): Comunicado
    {
        $total = $this->contar($filtros);

        $comunicado = $this->auditoria->conMotivo('Envío de comunicado por correo', fn (): Comunicado => Comunicado::query()->create([
            'enviado_por_usuario_id' => $autor->id,
            'asunto' => $asunto,
            'mensaje' => $mensaje,
            'filtros' => $filtros,
            'total_destinatarios' => $total,
        ]));

        // Fuera de la transacción: el comunicado ya quedó guardado. La clave evita un duplicado si se reintenta
        $this->destinatarios($filtros)->select(['id', 'name', 'email'])->chunkById(self::LOTE, function (Collection $lote) use ($comunicado): void {
            foreach ($lote as $usuario) {
                Mail::to($usuario)->queue(new CorreoComunicado($comunicado->asunto, $comunicado->mensaje, 'comunicado:'.$comunicado->id.':'.$usuario->id));
            }
        });

        return $comunicado;
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
