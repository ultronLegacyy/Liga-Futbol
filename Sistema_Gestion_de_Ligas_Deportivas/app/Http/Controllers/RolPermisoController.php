<?php

namespace App\Http\Controllers;

use App\Services\PermisosRolService;
use Illuminate\View\View;

// Permisos de cada rol en solo lectura (HU-081, RF-12.1). Las rutas están en el grupo del Administrador General,
// así que cualquier otro usuario recibe 403 (CA4, RNF-29). La edición llegará con HU-083
class RolPermisoController extends Controller
{
    public function __construct(
        private readonly PermisosRolService $permisos
    ) {
    }

    /**
     * Listado de los 13 roles con cuenta (CA1).
     */
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => $this->permisos->roles(),
        ]);
    }

    /**
     * Módulos y acciones de un rol, solo para consultar (CA2).
     */
    public function show(int $rol): View
    {
        $rol = $this->permisos->rol($rol);

        return view('admin.roles.show', [
            'rol' => $rol,
            'modulos' => $this->permisos->modulos($rol),
        ]);
    }

    /**
     * CA3: en esta versión los permisos son fijos; cualquier intento de cambio, aun por petición directa, se rechaza.
     */
    public function rechazarCambio(): never
    {
        abort(403, 'Los permisos de los roles son fijos en esta versión: solo se pueden consultar.');
    }
}
