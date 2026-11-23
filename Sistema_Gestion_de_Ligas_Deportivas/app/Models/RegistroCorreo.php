<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro de un correo del sistema (HU-067, RF-15.4).
 *
 * @property int $id
 * @property string $tipo
 * @property string $asunto
 * @property list<string> $destinatarios
 * @property string $estado
 * @property string|null $error
 * @property string|null $clave
 * @property \Illuminate\Support\Carbon|null $enviado_en
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class RegistroCorreo extends Model
{
    public const PENDIENTE = 'pendiente';

    public const ENVIADO = 'enviado';

    public const FALLIDO = 'fallido';

    public const ESTADOS = [self::PENDIENTE, self::ENVIADO, self::FALLIDO];

    // Nombre visible de cada tipo; uno desconocido se muestra como «Otro»
    public const TIPOS = [
        'recuperacion' => 'Recuperación de contraseña',
        'bloqueo' => 'Aviso de bloqueo',
        'prueba' => 'Correo de prueba',
        'aviso_programacion' => 'Aviso de programación',
        'recordatorio_pago' => 'Recordatorio de pago',
        'otro' => 'Otro',
    ];

    protected $table = 'registro_correos';

    protected $fillable = ['tipo', 'asunto', 'destinatarios', 'estado', 'error', 'clave', 'enviado_en'];

    protected function casts(): array
    {
        return [
            'destinatarios' => 'array',
            'enviado_en' => 'datetime',
        ];
    }

    public function nombreTipo(): string
    {
        return self::TIPOS[$this->tipo] ?? self::TIPOS['otro'];
    }
}
