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
     */
    public function rutaInicio(): string
    {
        if ($this->hasRole('Administrador General') || $this->rol === 'Administrador General') {
            return (string) config('fortify.home', '/admin/dashboard');
        }

        if ($this->hasRole('Tesorero') || $this->rol === 'Tesorero') {
            return route('cargos.index');
        }

        if ($this->hasRole('Secretario') || $this->rol === 'Secretario') {
            return route('personas.index');
        }

        if ($this->hasRole('Programador de Partidos')) {
            return route('canchas.index');
        }

        if ($this->hasRole('Delegado del Club') || $this->rol === 'Directivo Club') {
            return route('jugadores.create');
        }

        if ($this->hasRole('Jugador') || $this->rol === 'Jugador') {
            return route('jugadores.vincular.form');
        }

        if (
            $this->hasRole('Comisión Arbitral')
            || $this->hasRole('Árbitro en Campo')
            || $this->rol === 'Arbitro'
            || $this->rol === 'Comision Arbitral'
        ) {
            return route('arbitros.index');
        }

        if ($this->can('temporadas.ver')) {
            return route('temporadas.index');
        }

        return (string) config('fortify.home', '/');
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
