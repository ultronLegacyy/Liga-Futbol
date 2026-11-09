<?php

namespace App\Http\Controllers;

use App\Models\CargoFinanciero;
use App\Models\Pago;
use App\Services\RegistroPagoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Registrar pagos desde el detalle del cargo y descargar sus comprobantes (HU-051, RF-13.2)
class PagoController extends Controller
{
    // Tipos que acepta el registro del pago (mimes:pdf,jpg,jpeg,png)
    private const TIPOS_COMPROBANTE = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    public function __construct(private readonly RegistroPagoService $registro)
    {
    }

    /**
     * Registrar un pago parcial o total con su comprobante (criterios 1 y 2).
     */
    public function store(Request $request, CargoFinanciero $cargo): RedirectResponse
    {
        // El servicio no revisa permisos: se autoriza antes de llamarlo
        Gate::authorize('create', Pago::class);

        $archivo = $request->file('comprobante');

        $pago = $this->registro->registrar(
            $cargo,
            $request->only(['monto', 'metodo_pago', 'referencia']),
            $archivo instanceof UploadedFile ? $archivo : null,
            $request->user(),
        );

        // La instancia de la ruta tiene el saldo de antes del pago
        $cargo->refresh();

        $mensaje = 'Pago de $'.number_format((float) $pago->monto, 2).' registrado. '
            .($cargo->estado === 'Pagado'
                ? 'El cargo quedó pagado.'
                : 'Saldo pendiente: $'.number_format((float) $cargo->saldo(), 2).'.');

        return redirect()->route('cargos.show', $cargo)->with('success', $mensaje);
    }

    /**
     * Descargar el comprobante desde el disco privado; nunca desde public.
     */
    public function comprobante(Pago $pago): StreamedResponse
    {
        Gate::authorize('view', $pago);

        $disco = Storage::disk('local');
        $ruta = (string) $pago->recibo_url;
        $extension = Str::lower(pathinfo($ruta, PATHINFO_EXTENSION));

        // Solo los archivos que guardó el registro del pago, en comprobantes/
        if (! Str::startsWith($ruta, 'comprobantes/') || ! isset(self::TIPOS_COMPROBANTE[$extension]) || ! $disco->exists($ruta)) {
            abort(404, 'El pago no cuenta con un comprobante disponible.');
        }

        return $disco->download(
            $ruta,
            'comprobante-cargo-'.$pago->cargo_id.'-pago-'.$pago->id.'.'.$extension,
            [
                'Content-Type' => self::TIPOS_COMPROBANTE[$extension],
                'X-Content-Type-Options' => 'nosniff',
                'X-Robots-Tag' => 'noindex, nofollow',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }
}
