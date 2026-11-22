<?php

namespace App\Services;

use App\Mail\CorreoDelSistema;
use App\Models\RegistroCorreo;
use Illuminate\Database\UniqueConstraintViolationException;
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
     * Antes de enviar: reclama la clave dejando el correo en «pendiente». Devuelve false para cancelar el envío si esa
     * clave ya se envió o si otro proceso la está enviando (dos workers con el mismo aviso).
     */
    public function alEnviar(Email $correo): bool
    {
        $clave = $this->clave($correo);

        try {
            RegistroCorreo::query()->create([
                'clave' => $clave,
                'tipo' => $this->cabecera($correo, 'X-SGLD-Tipo') ?? 'otro',
                'asunto' => Str::limit((string) ($correo->getSubject() ?? ''), 250, '') ?: '(sin asunto)',
                'destinatarios' => array_map(fn (Address $direccion): string => $direccion->getAddress(), [...$correo->getTo(), ...$correo->getCc(), ...$correo->getBcc()]),
                'estado' => RegistroCorreo::PENDIENTE,
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            // La clave ya tiene fila: solo pasa quien la reclame con el UPDATE de abajo
        }

        // Se reclama un envío fallido, o un «pendiente» abandonado: el reintento de la cola llega 60 s después.
        // shortcut: un envío SMTP de más de 30 s podría salir dos veces; subir el margen si el proveedor es tan lento
        return RegistroCorreo::query()->where('clave', $clave)
            ->where(fn ($consulta) => $consulta->where('estado', RegistroCorreo::FALLIDO)
                ->orWhere(fn ($abandonado) => $abandonado->where('estado', RegistroCorreo::PENDIENTE)->where('updated_at', '<', now()->subSeconds(30))))
            ->update(['estado' => RegistroCorreo::PENDIENTE, 'error' => null]) === 1;
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
