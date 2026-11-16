<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Constancia del aviso de privacidad y del consentimiento del tutor de una inscripción (HU-020, OR-01).
 *
 * No usa Auditable: la auditoría registra la constancia sin los datos de contacto del tutor
 * (RegistroJugadorService), para no copiar datos personales a la bitácora.
 *
 * @property int $id
 * @property int $persona_id
 * @property int|null $jugador_id
 * @property string $version_aviso
 * @property \Illuminate\Support\Carbon $aviso_mostrado_en
 * @property bool $requiere_tutor
 * @property string|null $motivo_tutor
 * @property string|null $tutor_nombre_completo
 * @property string|null $tutor_parentesco
 * @property string|null $tutor_telefono
 * @property string|null $tutor_correo
 * @property \Illuminate\Support\Carbon|null $tutor_consintio_en
 * @property int $registrado_por_usuario_id
 * @property string|null $ip
 */
class ConsentimientoPrivacidadJugador extends Model
{
    public const MOTIVO_RAMA_INFANTIL = 'rama_infantil';

    public const MOTIVO_CATEGORIA_JUVENIL = 'categoria_juvenil';

    public const MOTIVO_MENOR_DE_EDAD = 'menor_de_edad';

    protected $table = 'consentimientos_privacidad_jugador';

    protected $fillable = [
        'persona_id',
        'jugador_id',
        'version_aviso',
        'aviso_mostrado_en',
        'requiere_tutor',
        'motivo_tutor',
        'tutor_nombre_completo',
        'tutor_parentesco',
        'tutor_telefono',
        'tutor_correo',
        'tutor_consintio_en',
        'registrado_por_usuario_id',
        'ip',
    ];

    protected $hidden = ['tutor_telefono', 'tutor_correo', 'ip'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'aviso_mostrado_en' => 'datetime',
            'requiere_tutor' => 'boolean',
            'tutor_telefono' => 'encrypted',
            'tutor_correo' => 'encrypted',
            'tutor_consintio_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Jugador, $this> */
    public function jugador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class, 'jugador_id');
    }

    /** @return BelongsTo<Persona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    /** @return BelongsTo<User, $this> */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_usuario_id');
    }
}
