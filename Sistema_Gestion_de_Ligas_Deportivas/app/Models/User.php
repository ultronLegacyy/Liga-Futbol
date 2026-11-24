<?php

namespace App\Models;

use App\Mail\CorreoDelSistema;
use App\Models\Concerns\Auditable;
use App\Services\RegistroCorreoService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;
use Throwable;

class User extends Authenticatable
{
    use Auditable;

    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * Nombre de la tabla asociada al modelo.
     */
    protected $table = 'usuarios';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'password_hash',
        'rol',
        'estatus',
        'club_id',
        'requires_password_change', // Agregado para HU-080
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'password_hash',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'requires_password_change' => 'boolean', // Casteo requerido para HU-080
        ];
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    /**
     * Cuentas activas con el rol «Delegado del Club» de esos clubes (avisos de HU-064 y recordatorios de HU-065).
     * whereHas en lugar de role(): si el rol no está sembrado devuelve vacío en vez de lanzar una excepción.
     *
     * @param  Builder<User>  $query
     * @param  iterable<int, mixed>  $clubes
     * @return Builder<User>
     */
    public function scopeDelegadosActivos(Builder $query, iterable $clubes): Builder
    {
        return $query->whereHas('roles', fn ($consulta) => $consulta->where('name', 'Delegado del Club'))
            ->whereIn('club_id', $clubes)->where('estatus', 'activo');
    }

    /** @return BelongsTo<Persona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    /**
     * Clubes de los que es propietario legal (SCRUM-25).
     */
    /** @return HasMany<Club, $this> */
    public function clubesPropietario(): HasMany
    {
        return $this->hasMany(Club::class, 'propietario_usuario_id');
    }

    /**
     * Determina la ruta de inicio o módulo principal según los roles asignados al usuario.
     * Implementa la redirección de aterrizaje para HU-071, HU-072 y HU-073 (Criterio 1)
     * y HU-080 (Criterio 3 tras el cambio obligatorio de contraseña).
     *
     * @return string URL absoluta del módulo autorizado para el rol del usuario.
     */
    public function rutaInicio(): string
    {
        // Administrador General: acceso al panel central administrativo
        if ($this->hasRole('Administrador General')) {
            return route('admin.dashboard');
        }

        // HAB-S7 (S7-T04): el Árbitro en Campo empieza en «Mis partidos», donde abre y captura sus cédulas
        if ($this->hasRole('Árbitro en Campo')) {
            return route('cedulas.mis-partidos');
        }

        // HAB-S7 (S7-T02): los demás roles empiezan en el inicio del panel, con las tarjetas de sus módulos.
        // Así ningún rol aterriza en una pantalla que le respondería 403 (HU-071 a HU-073, HU-080)
        return route('panel.inicio');
    }

    /**
     * Envía el enlace de recuperación de contraseña con el correo del sistema (HU-003).
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        // Con APP_URL y no con route() absoluto: route() usa la cabecera Host de la petición y permitiría
        // mandar a la víctima un enlace con su token hacia otro dominio (RNF-42)
        $enlace = rtrim((string) config('app.url'), '/').route('password.reset', ['token' => $token, 'email' => $this->email], false);
        $minutos = (int) config('auth.passwords.users.expire');

        $correo = new CorreoDelSistema(
            'Recupera tu contraseña del SGLD',
            "Hola, {$this->name}:\n\n"
                ."Recibimos una solicitud para restablecer la contraseña de tu cuenta en el Sistema de Gestión de Ligas Deportivas.\n\n"
                ."El enlace vence en {$minutos} minutos y solo se puede usar una vez.\n\n"
                .'Si no lo solicitaste, puedes ignorar este correo: tu contraseña no cambiará.',
            $enlace,
            'Restablecer mi contraseña',
            tipo: 'recuperacion',
        );

        // HU-067: el fallo queda en el registro de correos; RecuperacionContrasenaController lo atrapa y responde lo mismo
        try {
            Mail::to($this)->send($correo);
        } catch (Throwable $error) {
            app(RegistroCorreoService::class)->marcarFallido($correo, $error);

            throw $error;
        }
    }
}
