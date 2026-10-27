<form action="{{ $accion }}" method="POST" class="space-y-5">
    @csrf
    @if($metodo !== 'POST')
        @method($metodo)
    @endif

    <div>
        <label for="name" class="block text-sm font-medium text-gray-300 mb-1">Nombre completo</label>
        <input type="text" name="name" id="name" value="{{ old('name', $usuario->name ?? '') }}" required 
            class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        @error('name') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="email" class="block text-sm font-medium text-gray-300 mb-1">Correo electrónico</label>
        <input type="email" name="email" id="email" value="{{ old('email', $usuario->email ?? '') }}" required 
            class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        @error('email') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">Roles asignados</label>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-gray-900/50 p-4 border border-gray-700 rounded-lg">
            @foreach ($roles as $role)
                <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="roles[]" value="{{ $role->name }}" 
                        @checked(in_array($role->name, old('roles', isset($usuario) && $usuario->exists ? $usuario->roles->pluck('name')->toArray() : [])))
                        class="w-4 h-4 rounded border-gray-600 bg-gray-700 text-blue-500 focus:ring-blue-500 focus:ring-offset-gray-900">
                    <span class="ml-2 text-sm text-gray-300">{{ $role->name }}</span>
                </label>
            @endforeach
        </div>
        @error('roles') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="border-t border-gray-700 pt-5">
        <label for="motivo" class="block text-sm font-medium text-gray-300 mb-1">Motivo de la operación (Requerido para auditoría)</label>
        <textarea name="motivo" id="motivo" rows="2" required placeholder="{{ $ejemploMotivo }}" 
            class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"></textarea>
        @error('motivo') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="flex items-center justify-end gap-3 pt-2">
        <a href="{{ route('admin.usuarios.index') }}" class="px-4 py-2 text-sm font-medium text-gray-400 hover:text-white transition">
            Cancelar
        </a>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white font-medium text-sm rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
            {{ $textoBoton }}
        </button>
    </div>
</form>