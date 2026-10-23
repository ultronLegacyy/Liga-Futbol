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

class UserController extends Controller
{
    public function index()
    {
        // Autorización delegada a la UserPolicy existente
        Gate::authorize('viewAny', User::class);

        $usuarios = User::with('roles')->where('id', '!=', Auth::id())->get();
        $roles = Role::all();

        return view('admin.usuarios.index', compact('usuarios', 'roles'));
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

        return redirect()->route('admin.usuarios.index')
                         ->with('success', 'Cuenta creada. Contraseña temporal: ' . $tempPassword);
    }

    public function update(UpdateUsuarioRequest $request, User $usuario)
    {
        Gate::authorize('update', $usuario);

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
}