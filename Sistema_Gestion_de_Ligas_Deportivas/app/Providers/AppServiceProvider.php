<?php

namespace App\Providers;

use App\Models\Arbitro;
use App\Models\BloqueoCancha;
use App\Models\Cancha;
use App\Models\CargoFinanciero;
use App\Models\Categoria;
use App\Models\Club;
use App\Models\DirectivoClub;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Persona;
use App\Models\Temporada;
use App\Models\TarjetonDigital;
use App\Models\User;
use App\Policies\ArbitroPolicy;
use App\Policies\BloqueoCanchaPolicy;
use App\Policies\CanchaPolicy;
use App\Policies\CargoFinancieroPolicy;
use App\Policies\CategoriaPolicy;
use App\Policies\ClubPolicy;
use App\Policies\EquipoPolicy;
use App\Policies\GrupoPolicy;
use App\Policies\PersonaPolicy;
use App\Policies\TemporadaPolicy;
use App\Policies\TarjetonDigitalPolicy;
use App\Policies\UserPolicy;
use App\Services\AuditService;
use App\Observers\ClubObserver;
use App\Observers\DirectivoClubObserver;
use App\Observers\EquipoObserver;
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
        Gate::policy(Cancha::class, CanchaPolicy::class);
        Gate::policy(BloqueoCancha::class, BloqueoCanchaPolicy::class);
        Gate::policy(Categoria::class, CategoriaPolicy::class);
        Gate::policy(Grupo::class, GrupoPolicy::class);
        Gate::policy(Persona::class, PersonaPolicy::class);
        Gate::policy(Arbitro::class, ArbitroPolicy::class);
        Gate::policy(TarjetonDigital::class, TarjetonDigitalPolicy::class);
        Gate::policy(CargoFinanciero::class, CargoFinancieroPolicy::class);

        Club::observe(ClubObserver::class);
        DirectivoClub::observe(DirectivoClubObserver::class);
        Equipo::observe(EquipoObserver::class);
    }
}
