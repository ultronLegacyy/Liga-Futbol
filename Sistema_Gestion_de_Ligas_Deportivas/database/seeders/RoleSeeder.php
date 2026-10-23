<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = [
            'Administrador General',
            'Presidente',
            'Secretario',
            'Tesorero',
            'Comision de la Rama Infantil',
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
