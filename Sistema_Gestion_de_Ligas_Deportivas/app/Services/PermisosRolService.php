<?php

namespace App\Services;

use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Consulta en solo lectura de los permisos de cada rol (HU-081, RF-12.1, IU-02).
// Todo se lee del motor de permisos (spatie/laravel-permission, HU-001): no hay permisos escritos en el código
class PermisosRolService
{
    // Nombre visible de cada módulo (prefijo del permiso «modulo.accion»); si falta uno se usa el prefijo
    private const MODULOS = [
        'gobernanza' => 'Gobernanza',
        'ligas' => 'Ligas',
        'temporadas' => 'Temporadas',
        'convocatorias' => 'Convocatorias',
        'reglas_temporada' => 'Reglas de temporada',
        'categorias' => 'Categorías y grupos',
        'clubes' => 'Clubes',
        'tarjetones' => 'Tarjetón digital',
        'equipos' => 'Equipos',
        'jugadores' => 'Jugadores',
        'personas' => 'Personas',
        'campos' => 'Canchas',
        'usuarios' => 'Cuentas de usuario',
        'roles' => 'Roles y permisos',
        'auditoria' => 'Bitácora de auditoría',
        'arbitros' => 'Árbitros',
        'partidos' => 'Partidos',
        'cedulas' => 'Cédulas',
        'resultados' => 'Resultados',
        'disciplina' => 'Disciplina',
        'finanzas' => 'Finanzas',
    ];

    // Nombre visible de las acciones que no se leen bien solo cambiando «_» por espacio
    private const ACCIONES = [
        'pagos_validar' => 'Validar pagos',
        'asignar_permiso' => 'Asignar permisos',
    ];

    /**
     * CA1: los 13 roles con cuenta de la sección 2.3, en el orden del catálogo (sin «Público General», que no tiene cuenta).
     *
     * @return Collection<int, Role>
     */
    public function roles(): Collection
    {
        $orden = array_flip(RoleAndPermissionSeeder::ROLES);

        return Role::query()
            ->whereIn('name', RoleAndPermissionSeeder::ROLES)
            ->withCount('permissions')
            ->get()
            ->sortBy(fn (Role $rol): int => $orden[$rol->name])
            ->values();
    }

    /**
     * Rol del catálogo; cualquier otro (o un id inexistente) responde 404.
     */
    public function rol(int $id): Role
    {
        return Role::query()
            ->whereIn('name', RoleAndPermissionSeeder::ROLES)
            ->findOrFail($id);
    }

    /**
     * CA2: módulos del motor de permisos con sus acciones, marcando cuáles tiene el rol.
     *
     * @return list<array{clave: string, nombre: string, acciones: list<array{permiso: string, nombre: string, permitida: bool}>, permitidas: int}>
     */
    public function modulos(Role $rol): array
    {
        $delRol = $rol->permissions()->pluck('name')->flip();
        $modulos = [];

        foreach (Permission::query()->where('guard_name', $rol->guard_name)->orderBy('id')->pluck('name') as $permiso) {
            [$clave, $accion] = array_pad(explode('.', $permiso, 2), 2, '');

            $modulos[$clave] ??= [
                'clave' => $clave,
                'nombre' => self::MODULOS[$clave] ?? Str::ucfirst(str_replace('_', ' ', $clave)),
                'acciones' => [],
                'permitidas' => 0,
            ];

            $permitida = $delRol->has($permiso);
            $modulos[$clave]['acciones'][] = [
                'permiso' => $permiso,
                'nombre' => self::ACCIONES[$accion] ?? Str::ucfirst(str_replace('_', ' ', $accion)),
                'permitida' => $permitida,
            ];
            $modulos[$clave]['permitidas'] += (int) $permitida;
        }

        return array_values($modulos);
    }
}
