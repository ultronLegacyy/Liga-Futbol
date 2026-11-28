<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\MenuPanel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

// HAB-S7 (S7-T02): pantalla de inicio del panel; cada rol ve solo las tarjetas de sus módulos (IU-02, RNF-29)
class PanelController extends Controller
{
    public function inicio(Request $request): View
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);

        $hora = (int) Carbon::now('America/Mexico_City')->format('G');

        return view('panel.inicio', [
            'usuario' => $usuario,
            'rol' => $usuario->getRoleNames()->first() ?? 'Sin rol',
            'areas' => MenuPanel::para($usuario),
            'saludo' => $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches'),
        ]);
    }
}
