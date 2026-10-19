<?php

namespace App\Providers;

use App\Models\Club;
use App\Models\Equipo;
use App\Models\Temporada;
use App\Models\User;
use App\Policies\ClubPolicy;
use App\Policies\EquipoPolicy;
use App\Policies\TemporadaPolicy;
use App\Policies\UserPolicy;
use App\Services\AuditService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Una instancia por petición: el motivo de la auditoría no se comparte entre peticiones
        $this->app->scoped(AuditService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registro explicito de politicas de autorizacion para el dominio del sistema
        Gate::policy(Temporada::class, TemporadaPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Club::class, ClubPolicy::class);
        Gate::policy(Equipo::class, EquipoPolicy::class);
    }
}
