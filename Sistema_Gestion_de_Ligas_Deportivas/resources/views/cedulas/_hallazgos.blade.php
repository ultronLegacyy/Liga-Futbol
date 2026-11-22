{{-- HU-037: aviso de un formulario reenviado y lo que ValidacionCedulaService encontró al transcribir la cédula en papel --}}
@if (session('warning'))
    <x-alert type="warning" :message="session('warning')" />
@endif
@if (is_array(session('hallazgos')) && session('hallazgos') !== [])
    <div role="status" class="mb-4 rounded-lg border border-amber-700 bg-amber-950 p-4 text-sm text-amber-100">
        <p class="font-semibold">Revisa antes de cerrar la cédula:</p>
        <ul class="mt-2 list-disc pl-5">
            @foreach (session('hallazgos') as $hallazgo)
                <li>{{ $hallazgo['mensaje'] }}</li>
            @endforeach
        </ul>
    </div>
@endif
