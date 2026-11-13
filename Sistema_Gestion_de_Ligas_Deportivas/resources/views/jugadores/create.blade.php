@extends('layouts.admin')

@section('title', 'Registro de jugador')

@section('content')
<main class="mx-auto max-w-5xl p-6" x-data>
    <div class="mb-8">
        <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">HU-016 · Alta de jugador</p>
        <h1 class="mt-2 text-3xl font-bold text-white">Registrar jugador</h1>
        <p class="mt-2 text-gray-300">Captura sus datos, asigna una categoría y temporada activa, y adjunta la documentación de forma segura.</p>
    </div>

    @if (session('success'))
        <div role="status" class="mb-6 rounded-lg border border-emerald-700 bg-emerald-950 p-4 text-emerald-200">{{ session('success') }}</div>
    @endif

    @if ($equipos->isEmpty())
        <div role="status" class="rounded-lg border border-amber-700 bg-amber-950 p-5 text-amber-100">
            No hay equipos asignados a tu club en una temporada activa.
        </div>
    @else
        <form method="POST" action="{{ route('jugadores.store') }}" enctype="multipart/form-data" class="space-y-8 rounded-xl border border-gray-700 bg-gray-800 p-6 shadow-xl">
            @csrf

            <section aria-labelledby="datos-jugador-titulo">
                <h2 id="datos-jugador-titulo" class="mb-4 text-xl font-semibold text-white">Datos del jugador</h2>
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label for="nombre" class="mb-1 block text-sm font-medium text-gray-200">Nombre(s)</label>
                        <input id="nombre" name="nombre" value="{{ old('nombre') }}" required maxlength="120" autocomplete="given-name" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none">
                        @error('nombre') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="apellidos" class="mb-1 block text-sm font-medium text-gray-200">Apellidos</label>
                        <input id="apellidos" name="apellidos" value="{{ old('apellidos') }}" required maxlength="160" autocomplete="family-name" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none">
                        @error('apellidos') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="fecha_nacimiento" class="mb-1 block text-sm font-medium text-gray-200">Fecha de nacimiento</label>
                        <input id="fecha_nacimiento" name="fecha_nacimiento" type="date" value="{{ old('fecha_nacimiento') }}" required max="{{ $edadMinima === null ? now(App\Services\GestionJugadorService::ZONA_HORARIA)->toDateString() : now(App\Services\GestionJugadorService::ZONA_HORARIA)->subYears($edadMinima)->toDateString() }}" autocomplete="bday" @if ($edadMinima !== null) aria-describedby="fecha-nacimiento-ayuda" @endif class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none">
                        @if ($edadMinima !== null)
                            <p id="fecha-nacimiento-ayuda" class="mt-1 text-xs text-gray-400">La Liga solo inscribe jugadores de {{ $edadMinima }} años cumplidos o más.</p>
                        @endif
                        @error('fecha_nacimiento') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="curp" class="mb-1 block text-sm font-medium text-gray-200">CURP</label>
                        <input id="curp" name="curp" value="{{ old('curp') }}" required minlength="18" maxlength="18" autocomplete="off" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 uppercase text-white focus:border-emerald-500 focus:outline-none">
                        @error('curp') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section aria-labelledby="inscripcion-titulo">
                <h2 id="inscripcion-titulo" class="mb-4 text-xl font-semibold text-white">Equipo y categoría</h2>
                <div class="grid gap-5 md:grid-cols-3">
                    <div>
                        <label for="temporada_id" class="mb-1 block text-sm font-medium text-gray-200">Temporada activa</label>
                        <select id="temporada_id" name="temporada_id" required class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-white">
                            <option value="">Selecciona temporada</option>
                            @foreach ($temporadas as $temporada)
                                <option value="{{ $temporada->id }}" @selected(old('temporada_id') == $temporada->id)>{{ $temporada->nombre }}</option>
                            @endforeach
                        </select>
                        @error('temporada_id') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="categoria_id" class="mb-1 block text-sm font-medium text-gray-200">Categoría</label>
                        <select id="categoria_id" name="categoria_id" required disabled class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-white disabled:opacity-50">
                            <option value="">Selecciona categoría</option>
                            @foreach ($categorias as $categoria)
                                <option value="{{ $categoria->id }}" data-temporada="{{ $categoria->temporada_id }}" data-requiere-tutor="{{ in_array($categoria->id, $categoriasConTutor, true) ? '1' : '0' }}" @selected(old('categoria_id') == $categoria->id)>{{ $categoria->nombre }}</option>
                            @endforeach
                        </select>
                        @error('categoria_id') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="equipo_id" class="mb-1 block text-sm font-medium text-gray-200">Equipo</label>
                        <select id="equipo_id" name="equipo_id" required disabled class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-white disabled:opacity-50">
                            <option value="">Selecciona equipo</option>
                            @foreach ($equipos as $equipo)
                                <option value="{{ $equipo->id }}" data-temporada="{{ $equipo->temporada_id }}" data-categoria="{{ $equipo->grupo->categoria_id }}" @selected(old('equipo_id') == $equipo->id)>{{ $equipo->nombre }} · {{ $equipo->club->nombre }}</option>
                            @endforeach
                        </select>
                        @error('equipo_id') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="dorsal" class="mb-1 block text-sm font-medium text-gray-200">Dorsal (opcional)</label>
                        <input id="dorsal" name="dorsal" type="number" min="1" max="99" value="{{ old('dorsal') }}" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-white">
                        @error('dorsal') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section aria-labelledby="documentos-titulo">
                <h2 id="documentos-titulo" class="mb-2 text-xl font-semibold text-white">Documentos confidenciales</h2>
                <p class="mb-4 text-sm text-gray-300">Los archivos se guardarán en almacenamiento privado y solo se procesarán desde el sistema.</p>
                @error('documentos') <p role="alert" class="mb-4 rounded border border-red-700 bg-red-950/40 p-3 text-sm text-red-200">{{ $message }}</p> @enderror
                <div class="grid gap-5 md:grid-cols-2">
                    @foreach ([
                        'ine' => ['Identificación oficial (INE) · adjunta INE o CURP', '.jpg,.jpeg,.png,.pdf'],
                        'curp' => ['Documento CURP · adjunta INE o CURP', '.jpg,.jpeg,.png,.pdf'],
                        'comprobante' => ['Comprobante de domicilio', '.jpg,.jpeg,.png,.pdf'],
                    ] as $campo => [$etiqueta, $formatos])
                        <div>
                            <label for="documento-{{ $campo }}" class="mb-1 block text-sm font-medium text-gray-200">{{ $etiqueta }}</label>
                            <input id="documento-{{ $campo }}" name="documentos[{{ $campo }}]" type="file" accept="{{ $formatos }}" @if ($campo === 'comprobante') required @endif class="block w-full rounded-lg border border-gray-600 bg-gray-900 text-sm text-gray-200 file:mr-4 file:border-0 file:bg-gray-700 file:px-4 file:py-2 file:text-white">
                            @error('documentos.'.$campo) <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </section>

            <section aria-labelledby="fotografia-titulo" class="rounded-lg border border-gray-700 bg-gray-900/60 p-5">
                <h2 id="fotografia-titulo" class="text-xl font-semibold text-white">Fotografía</h2>
                <p class="mt-1 text-sm text-gray-300">Selecciona una imagen JPG/PNG o toma una foto con la cámara del dispositivo.</p>
                <div class="mt-4 grid gap-6 md:grid-cols-2">
                    <div>
                        <label for="documento-fotografia" class="mb-1 block text-sm font-medium text-gray-200">Subir fotografía</label>
                        <input id="documento-fotografia" name="documentos[fotografia]" type="file" accept="image/jpeg,image/png,.jpg,.jpeg,.png" class="block w-full rounded-lg border border-gray-600 bg-gray-900 text-sm text-gray-200 file:mr-4 file:border-0 file:bg-gray-700 file:px-4 file:py-2 file:text-white">
                        @error('documentos.fotografia') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                        @error('fotografia_base64') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div class="space-y-3">
                        <div class="flex flex-wrap gap-2">
                            <button id="activar-camara" type="button" class="rounded-lg bg-gray-700 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-600">Activar cámara</button>
                            <button id="capturar-foto" type="button" disabled class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-50">Tomar fotografía</button>
                        </div>
                        <video id="vista-camara" autoplay playsinline muted class="hidden max-h-64 w-full rounded-lg bg-black" aria-label="Vista previa de la cámara"></video>
                        <img id="vista-fotografia" alt="Vista previa de la fotografía" class="hidden max-h-64 rounded-lg border border-gray-700">
                        <canvas id="lienzo-fotografia" class="hidden"></canvas>
                        <input id="fotografia-base64" name="fotografia_base64" type="hidden" value="{{ old('fotografia_base64') }}">
                        <p id="estado-camara" role="status" aria-live="polite" class="text-sm text-gray-400"></p>
                    </div>
                </div>
            </section>

            <section id="seccion-tutor" aria-labelledby="tutor-titulo" class="rounded-lg border border-amber-700/60 bg-amber-950/20 p-5">
                <h2 id="tutor-titulo" class="text-xl font-semibold text-white">Consentimiento del tutor</h2>
                <p class="mt-1 text-sm text-amber-100">Obligatorio si el jugador es de la rama infantil, de categoría Juvenil o menor de 18 años. Sin el consentimiento de su madre, padre o tutor no se puede guardar el registro.</p>
                @error('tutor') <p role="alert" class="mt-3 rounded border border-red-700 bg-red-950/40 p-3 text-sm text-red-200">{{ $message }}</p> @enderror
                @error('tutor.consentimiento') <p role="alert" class="mt-3 rounded border border-red-700 bg-red-950/40 p-3 text-sm text-red-200">{{ $message }}</p> @enderror
                <div class="mt-4 grid gap-5 md:grid-cols-2">
                    <div>
                        <label for="tutor-nombre" class="mb-1 block text-sm font-medium text-gray-200">Nombre completo del tutor</label>
                        <input id="tutor-nombre" name="tutor[nombre_completo]" value="{{ old('tutor.nombre_completo') }}" maxlength="200" autocomplete="off" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none">
                        @error('tutor.nombre_completo') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="tutor-parentesco" class="mb-1 block text-sm font-medium text-gray-200">Parentesco</label>
                        <select id="tutor-parentesco" name="tutor[parentesco]" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-white">
                            <option value="">Selecciona parentesco</option>
                            @foreach (config('privacidad.parentescos_tutor') as $parentesco)
                                <option value="{{ $parentesco }}" @selected(old('tutor.parentesco') === $parentesco)>{{ $parentesco }}</option>
                            @endforeach
                        </select>
                        @error('tutor.parentesco') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="tutor-telefono" class="mb-1 block text-sm font-medium text-gray-200">Teléfono (10 dígitos)</label>
                        <input id="tutor-telefono" name="tutor[telefono]" type="tel" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" value="{{ old('tutor.telefono') }}" autocomplete="off" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none">
                        @error('tutor.telefono') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="tutor-correo" class="mb-1 block text-sm font-medium text-gray-200">Correo (opcional)</label>
                        <input id="tutor-correo" name="tutor[correo]" type="email" maxlength="160" value="{{ old('tutor.correo') }}" autocomplete="off" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none">
                        @error('tutor.correo') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                </div>
                <label class="mt-4 flex items-start gap-3 text-sm text-gray-100">
                    <input id="tutor-consentimiento" name="tutor[consentimiento]" type="checkbox" value="1" @checked(old('tutor.consentimiento')) class="mt-1 h-4 w-4 rounded border-gray-500 bg-gray-900 text-emerald-600">
                    <span>La madre, padre o tutor leyó el aviso de privacidad y da su consentimiento para el tratamiento de los datos personales del jugador.</span>
                </label>
            </section>

            <section aria-labelledby="aviso-titulo" class="rounded-lg border border-gray-700 bg-gray-900/60 p-5">
                <h2 id="aviso-titulo" class="text-xl font-semibold text-white">Aviso de privacidad</h2>
                <p class="mt-1 text-sm text-gray-300">Muéstralo al jugador o a su tutor antes de guardar el registro.</p>
                <div id="aviso-privacidad" tabindex="0" class="mt-4 max-h-64 overflow-y-auto rounded-lg border border-gray-700 bg-gray-950/60 p-4">
                    @include('privacidad._aviso_jugador')
                </div>
                <label class="mt-4 flex items-start gap-3 text-sm text-gray-100">
                    <input id="aviso-aceptado" name="aviso_privacidad_aceptado" type="checkbox" value="1" required @checked(old('aviso_privacidad_aceptado')) class="mt-1 h-4 w-4 rounded border-gray-500 bg-gray-900 text-emerald-600">
                    <span>Mostré el aviso de privacidad al jugador o a su tutor y lo leyó.</span>
                </label>
                @error('aviso_privacidad_aceptado') <p role="alert" class="mt-2 text-sm text-red-300">{{ $message }}</p> @enderror
            </section>

            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-700 pt-5">
                <a href="{{ url()->previous() }}" class="rounded-lg border border-gray-600 px-4 py-2 text-sm font-semibold text-gray-200 hover:bg-gray-700">Cancelar</a>
                <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-500">Registrar jugador</button>
            </div>
        </form>
    @endif
</main>

<script>
(() => {
    const temporada = document.getElementById('temporada_id');
    const categoria = document.getElementById('categoria_id');
    const equipo = document.getElementById('equipo_id');
    const camara = document.getElementById('vista-camara');
    const vista = document.getElementById('vista-fotografia');
    const botonActivar = document.getElementById('activar-camara');
    const botonCapturar = document.getElementById('capturar-foto');
    const fotografia = document.getElementById('documento-fotografia');
    const fotografiaBase64 = document.getElementById('fotografia-base64');
    const estado = document.getElementById('estado-camara');
    const lienzo = document.getElementById('lienzo-fotografia');
    let flujo = null;

    if (!temporada || !categoria || !equipo) return;

    const filtrar = () => {
        const temporadaId = temporada.value;
        const categoriaId = categoria.value;

        [...categoria.options].forEach((opcion) => {
            if (!opcion.value) return;
            opcion.hidden = opcion.dataset.temporada !== temporadaId;
            if (opcion.hidden && opcion.selected) opcion.selected = false;
        });
        categoria.disabled = !temporadaId;

        [...equipo.options].forEach((opcion) => {
            if (!opcion.value) return;
            opcion.hidden = opcion.dataset.temporada !== temporadaId || opcion.dataset.categoria !== categoriaId;
            if (opcion.hidden && opcion.selected) opcion.selected = false;
        });
        equipo.disabled = !temporadaId || !categoriaId;
    };

    // HU-020: solo es ayuda visual; el servidor decide si exige el consentimiento del tutor.
    const seccionTutor = document.getElementById('seccion-tutor');
    const nacimiento = document.getElementById('fecha_nacimiento');
    const camposTutor = ['tutor-nombre', 'tutor-parentesco', 'tutor-telefono', 'tutor-consentimiento'].map((id) => document.getElementById(id));
    const mayoriaDeEdad = {{ (int) config('privacidad.mayoria_de_edad') }};

    const esMenor = () => {
        if (!nacimiento?.value) return false;
        const fecha = new Date(nacimiento.value + 'T00:00:00');
        const hoy = new Date();
        let edad = hoy.getFullYear() - fecha.getFullYear();
        if (hoy.getMonth() < fecha.getMonth() || (hoy.getMonth() === fecha.getMonth() && hoy.getDate() < fecha.getDate())) edad--;
        return edad < mayoriaDeEdad;
    };

    const actualizarTutor = () => {
        if (!seccionTutor) return;
        const requiere = categoria.selectedOptions[0]?.dataset.requiereTutor === '1' || esMenor();
        seccionTutor.hidden = !requiere;
        camposTutor.forEach((campo) => { if (campo) campo.required = requiere; });
    };

    temporada.addEventListener('change', () => { categoria.value = ''; equipo.value = ''; filtrar(); actualizarTutor(); });
    categoria.addEventListener('change', () => { equipo.value = ''; filtrar(); actualizarTutor(); });
    nacimiento?.addEventListener('change', actualizarTutor);
    filtrar();
    actualizarTutor();

    fotografia?.addEventListener('change', () => {
        fotografiaBase64.value = '';
        const archivo = fotografia.files?.[0];
        if (!archivo) return;
        vista.src = URL.createObjectURL(archivo);
        vista.classList.remove('hidden');
    });

    botonActivar?.addEventListener('click', async () => {
        if (!navigator.mediaDevices?.getUserMedia) {
            estado.textContent = 'Este dispositivo o navegador no permite usar la cámara. Selecciona una imagen desde tus archivos.';
            return;
        }

        try {
            flujo = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
            camara.srcObject = flujo;
            camara.classList.remove('hidden');
            botonCapturar.disabled = false;
            estado.textContent = 'Cámara activa. Encuadra al jugador y toma la fotografía.';
        } catch {
            estado.textContent = 'No fue posible acceder a la cámara. Revisa los permisos o selecciona una imagen.';
        }
    });

    botonCapturar?.addEventListener('click', () => {
        if (!camara.videoWidth || !camara.videoHeight) {
            estado.textContent = 'Espera a que la cámara muestre la imagen antes de capturar.';
            return;
        }

        lienzo.width = camara.videoWidth;
        lienzo.height = camara.videoHeight;
        lienzo.getContext('2d')?.drawImage(camara, 0, 0);
        fotografiaBase64.value = lienzo.toDataURL('image/jpeg', 0.88);
        fotografia.value = '';
        vista.src = fotografiaBase64.value;
        vista.classList.remove('hidden');
        estado.textContent = 'Fotografía capturada y lista para enviar de forma segura.';
        flujo?.getTracks().forEach((pista) => pista.stop());
        flujo = null;
        camara.classList.add('hidden');
        botonCapturar.disabled = true;
    });

    window.addEventListener('pagehide', () => flujo?.getTracks().forEach((pista) => pista.stop()));
})();
</script>
@endsection
