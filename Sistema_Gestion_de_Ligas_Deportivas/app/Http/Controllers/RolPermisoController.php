<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarPermisosRolRequest;
use App\Services\PermisosRolService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

// Permisos de cada rol: consulta (HU-081) y edición (HU-083), RF-12.1. Las rutas están en el grupo del
// Administrador General, así que cualquier otro usuario recibe 403 (HU-081 CA4 y HU-083 CA3, RNF-29)
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
     * HU-083 CA1: formulario para agregar o quitar permisos de módulo y acción, con motivo obligatorio.
     */
    public function edit(int $rol): View
    {
        $rol = $this->permisos->rol($rol);

        return view('admin.roles.edit', [
            'rol' => $rol,
            'modulos' => $this->permisos->modulos($rol),
            'protegidos' => $rol->name === 'Administrador General' ? PermisosRolService::MODULOS_PROTEGIDOS_ADMIN : [],
        ]);
    }

    /**
     * HU-083 CA1 y CA2: guarda los permisos del rol y su registro inmutable en una sola transacción.
     */
    public function update(ActualizarPermisosRolRequest $request, int $rol): RedirectResponse
    {
        $rol = $this->permisos->rol($rol);
        $cambios = $this->permisos->actualizar($rol, $request->permisos(), (string) $request->validated('motivo'));

        return redirect()
            ->route('admin.roles.show', $rol->id)
            ->with('success', sprintf(
                'Se guardaron los permisos de %s: %d agregado(s) y %d quitado(s). El cambio ya aplica a sus cuentas.',
                $rol->name,
                count($cambios['agregados']),
                count($cambios['quitados']),
            ));
    }

    /**
     * El catálogo de roles es fijo (HU-080): no se crean, renombran ni eliminan roles, aun por petición directa.
     * Los permisos de un rol solo se cambian con el formulario de edición (HU-083).
     */
    public function rechazarCambio(): never
    {
        abort(403, 'Los roles del catálogo son fijos: no se pueden crear, renombrar ni eliminar. Los permisos se cambian desde «Editar permisos».');
    }
}
