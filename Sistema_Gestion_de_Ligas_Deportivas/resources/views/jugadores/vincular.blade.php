@extends('layouts.admin')

@section('title', 'Vincular mi cuenta')
@section('header', 'Vinculación de jugador')

@section('content')
<div class="mx-auto max-w-xl">
    <div class="rounded-xl border border-gray-700 bg-gray-800 p-8 shadow-xl">
        <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">
            HU-016 · Vinculación de cuenta
        </p>

        <h1 class="mt-3 text-2xl font-bold text-white">
            Vincular mi cuenta
        </h1>

        <p class="mt-3 text-sm leading-6 text-gray-300">
            Introduce tu CURP para solicitar la vinculación con tu registro de jugador.
        </p>

        <form id="formulario-vinculacion"
              action="{{ route('jugadores.vincular') }}"
              method="POST"
              class="mt-7 space-y-5">
            @csrf

            <div>
                <label for="curp" class="mb-2 block text-sm font-medium text-gray-200">
                    CURP
                </label>

                <input
                    id="curp"
                    name="curp"
                    type="text"
                    required
                    minlength="18"
                    maxlength="18"
                    pattern="[A-Za-z]{4}[0-9]{6}[HMXhmx][A-Za-z]{5}[A-Za-z0-9][0-9]"
                    autocomplete="off"
                    spellcheck="false"
                    placeholder="Ingresa los 18 caracteres"
                    class="w-full rounded-lg border border-gray-600 bg-gray-900 px-4 py-3 uppercase text-white focus:border-emerald-500 focus:outline-none"
                >
            </div>

            <p id="mensaje-vinculacion"
               role="status"
               aria-live="polite"
               class="hidden rounded-lg p-4 text-sm"></p>

            <button
                id="boton-vincular"
                type="submit"
                class="w-full rounded-lg bg-emerald-600 px-5 py-3 font-semibold text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50">
                Solicitar vinculación
            </button>
        </form>
    </div>
</div>

<script>
(() => {
    const formulario = document.getElementById('formulario-vinculacion');
    const boton = document.getElementById('boton-vincular');
    const mensaje = document.getElementById('mensaje-vinculacion');
    const curp = document.getElementById('curp');

    const mostrarMensaje = (texto, error = false) => {
        mensaje.textContent = texto;
        mensaje.className = error
            ? 'rounded-lg border border-red-700 bg-red-950 p-4 text-sm text-red-200'
            : 'rounded-lg border border-emerald-700 bg-emerald-950 p-4 text-sm text-emerald-200';
    };

    formulario.addEventListener('submit', async (evento) => {
        evento.preventDefault();

        boton.disabled = true;
        mensaje.classList.add('hidden');

        try {
            const respuesta = await fetch(formulario.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': formulario.querySelector('[name="_token"]').value
                },
                body: JSON.stringify({
                    curp: curp.value.trim().toUpperCase()
                })
            });

            if (respuesta.ok) {
                mostrarMensaje(
                    'Si los datos corresponden a un registro elegible, la cuenta quedará vinculada.'
                );
                formulario.reset();
            } else if (respuesta.status === 429) {
                mostrarMensaje(
                    'Has realizado demasiados intentos. Espera antes de volver a intentarlo.',
                    true
                );
            } else if (respuesta.status === 422) {
                mostrarMensaje(
                    'Verifica que la CURP tenga el formato oficial de 18 caracteres.',
                    true
                );
            } else {
                mostrarMensaje(
                    'No fue posible procesar la solicitud. Intenta nuevamente más tarde.',
                    true
                );
            }
        } catch {
            mostrarMensaje(
                'No fue posible conectar con el servidor. Intenta nuevamente.',
                true
            );
        } finally {
            boton.disabled = false;
        }
    });
})();
</script>
@endsection