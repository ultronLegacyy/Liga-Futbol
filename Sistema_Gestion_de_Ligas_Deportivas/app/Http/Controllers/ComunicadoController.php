<?php

namespace App\Http\Controllers;

use App\Http\Requests\ComunicadoRequest;
use App\Models\Categoria;
use App\Models\Club;
use App\Services\ComunicadoService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

// Comunicados por correo a grupos de usuarios (HU-066, RF-15.3); la ruta exige comunicados.enviar
class ComunicadoController extends Controller
{
    public function __construct(private readonly ComunicadoService $comunicados)
    {
    }

    public function create(): View
    {
        return $this->formulario();
    }

    // Antes de enviar: cuántas personas tiene el grupo, y el botón para confirmar
    public function revisar(ComunicadoRequest $request): View
    {
        return $this->formulario($this->comunicados->contar($request->filtros()), $request);
    }

    public function store(ComunicadoRequest $request): RedirectResponse
    {
        $comunicado = $this->comunicados->enviar(trim((string) $request->validated('asunto')), trim((string) $request->validated('mensaje')), $request->filtros(), (string) $request->validated('token'), $request->user());

        return redirect()->route('comunicados.create')
            ->with('status', "Comunicado enviado: {$comunicado->total_destinatarios} ".($comunicado->total_destinatarios === 1 ? 'destinatario' : 'destinatarios').'. Los correos salen por la cola en los próximos minutos.');
    }

    private function formulario(?int $total = null, ?ComunicadoRequest $request = null): View
    {
        return view('comunicados.create', [
            'roles' => RoleAndPermissionSeeder::ROLES,
            'categorias' => Categoria::query()->with('temporada:id,nombre')->orderByDesc('temporada_id')->orderBy('nombre')->get(['id', 'nombre', 'temporada_id']),
            'clubes' => Club::query()->orderBy('nombre')->get(['id', 'nombre']),
            'total' => $total,
            'datos' => $request ? ['asunto' => (string) $request->validated('asunto'), 'mensaje' => (string) $request->validated('mensaje')] + $request->filtros() : null,
            // Cada revisión da un token nuevo; el envío lo consume una sola vez (ver ComunicadoService::enviar)
            'token' => $total !== null ? (string) Str::uuid() : null,
        ]);
    }
}
