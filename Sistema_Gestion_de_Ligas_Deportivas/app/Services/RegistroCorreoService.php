<?php

namespace App\Services;

use App\Mail\CorreoDelSistema;
use App\Models\RegistroCorreo;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Registro de cada correo que manda el sistema (HU-067, RF-15.4). Lo alimentan los eventos de correo de Laravel
 * (RegistrarCorreo); un envío que falla lo marca quien lo atrapa. No guarda el cuerpo (decisión de Daniel, 20/11):
 * así el enlace de recuperación de contraseña nunca llega a la base.
 */
class RegistroCorreoService
{
    private const CABECERA_CLAVE = 'X-SGLD-Clave';

    /**
     * Antes de enviar: deja el correo en «pendiente». Devuelve false si esa clave ya se envió, para cancelar el reenvío.
     */
    public function alEnviar(Email $correo): bool
    {
        $clave = $this->clave($correo);
        $registro = RegistroCorreo::query()->firstOrNew(['clave' => $clave]);

        if ($registro->exists && $registro->estado === RegistroCorreo::ENVIADO) {
            return false;
        }

        $registro->fill([
            'tipo' => $this->cabecera($correo, 'X-SGLD-Tipo') ?? 'otro',
            'asunto' => Str::limit((string) ($correo->getSubject() ?? ''), 250, '') ?: '(sin asunto)',
            'destinatarios' => array_map(fn (Address $direccion): string => $direccion->getAddress(), [...$correo->getTo(), ...$correo->getCc(), ...$correo->getBcc()]),
            'estado' => RegistroCorreo::PENDIENTE,
            'error' => null,
        ])->save();

        return true;
    }

    // Después de enviar: «enviado», con la hora
    public function alConfirmar(Email $correo): void
    {
        RegistroCorreo::query()->where('clave', $this->clave($correo))
            ->update(['estado' => RegistroCorreo::ENVIADO, 'enviado_en' => now(), 'error' => null]);
    }

    // Un envío que no salió queda «fallido», aunque haya fallado antes de llegar al transporte
    public function marcarFallido(CorreoDelSistema $correo, Throwable $error): void
    {
        $registro = RegistroCorreo::query()->firstOrNew(['clave' => $correo->clave]);

        if (! $registro->exists) {
            $registro->fill([
                'tipo' => $correo->tipo,
                'asunto' => Str::limit($correo->asunto, 250, ''),
                'destinatarios' => array_values(array_map(fn (array $destino): string => (string) $destino['address'], $correo->to)),
            ]);
        }

        $registro->fill(['estado' => RegistroCorreo::FALLIDO, 'error' => class_basename($error)])->save();
    }

    // Un correo sin clave (de fuera de CorreoDelSistema) recibe una aquí, para que «enviado» encuentre su fila
    private function clave(Email $correo): string
    {
        $clave = $this->cabecera($correo, self::CABECERA_CLAVE);

        if ($clave === null) {
            $clave = (string) Str::uuid();
            $correo->getHeaders()->addTextHeader(self::CABECERA_CLAVE, $clave);
        }

        return $clave;
    }

    private function cabecera(Email $correo, string $nombre): ?string
    {
        $valor = $correo->getHeaders()->get($nombre)?->getBodyAsString();

        return $valor === null || $valor === '' ? null : $valor;
    }
}
