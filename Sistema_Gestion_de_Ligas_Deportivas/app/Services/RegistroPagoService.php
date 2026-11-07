<?php

namespace App\Services;

use App\Models\CargoFinanciero;
use App\Models\Pago;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

// Pagos parciales o totales de un cargo, con su comprobante (HU-051, RF-13.2). La autorización le toca a quien lo llame
class RegistroPagoService
{
    private const MENSAJES = [
        'monto.required' => 'El monto del pago es obligatorio.',
        'monto.numeric' => 'El monto del pago debe ser un número.',
        'monto.gt' => 'El monto del pago debe ser mayor que cero.',
        'monto.decimal' => 'El monto del pago debe ser un número con dos decimales como máximo, por ejemplo 150.50.',
        'monto.max' => 'El monto del pago no puede ser mayor que 99,999,999.99.',
        'metodo_pago.required' => 'El método de pago es obligatorio.',
        'metodo_pago.string' => 'El método de pago no es válido.',
        'metodo_pago.max' => 'El método de pago no debe tener más de 50 caracteres.',
        'referencia.string' => 'La referencia no es válida.',
        'referencia.max' => 'La referencia no debe tener más de 100 caracteres.',
        'comprobante.required' => 'El comprobante de pago es obligatorio.',
        'comprobante.file' => 'El comprobante de pago debe ser un archivo.',
        'comprobante.mimes' => 'El comprobante debe ser un PDF, JPG o PNG.',
        'comprobante.max' => 'El comprobante no debe pesar más de 5 MB.',
    ];

    /**
     * Registra un pago con su comprobante y actualiza el estado del adeudo (criterios 1 y 2).
     * Un pago parcial deja el cargo en Pendiente; cuando el saldo llega a 0 pasa a Pagado.
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws ValidationException
     */
    public function registrar(CargoFinanciero $cargo, array $datos, ?UploadedFile $comprobante, User $tesorero): Pago
    {
        $validos = Validator::make(array_merge($datos, ['comprobante' => $comprobante]), [
            'monto' => ['bail', 'required', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            'metodo_pago' => ['bail', 'required', 'string', 'max:50'],
            'referencia' => ['bail', 'nullable', 'string', 'max:100'],
            'comprobante' => ['bail', 'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], self::MENSAJES)->validate();

        $archivo = $validos['comprobante'];

        // La validación ya lo exige; esta comprobación garantiza el tipo del archivo
        if (! $archivo instanceof UploadedFile) {
            throw ValidationException::withMessages(['comprobante' => self::MENSAJES['comprobante.required']]);
        }

        $centavos = CargoFinanciero::aCentavos($validos['monto']);
        $ruta = null;

        try {
            return DB::transaction(function () use ($cargo, $validos, $archivo, $centavos, $tesorero, &$ruta): Pago {
                // El bloqueo evita que dos pagos simultáneos excedan el saldo; con READ COMMITTED se relee lo último confirmado
                $bloqueado = CargoFinanciero::query()->lockForUpdate()->findOrFail($cargo->getKey());

                if ($bloqueado->temporada()->value('estado') === 'cerrada') {
                    throw ValidationException::withMessages([
                        'cargo' => 'La temporada del cargo está cerrada: solo se consulta (RF-01.4).',
                    ]);
                }

                if ($bloqueado->estado === 'Pagado') {
                    throw ValidationException::withMessages([
                        'cargo' => 'El cargo ya está pagado.',
                    ]);
                }

                $saldo = $bloqueado->centavosDeSaldo();

                if ($centavos > $saldo) {
                    throw ValidationException::withMessages([
                        'monto' => 'El pago supera el saldo pendiente de $'.number_format($saldo / 100, 2).'.',
                    ]);
                }

                $ruta = $archivo->store('comprobantes', 'local');

                if ($ruta === false) {
                    throw new RuntimeException('No fue posible guardar el comprobante de pago.');
                }

                $pago = $bloqueado->pagos()->create([
                    'monto' => CargoFinanciero::formatoCentavos($centavos),
                    'metodo_pago' => $validos['metodo_pago'],
                    'referencia' => $validos['referencia'] ?? null,
                    'recibo_url' => $ruta,
                    'validado_por_tesorero_id' => $tesorero->id,
                ]);

                if ($centavos === $saldo) {
                    $bloqueado->update(['estado' => 'Pagado']);
                }

                return $pago;
            });
        } catch (Throwable $excepcion) {
            // Si algo falla después de guardar el comprobante, no se deja el archivo suelto
            if (is_string($ruta)) {
                Storage::disk('local')->delete($ruta);
            }

            throw $excepcion;
        }
    }
}
