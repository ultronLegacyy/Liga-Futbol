{{-- Formulario compartido del alta y la edición de una cuenta: cada campo muestra su error en español (CA4, IU-03) --}}
@php
    $campo = 'w-full rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400';
    $borde = fn (string $nombre): string => $errors->has($nombre) ? 'border-red-500' : 'border-gray-600';
    $rolesElegidos = old('roles', $usuario->exists ? $usuario->roles->pluck('name')->all() : []);
    $errorRoles = $errors->first('roles') ?: $errors->first('roles.*');
@endphp

@if ($errors->any())
    <div role="alert" class="mb-6 px-4 py-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
        No se guardó la cuenta. Revisa los campos marcados.
    </div>
@endif

<form method="POST" action="{{ $accion }}" novalidate class="space-y-5">
    @csrf
    @if ($metodo === 'PUT')
        @method('PUT')
    @endif

    <div>
        <label for="name" class="block text-sm font-medium text-gray-200 mb-1">
            Nombre <span class="text-red-400" aria-hidden="true">*</span>
        </label>
        <input type="text" id="name" name="name" maxlength="255" required autofocus autocomplete="off"
            value="{{ old('name', $usuario->name) }}"
            class="{{ $campo }} {{ $borde('name') }}"
            @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
        @error('name')
            <p id="name-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="email" class="block text-sm font-medium text-gray-200 mb-1">
            Correo electrónico <span class="text-red-400" aria-hidden="true">*</span>
        </label>
        <input type="email" id="email" name="email" maxlength="255" required autocomplete="off" inputmode="email" autocapitalize="none" spellcheck="false"
            value="{{ old('email', $usuario->email) }}"
            class="{{ $campo }} {{ $borde('email') }}"
            @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
        @error('email')
            <p id="email-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <fieldset @if ($errorRoles) aria-describedby="roles-error" @endif>
        <legend class="block text-sm font-medium text-gray-200 mb-2">
            Roles <span class="text-red-400" aria-hidden="true">*</span>
            <span class="font-normal text-gray-400">(uno o varios)</span>
        </legend>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 p-3 rounded-lg border {{ $errorRoles ? 'border-red-500' : 'border-gray-600' }}">
            @foreach ($roles as $rol)
                <label class="flex items-center gap-2 text-sm text-gray-200">
                    <input type="checkbox" name="roles[]" value="{{ $rol->name }}"
                        class="w-4 h-4 accent-green-600 focus:ring-2 focus:ring-green-400"
                        @checked(in_array($rol->name, $rolesElegidos, true))>
                    {{ $rol->name }}
                </label>
            @endforeach
        </div>
        @if ($errorRoles)
            <p id="roles-error" class="mt-1 text-sm text-red-400">{{ $errorRoles }}</p>
        @endif
    </fieldset>

    <div>
        <label for="motivo" class="block text-sm font-medium text-gray-200 mb-1">
            Motivo <span class="text-red-400" aria-hidden="true">*</span>
        </label>
        <input type="text" id="motivo" name="motivo" maxlength="255" required
            value="{{ old('motivo') }}" placeholder="{{ $ejemploMotivo }}"
            class="{{ $campo }} {{ $borde('motivo') }}"
            aria-describedby="motivo-ayuda @error('motivo') motivo-error @enderror"
            @error('motivo') aria-invalid="true" @enderror>
        <p id="motivo-ayuda" class="mt-1 text-xs text-gray-400">Queda en la auditoría junto con tu usuario, la fecha y los valores anterior y nuevo.</p>
        @error('motivo')
            <p id="motivo-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <p class="text-xs text-gray-400"><span class="text-red-400" aria-hidden="true">*</span> Campo obligatorio.</p>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
            {{ $textoBoton }}
        </button>
        <a href="{{ route('admin.usuarios.index') }}" class="px-4 py-2 text-sm text-gray-300 rounded-lg border border-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400">
            Cancelar
        </a>
    </div>
</form>
