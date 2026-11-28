<?php

namespace App\Http\Requests;

use App\Models\Asamblea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Publicar o retirar un comunicado de asamblea del portal (HU-078; decisión del PO sobre PA-15, 25/11/2026).
 *
 * «publicar» es obligatorio y booleano: un valor raro («si», un arreglo o el campo ausente) ya no se toma como
 * «retirar», que quitaría del portal un comunicado publicado.
 */
class PublicarComunicadoAsambleaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $asamblea = $this->route('asamblea');
        // Gate::authorize conserva el mensaje en español de la política en el 403.
        Gate::authorize('publicarComunicado', $asamblea instanceof Asamblea ? $asamblea : Asamblea::class);

        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'publicar' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'publicar.required' => 'Indica si el comunicado se publica o se retira del portal.',
            'publicar.boolean' => 'Indica si el comunicado se publica o se retira del portal.',
        ];
    }
}
