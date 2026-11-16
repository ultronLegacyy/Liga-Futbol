<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DescartaArreglosEnCamposDeTexto;
use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreJugadorRequest extends FormRequest
{
    use DescartaArreglosEnCamposDeTexto {
        prepareForValidation as descartarArreglosEnCamposDeTexto;
    }

    /** Mensaje de cada campo de texto que llegó como arreglo (revisión del #83, recomendado 3). */
    private const MENSAJES_ARREGLO = [
        'nombre' => 'El nombre del jugador debe ser texto.',
        'apellidos' => 'Los apellidos del jugador deben ser texto.',
        'fecha_nacimiento' => 'La fecha de nacimiento no es válida; usa el calendario (AAAA-MM-DD).',
        'curp' => 'La CURP debe ser texto de 18 caracteres.',
        'dorsal' => 'El dorsal debe ser un número entero.',
        'fotografia_base64' => 'La fotografía capturada no es válida; vuelve a capturarla.',
        'tutor.nombre_completo' => 'El nombre del tutor debe ser texto.',
        'tutor.parentesco' => 'Selecciona un parentesco válido para el tutor.',
        'tutor.telefono' => 'El teléfono del tutor debe tener 10 dígitos.',
        'tutor.correo' => 'El correo del tutor no es válido.',
        'tutor.consentimiento' => 'El consentimiento del tutor no es válido.',
    ];

    /** @var list<string> */
    private array $camposConArreglo = [];

    /**
     * Campos de texto del formulario: si llegan como arreglo se descartan antes de
     * validar, para que old() no provoque un 500 al volver a pintar el formulario.
     *
     * @return list<string>
     */
    protected function camposDeTexto(): array
    {
        return [
            'nombre', 'apellidos', 'fecha_nacimiento', 'curp', 'dorsal', 'fotografia_base64',
            'tutor.nombre_completo', 'tutor.parentesco', 'tutor.telefono', 'tutor.correo', 'tutor.consentimiento',
        ];
    }

    /**
     * Antes de descartar los arreglos se anota en qué campos llegaron, para responder con un
     * error en lugar de guardar el registro sin el dato (por ejemplo `dorsal[]=x`).
     */
    protected function prepareForValidation(): void
    {
        foreach ($this->camposDeTexto() as $campo) {
            if (is_array($this->input($campo))) {
                $this->camposConArreglo[] = $campo;
            }
        }

        $this->descartarArreglosEnCamposDeTexto();
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('No cuenta con autorización para registrar jugadores en este equipo.');
    }

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->hasRole('Administrador General')) {
            return true;
        }

        if ($this->routeIs('jugadores.store')) {
            if (! $user->hasRole('Delegado del Club')) {
                return $user->can('jugadores.crear');
            }

            if ($user->club_id === null) {
                return false;
            }

            if (! $user->can('jugadores.crear')) {
                return false;
            }

            $equipoId = $this->input('equipo_id');

            if (! is_numeric($equipoId)) {
                return true;
            }

            $equipo = Equipo::query()->find((int) $equipoId);

            return $equipo === null || (int) $equipo->club_id === (int) $user->club_id;
        }

        $equipo = $this->equipoSeleccionado();

        if ($user->hasRole('Delegado del Club')) {
            return $equipo instanceof Equipo
                && $user->can('jugadores.crear')
                && $user->can('addPlayer', $equipo);
        }

        return $equipo instanceof Equipo
            && ($user->can('addPlayer', $equipo) || $user->can('jugadores.crear'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $equipo = $this->equipoSeleccionado();
        $esFormulario = $this->routeIs('jugadores.store');
        $equipoId = $equipo?->getKey() ?? $this->input('equipo_id');
        $archivos = 'sometimes';

        $reglas = [
            'persona_id' => [
                $esFormulario ? 'nullable' : 'required',
                'integer',
                Rule::exists('personas', 'id'),
            ],
            'equipo_id' => $esFormulario ? [
                'required',
                'integer',
                Rule::exists('equipos', 'id')->where('temporada_id', $this->input('temporada_id')),
            ] : ['sometimes', 'integer'],
            'temporada_id' => $esFormulario
                ? ['required', 'integer', Rule::exists('temporadas', 'id')->where('estado', 'activa')]
                : ['sometimes', 'integer'],
            'categoria_id' => $esFormulario
                ? ['required', 'integer', Rule::exists('categorias', 'id')->where('temporada_id', $this->input('temporada_id'))]
                : ['sometimes', 'integer'],
            'nombre' => [$esFormulario ? 'required_without:persona_id' : 'sometimes', 'string', 'max:120'],
            'apellidos' => [$esFormulario ? 'required_without:persona_id' : 'sometimes', 'string', 'max:160'],
            'fecha_nacimiento' => [$esFormulario ? 'required_without:persona_id' : 'sometimes', 'date_format:Y-m-d', 'before:today'],
            'curp' => [
                $esFormulario ? 'required_without:persona_id' : 'sometimes',
                'string',
                'size:18',
                'regex:/^[A-Z]{4}[0-9]{6}[HMX][A-Z]{5}[A-Z0-9][0-9]$/i',
            ],
            'dorsal' => [
                'nullable',
                'integer',
                'min:1',
                'max:99',
                Rule::unique('jugadores_equipos', 'dorsal')->where('equipo_id', $equipoId),
            ],
            'documentos' => ['sometimes', 'array'],
            'documentos.ine' => [$archivos, 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'documentos.curp' => [$archivos, 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'documentos.fotografia' => [
                'nullable',
                $esFormulario ? 'required_without:fotografia_base64' : 'sometimes',
                'image',
                'mimes:jpg,jpeg,png',
                'max:5120',
            ],
            'documentos.comprobante' => [$esFormulario ? 'required' : $archivos, 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'fotografia_base64' => [
                $esFormulario ? 'required_without:documentos.fotografia' : 'nullable',
                'string',
                'max:7340032',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }

                    if (! preg_match('/^data:image\/(jpeg|png);base64,([A-Za-z0-9+\/=]+)$/', $value, $matches)) {
                        $fail('La fotografía capturada debe ser una imagen JPG o PNG válida.');

                        return;
                    }

                    $bytes = base64_decode($matches[2], true);
                    $mime = $bytes === false ? false : (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

                    if ($bytes === false || strlen($bytes) > 5 * 1024 * 1024 || ! in_array($mime, ['image/jpeg', 'image/png'], true)) {
                        $fail('La fotografía capturada debe ser una imagen JPG o PNG de hasta 5 MB.');
                    }
                },
            ],
            // HU-020: el aviso se muestra en todo registro; los datos del tutor los exige
            // ConsentimientoPrivacidadService según la rama, la categoría y la edad.
            'aviso_privacidad_aceptado' => ['bail', 'required', 'accepted'],
            'tutor' => ['bail', 'sometimes', 'nullable', 'array'],
            'tutor.nombre_completo' => ['bail', 'nullable', 'string', 'min:5', 'max:200'],
            'tutor.parentesco' => ['bail', 'nullable', 'string', Rule::in((array) config('privacidad.parentescos_tutor'))],
            'tutor.telefono' => ['bail', 'nullable', 'string', 'regex:/^[0-9]{10}$/'],
            'tutor.correo' => ['bail', 'nullable', 'string', 'email', 'max:160'],
            'tutor.consentimiento' => ['bail', 'nullable', 'boolean'],
        ];

        if ($equipo instanceof Equipo) {
            $reglas['persona_id'][] = Rule::unique('jugadores_equipos', 'persona_id')->where('equipo_id', $equipo->id);
        }

        return $reglas;
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->camposConArreglo as $campo) {
                if (! $validator->errors()->has($campo)) {
                    $validator->errors()->add($campo, self::MENSAJES_ARREGLO[$campo] ?? 'El dato no es válido.');
                }
            }

            $equipo = $this->equipoSeleccionado();

            if (! $equipo instanceof Equipo) {
                return;
            }

            if ($this->routeIs('jugadores.store')) {
                $categoria = Categoria::query()->find($this->integer('categoria_id'));

                if ((int) $equipo->temporada_id !== $this->integer('temporada_id')
                    || $categoria === null
                    || (int) $equipo->grupo->categoria_id !== (int) $categoria->getKey()) {
                    $validator->errors()->add('equipo_id', 'El equipo no pertenece a la categoría y temporada seleccionadas.');

                    return;
                }

                if (! $this->filled('persona_id') && ! $this->hasFile('documentos.ine') && ! $this->hasFile('documentos.curp')) {
                    $validator->errors()->add('documentos', 'Adjunta una identificación del jugador: INE o CURP.');
                }
            }

            if ($validator->errors()->has('persona_id') || ! $this->filled('persona_id') || $equipo->temporada_id === null) {
                return;
            }

            $personaActivaEnOtroEquipo = Jugador::query()
                ->where('persona_id', $this->integer('persona_id'))
                ->where('estado_elegibilidad', 'Activo')
                ->where('equipo_id', '!=', $equipo->getKey())
                ->whereHas('equipo', fn (Builder $query): Builder => $query->where('temporada_id', $equipo->temporada_id))
                ->exists();

            if ($personaActivaEnOtroEquipo) {
                $validator->errors()->add('persona_id', 'El jugador ya está activo en otro equipo de esta temporada.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'persona_id.required' => 'El jugador es obligatorio.',
            'persona_id.exists' => 'El jugador seleccionado no existe.',
            'persona_id.unique' => 'El jugador ya está registrado en este equipo.',
            'persona_id.integer' => 'El jugador seleccionado no es válido.',
            'equipo_id.required' => 'Selecciona el equipo.',
            'equipo_id.integer' => 'Selecciona un equipo válido.',
            'temporada_id.required' => 'Selecciona la temporada.',
            'temporada_id.integer' => 'Selecciona una temporada válida.',
            'categoria_id.required' => 'Selecciona la categoría.',
            'categoria_id.integer' => 'Selecciona una categoría válida.',
            'nombre.string' => 'El nombre del jugador debe ser texto.',
            'nombre.max' => 'El nombre del jugador no debe superar los :max caracteres.',
            'apellidos.string' => 'Los apellidos del jugador deben ser texto.',
            'apellidos.max' => 'Los apellidos del jugador no deben superar los :max caracteres.',
            'fecha_nacimiento.required_without' => 'La fecha de nacimiento del jugador es obligatoria.',
            'fecha_nacimiento.date_format' => 'La fecha de nacimiento no es válida; usa el calendario (AAAA-MM-DD).',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'curp.string' => 'La CURP debe ser texto.',
            'curp.size' => 'La CURP debe tener 18 caracteres.',
            'documentos.array' => 'Los documentos adjuntos no son válidos.',
            'documentos.ine.file' => 'La identificación oficial debe ser un archivo.',
            'documentos.curp.file' => 'El documento de CURP debe ser un archivo.',
            'documentos.comprobante.file' => 'El comprobante de domicilio debe ser un archivo.',
            'fotografia_base64.required_without' => 'Debes subir una fotografía o capturarla con la cámara.',
            'fotografia_base64.string' => 'La fotografía capturada no es válida; vuelve a capturarla.',
            'fotografia_base64.max' => 'La fotografía capturada es demasiado grande.',
            'dorsal.integer' => 'El dorsal debe ser un número entero.',
            'dorsal.min' => 'El dorsal debe ser al menos :min.',
            'dorsal.max' => 'El dorsal no debe ser mayor que :max.',
            'dorsal.unique' => 'El dorsal ya está asignado a otro jugador de este equipo.',
            'temporada_id.exists' => 'Selecciona una temporada activa.',
            'categoria_id.exists' => 'La categoría no pertenece a la temporada seleccionada.',
            'equipo_id.exists' => 'El equipo no pertenece a la temporada seleccionada.',
            'nombre.required_without' => 'El nombre del jugador es obligatorio.',
            'apellidos.required_without' => 'Los apellidos del jugador son obligatorios.',
            'curp.required_without' => 'La CURP del jugador es obligatoria.',
            'curp.regex' => 'La CURP no tiene un formato válido: 4 letras, 6 dígitos de la fecha, sexo (H, M o X), 5 letras, homoclave y dígito verificador.',
            'documentos.ine.mimes' => 'La identificación oficial debe ser un archivo JPG, PNG o PDF.',
            'documentos.ine.max' => 'La identificación oficial no debe superar los 10 MB.',
            'documentos.curp.mimes' => 'El documento de CURP debe ser un archivo JPG, PNG o PDF.',
            'documentos.curp.max' => 'El documento de CURP no debe superar los 10 MB.',
            'documentos.fotografia.required_without' => 'Debes subir una fotografía o capturarla con la cámara.',
            'documentos.fotografia.image' => 'La fotografía debe ser una imagen válida.',
            'documentos.fotografia.mimes' => 'La fotografía debe estar en formato JPG o PNG.',
            'documentos.fotografia.max' => 'La fotografía no debe superar los 5 MB.',
            'documentos.comprobante.required' => 'Debes adjuntar el comprobante de domicilio.',
            'documentos.comprobante.mimes' => 'El comprobante de domicilio debe ser un archivo JPG, PNG o PDF.',
            'documentos.comprobante.max' => 'El comprobante de domicilio no debe superar los 10 MB.',
            'aviso_privacidad_aceptado.required' => 'Debes mostrar el aviso de privacidad y confirmar que el jugador o su tutor lo leyó antes de guardar el registro.',
            'aviso_privacidad_aceptado.accepted' => 'Debes mostrar el aviso de privacidad y confirmar que el jugador o su tutor lo leyó antes de guardar el registro.',
            'tutor.array' => 'Los datos del tutor no son válidos; captúralos en la sección «Consentimiento del tutor».',
            'tutor.nombre_completo.string' => 'El nombre del tutor debe ser texto.',
            'tutor.parentesco.string' => 'Selecciona un parentesco válido para el tutor.',
            'tutor.telefono.string' => 'El teléfono del tutor debe tener 10 dígitos.',
            'tutor.correo.string' => 'El correo del tutor no es válido.',
            'tutor.correo.max' => 'El correo del tutor no debe tener más de :max caracteres.',
            'tutor.nombre_completo.min' => 'Escribe el nombre completo del tutor.',
            'tutor.nombre_completo.max' => 'El nombre del tutor no debe superar los 200 caracteres.',
            'tutor.parentesco.in' => 'Selecciona un parentesco válido para el tutor.',
            'tutor.telefono.regex' => 'El teléfono del tutor debe tener 10 dígitos.',
            'tutor.correo.email' => 'El correo del tutor no es válido.',
            'tutor.consentimiento.boolean' => 'El consentimiento del tutor no es válido.',
        ];
    }

    private function equipoSeleccionado(): ?Equipo
    {
        $routeEquipo = $this->route('equipo');

        if ($routeEquipo instanceof Equipo) {
            return $routeEquipo;
        }

        $equipoId = is_numeric($routeEquipo) ? $routeEquipo : $this->input('equipo_id');

        return is_numeric($equipoId) ? Equipo::query()->find((int) $equipoId) : null;
    }
}
