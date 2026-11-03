<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Concerns\Auditable;
use App\Mail\CorreoDelSistema;
use Illuminate\Support\Facades\Mail;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use Notifiable;
    use HasRoles;
    use TwoFactorAuthenticatable;
    use Auditable;

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
        'two_factor_recovery_codes'
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

    /**
     * Clubes de los que es propietario legal (SCRUM-25).
     */
    public function clubesPropietario(): HasMany
    {
        return $this->hasMany(Club::class, 'propietario_usuario_id');
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