<?php

namespace App\Models;

use App\Mail\CorreoDelSistema;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

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

        // HU-071 (Roles de Gobernanza):
        // Tesorero: consulta y administración financiera
        if ($this->hasRole('Tesorero')) {
            return route('cargos.index');
        }

        // Secretario: administración del padrón general de personas
        if ($this->hasRole('Secretario')) {
            return route('personas.index');
        }

        // HU-072 (Roles Operativos y Disciplinarios):
        // Programador de Partidos: gestión de disponibilidad de canchas y sedes
        if ($this->hasRole('Programador de Partidos')) {
            return route('canchas.index');
        }

        // Árbitro en Campo y Comisión Arbitral: padrón de colegiados arbitrales
        if ($this->hasRole('Comisión Arbitral') || $this->hasRole('Árbitro en Campo')) {
            return route('arbitros.index');
        }

        // HU-073 (Roles de Clubes y Jugadores):
        // Delegado del Club: formulario de alta de jugadores si tiene club vinculado;
        // en caso de que club_id sea nulo (pendiente de vinculación PA-19), redirige a temporadas para evitar HTTP 403
        if ($this->hasRole('Delegado del Club')) {
            return $this->club_id !== null ? route('jugadores.create') : route('temporadas.index');
        }

        // Jugador: vinculación de cuenta por CURP
        if ($this->hasRole('Jugador')) {
            return route('jugadores.vincular.form');
        }

        // Roles con visualización de temporadas (Presidente, Rama Infantil, Capturista, Tribunal de Penas, Presidente del Club)
        if (
            $this->hasRole('Presidente')
            || $this->hasRole('Comision de la Rama Infantil')
            || $this->hasRole('Comisión de la Rama Infantil')
            || $this->hasRole('Capturista')
            || $this->hasRole('Tribunal de Penas')
            || $this->hasRole('Presidente del Club')
            || $this->can('temporadas.ver')
        ) {
            return route('temporadas.index');
        }

        return route('admin.dashboard');
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

        Mail::to($this)->send(new CorreoDelSistema(
            'Recupera tu contraseña del SGLD',
            "Hola, {$this->name}:\n\n"
                ."Recibimos una solicitud para restablecer la contraseña de tu cuenta en el Sistema de Gestión de Ligas Deportivas.\n\n"
                ."El enlace vence en {$minutos} minutos y solo se puede usar una vez.\n\n"
                .'Si no lo solicitaste, puedes ignorar este correo: tu contraseña no cambiará.',
            $enlace,
            'Restablecer mi contraseña',
        ));
    }
}
