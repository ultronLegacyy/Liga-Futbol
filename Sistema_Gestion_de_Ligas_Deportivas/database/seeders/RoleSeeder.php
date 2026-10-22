<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiar caché de Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = [
            'Administrador General',
            'Presidente del Consejo Directivo',
            'Secretario del Consejo Directivo',
            'Tesorero del Consejo Directivo',
            'Comisión de la Rama Infantil',
            'Programador de Partidos',
            'Capturista',
            'Tribunal de Penas',
            'Comisión Arbitral',
            'Árbitro en Campo',
            'Presidente del Club',
            'Delegado del Club',
            'Jugador'
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}