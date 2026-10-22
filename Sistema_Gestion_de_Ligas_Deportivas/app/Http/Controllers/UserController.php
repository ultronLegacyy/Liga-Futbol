<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    protected $auditService;

    // Inyectamos el AuditService (creado en el Sprint 1)
    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Verifica que el usuario autenticado sea Administrador General.
     * Criterio 8: Retornar 403 si no lo es.
     */
    private function authorizeAdmin()
    {
        if (!Auth::user()->hasRole('Administrador General')) {
            abort(403, 'No tienes permiso para administrar las cuentas de usuario.');
        }
    }

    public function index()
    {
        $this->authorizeAdmin();
        // Excluimos la cuenta actual y listamos
        $usuarios = User::with('roles')->where('id', '!=', Auth::id())->get();
        $roles = Role::where('name', '!=', 'Público General')->get();

        return view('admin.usuarios.index', compact('usuarios', 'roles'));
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:usuarios',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name'
        ]);

        // Criterio 2: Contraseña temporal que cumple RNF-26 (ej. 16 caracteres aleatorios)
        $tempPassword = Str::random(16);

        DB::transaction(function () use ($request, $tempPassword) {
            $usuario = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($tempPassword),
                'password_hash' => Hash::make($tempPassword),
                'is_active' => true,
                'requires_password_change' => true, // Criterio 3: obligar cambio
            ]);

            $usuario->assignRole($request->roles);

            // Criterio 7: Registro inmutable en auditoría
            $this->auditService->log(
                Auth::id(),
                'CREACION_CUENTA',
                'usuarios',
                $usuario->id,
                null,
                $usuario->toJson(),
                request()->ip(),
                'Alta de nueva cuenta en el sistema'
            );
        });

        // En un caso real enviaríamos la contraseña temporal por correo, aquí la pasamos a la vista por sesión para depuración
        return redirect()->route('admin.usuarios.index')
                         ->with('success', 'Cuenta creada. Contraseña temporal: ' . $tempPassword);
    }

    public function update(Request $request, User $usuario)
    {
        $this->authorizeAdmin();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:usuarios,email,' . $usuario->id,
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name'
        ]);

        DB::transaction(function () use ($request, $usuario) {
            $valoresAnteriores = $usuario->toJson();

            $usuario->update([
                'name' => $request->name,
                'email' => $request->email,
            ]);

            $usuario->syncRoles($request->roles);

            $this->auditService->log(
                Auth::id(),
                'EDICION_CUENTA',
                'usuarios',
                $usuario->id,
                $valoresAnteriores,
                $usuario->toJson(),
                request()->ip(),
                'Actualización de datos y/o roles'
            );
        });

        return redirect()->route('admin.usuarios.index')->with('success', 'Cuenta actualizada correctamente.');
    }

    public function toggleStatus(Request $request, User $usuario)
    {
        $this->authorizeAdmin();

        // Criterio 7: El motivo es obligatorio al desactivar/reactivar
        $request->validate([
            'motivo' => 'required|string|max:255'
        ]);

        DB::transaction(function () use ($request, $usuario) {
            $valoresAnteriores = $usuario->toJson();
            $nuevoEstado = !$usuario->is_active;

            $usuario->update(['is_active' => $nuevoEstado]);

            $accion = $nuevoEstado ? 'REACTIVACION_CUENTA' : 'DESACTIVACION_CUENTA';

            $this->auditService->log(
                Auth::id(),
                $accion,
                'usuarios',
                $usuario->id,
                $valoresAnteriores,
                $usuario->toJson(),
                request()->ip(),
                $request->motivo
            );
        });

        $mensaje = $usuario->is_active ? 'Cuenta reactivada.' : 'Cuenta desactivada.';
        return redirect()->route('admin.usuarios.index')->with('success', $mensaje);
    }
}