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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
