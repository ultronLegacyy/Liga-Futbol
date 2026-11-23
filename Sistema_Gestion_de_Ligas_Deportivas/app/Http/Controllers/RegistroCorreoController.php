<?php

namespace App\Http\Controllers;

use App\Models\RegistroCorreo;
use Illuminate\Http\Request;
use Illuminate\View\View;

// HU-067 (RF-15.4): consulta del registro de correos; la ruta exige correos.ver (solo el Administrador General)
class RegistroCorreoController extends Controller
{
    public function index(Request $request): View
    {
        $estado = $request->query('estado');
        $estado = is_string($estado) && in_array($estado, RegistroCorreo::ESTADOS, true) ? $estado : '';

        $correos = RegistroCorreo::query()
            ->when($estado !== '', fn ($consulta) => $consulta->where('estado', $estado))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('correos.registro', ['correos' => $correos, 'estado' => $estado]);
    }
}
