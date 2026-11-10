@extends('layouts.admin')

@section('title', 'Detalle del cargo - SGLD')
@section('header', 'Cuotas y cargos')

@section('content')
@php
    $campo = 'w-full rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400';
    $borde = fn (string $nombre) => $errors->has($nombre) ? 'border-red-500' : 'border-gray-600';
    $estadoMostrado = $cargo->estadoMostrado();
    $temporadaCerrada = $cargo->temporada?->estado === 'cerrada';
    // Solo con saldo, la temporada abierta y permiso; el servicio vuelve a revisarlo al registrar
    $admitePago = $cargo->centavosDeSaldo() > 0 && ! $temporadaCerrada;
@endphp

<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-3xl">
    <h2 class="text-xl font-bold text-white mb-1">
        {{ App\Models\CargoFinanciero::CONCEPTOS[$cargo->concepto] ?? $cargo->concepto }} · {{ $cargo->club->nombre }}
    </h2>
    <p class="text-sm text-gray-400 mb-6">Cargo {{ $cargo->id }}, registrado el {{ $cargo->created_at?->format('d/m/Y') }}.</p>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    {{-- Cargo ya pagado o temporada cerrada: se muestra aunque el formulario ya no aparezca --}}
    @if ($errors->has('cargo'))
        <x-alert type="error" :message="$errors->first('cargo')" />
    @endif

    @if ($temporadaCerrada)
        <x-alert type="info" message="La temporada está cerrada: este cargo solo se consulta." />
    @endif

    <dl class="grid gap-4 sm:grid-cols-2 text-sm">
        <div>
            <dt class="text-gray-400">Temporada</dt>
            <dd class="text-white">{{ $cargo->temporada?->nombre ?? 'Sin temporada' }}</dd>
        </div>
        <div>
            <dt class="text-gray-400">Club</dt>
            <dd class="text-white">{{ $cargo->club->nombre }}</dd>
        </div>
        <div>
            <dt class="text-gray-400">Equipo</dt>
            <dd class="text-white">{{ $cargo->equipo?->nombre ?? 'Sin equipo' }}</dd>
        </div>
        <div>
            <dt class="text-gray-400">Jugador</dt>
            <dd class="text-white">{{ $cargo->persona ? $cargo->persona->apellidos.', '.$cargo->persona->nombre : 'Sin jugador' }}</dd>
        </div>
        <div>
            <dt class="text-gray-400">Monto</dt>
            <dd class="text-white">${{ number_format((float) $cargo->monto, 2) }} MXN</dd>
        </div>
        <div>
            <dt class="text-gray-400">Vencimiento</dt>
            <dd class="text-white">{{ $cargo->vencimiento->format('d/m/Y') }}</dd>
        </div>
        <div>
            <dt class="text-gray-400">Pagado</dt>
            <dd class="text-white">${{ number_format((float) $cargo->montoPagado(), 2) }} MXN</dd>
        </div>
        <div>
            <dt class="text-gray-400">Saldo pendiente</dt>
            <dd class="text-white font-semibold">${{ number_format((float) $cargo->saldo(), 2) }} MXN</dd>
        </div>
        <div>
            <dt class="text-gray-400">Estado</dt>
            <dd class="{{ $estadoMostrado === 'Vencido' ? 'text-red-400' : ($estadoMostrado === 'Pagado' ? 'text-green-400' : 'text-amber-400') }} font-semibold">
                {{ App\Models\CargoFinanciero::ESTADOS[$estadoMostrado] ?? $estadoMostrado }}
            </dd>
        </div>
    </dl>

    <h3 class="text-lg font-semibold text-white mt-8 mb-3">Pagos</h3>

    @if ($cargo->pagos->isEmpty())
        <p class="text-sm text-gray-400">Este cargo todavía no tiene pagos.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-300">
                <caption class="sr-only">Pagos registrados del cargo {{ $cargo->id }}</caption>
                <thead class="text-xs uppercase text-gray-400 border-b border-gray-700">
                    <tr>
                        <th scope="col" class="py-2 pr-4">Fecha</th>
                        <th scope="col" class="py-2 pr-4">Monto</th>
                        <th scope="col" class="py-2 pr-4">Método</th>
                        <th scope="col" class="py-2 pr-4">Referencia</th>
                        <th scope="col" class="py-2 pr-4">Registró</th>
                        <th scope="col" class="py-2">Comprobante</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cargo->pagos as $pago)
                        <tr class="border-b border-gray-700/60">
                            <td class="py-2 pr-4 whitespace-nowrap">{{ $pago->created_at?->timezone('America/Mexico_City')->format('d/m/Y H:i') }}</td>
                            <td class="py-2 pr-4 whitespace-nowrap">${{ number_format((float) $pago->monto, 2) }}</td>
                            <td class="py-2 pr-4">{{ $pago->metodo_pago }}</td>
                            <td class="py-2 pr-4">{{ $pago->referencia ?: 'Sin referencia' }}</td>
                            <td class="py-2 pr-4">{{ $pago->validadoPor?->name ?? 'Sin registro' }}</td>
                            <td class="py-2">
                                @can('view', $pago)
                                    <a href="{{ route('pagos.comprobante', $pago) }}" class="text-green-400 underline hover:text-green-300 focus:outline-none focus:ring-2 focus:ring-green-400 rounded">Descargar<span class="sr-only"> el comprobante del pago {{ $pago->id }}</span></a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($admitePago)
        @can('create', App\Models\Pago::class)
            <h3 class="text-lg font-semibold text-white mt-8 mb-3">Registrar pago</h3>

            @if ($errors->any() && ! $errors->has('cargo'))
                <x-alert type="error" message="No se registró el pago. Revisa los campos marcados." />
            @endif

            <form method="POST" action="{{ route('cargos.pagos.store', $cargo) }}" enctype="multipart/form-data" novalidate class="space-y-5">
                @csrf

                <x-form.field name="monto" label="Monto del pago (MXN)" required help="Mayor que cero, con dos decimales como máximo y sin superar el saldo pendiente." :error="$errors->first('monto')">
                    <input type="number" id="monto" name="monto" required min="0.01" step="0.01" max="{{ $cargo->saldo() }}" inputmode="decimal" value="{{ old('monto') }}"
                        class="{{ $campo }} sm:max-w-xs {{ $borde('monto') }}" aria-describedby="monto-ayuda @if ($errors->has('monto')) monto-error @endif" @if ($errors->has('monto')) aria-invalid="true" @endif>
                </x-form.field>

                <x-form.field name="metodo_pago" label="Método de pago" required help="Por ejemplo: efectivo, transferencia o depósito." :error="$errors->first('metodo_pago')">
                    <input type="text" id="metodo_pago" name="metodo_pago" required maxlength="50" value="{{ old('metodo_pago') }}"
                        class="{{ $campo }} {{ $borde('metodo_pago') }}" aria-describedby="metodo_pago-ayuda @if ($errors->has('metodo_pago')) metodo_pago-error @endif" @if ($errors->has('metodo_pago')) aria-invalid="true" @endif>
                </x-form.field>

                <x-form.field name="referencia" label="Referencia" help="Opcional: folio de la transferencia o del recibo." :error="$errors->first('referencia')">
                    <input type="text" id="referencia" name="referencia" maxlength="100" value="{{ old('referencia') }}"
                        class="{{ $campo }} {{ $borde('referencia') }}" aria-describedby="referencia-ayuda @if ($errors->has('referencia')) referencia-error @endif" @if ($errors->has('referencia')) aria-invalid="true" @endif>
                </x-form.field>

                <x-form.field name="comprobante" label="Comprobante" required help="PDF, JPG o PNG de hasta 5 MB." :error="$errors->first('comprobante')">
                    <input type="file" id="comprobante" name="comprobante" required accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                        class="{{ $campo }} {{ $borde('comprobante') }}" aria-describedby="comprobante-ayuda @if ($errors->has('comprobante')) comprobante-error @endif" @if ($errors->has('comprobante')) aria-invalid="true" @endif>
                </x-form.field>

                <p class="text-xs text-gray-400"><span class="text-red-400" aria-hidden="true">*</span> Campo obligatorio.</p>

                <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">Registrar pago</button>
            </form>
        @endcan
    @endif

    <p class="mt-6">
        <a href="{{ route('cargos.index') }}" class="px-4 py-2 text-sm text-gray-300 rounded-lg border border-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400">Volver al listado</a>
    </p>
</div>
@endsection
