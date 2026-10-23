<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index()
    {
        // Autorización delegada a la UserPolicy existente
        Gate::authorize('viewAny', User::class);

        $usuarios = User::with('roles')->where('id', '!=', Auth::id())->get();
        $roles = $this->rolesDisponibles();

        return view('admin.usuarios.index', compact('usuarios', 'roles'));
    }

    // Formulario de alta de una cuenta (CA2)
    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.usuarios.create', ['usuario' => new User(), 'roles' => $this->rolesDisponibles()]);
    }

    // Formulario de edición de datos y roles (CA5)
    public function edit(User $usuario): View
    {
        Gate::authorize('update', $usuario);

        return view('admin.usuarios.edit', ['usuario' => $usuario->load('roles'), 'roles' => $this->rolesDisponibles()]);
    }

    // Confirmación para desactivar o reactivar con motivo (CA6)
    public function estado(User $usuario): View
    {
        Gate::authorize('toggleStatus', $usuario);

        return view('admin.usuarios.estado', compact('usuario'));
    }

    public function store(StoreUsuarioRequest $request)
    {
        Gate::authorize('create', User::class);

        $tempPassword = Str::random(16);

        app(AuditService::class)->conMotivo($request->motivo, function () use ($request, $tempPassword) {
            $usuario = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($tempPassword),
                'estatus' => 'activo',
                'requires_password_change' => true,
            ]);

            $usuario->assignRole($request->roles);
        });

        // La contraseña temporal se muestra una sola vez, en el listado, mientras no haya envío por correo (HU-079)
        return redirect()->route('admin.usuarios.index')
            ->with('success', 'Cuenta creada correctamente.')
            ->with('password_temporal', ['email' => $request->email, 'password' => $tempPassword]);
    }

    public function update(UpdateUsuarioRequest $request, User $usuario)
    {
        Gate::authorize('update', $usuario);

        // Cambiar roles es una habilidad aparte: nadie puede cambiar los suyos (CA5)
        $rolesActuales = $usuario->getRoleNames()->sort()->values()->all();
        $rolesNuevos = collect($request->roles)->sort()->values()->all();

        if ($rolesActuales !== $rolesNuevos) {
            Gate::authorize('asignarRoles', $usuario);
        }

        app(AuditService::class)->conMotivo($request->motivo, function () use ($request, $usuario) {
            $usuario->update([
                'name' => $request->name,
                'email' => $request->email,
            ]);

            $usuario->syncRoles($request->roles);
        });

        return redirect()->route('admin.usuarios.index')->with('success', 'Cuenta actualizada correctamente.');
    }

    public function toggleStatus(Request $request, User $usuario)
    {
        $request->validate([
            'motivo' => 'required|string|max:255'
        ]);

        Gate::authorize('toggleStatus', $usuario);

        app(AuditService::class)->conMotivo($request->motivo, function () use ($usuario) {
            $nuevoEstatus = $usuario->estatus === 'activo' ? 'inactivo' : 'activo';
            $usuario->update(['estatus' => $nuevoEstatus]);
        });

        $mensaje = $usuario->estatus === 'activo' ? 'Cuenta reactivada.' : 'Cuenta desactivada.';
        return redirect()->route('admin.usuarios.index')->with('success', $mensaje);
    }

    /**
     * Roles que se ofrecen en los formularios de alta y edición.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Role>
     */
    private function rolesDisponibles()
    {
        return Role::query()->orderBy('name')->get();
    }
}
