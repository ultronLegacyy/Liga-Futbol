<?php

namespace App\Http\Requests\Cedula;

// HU-075: reporte arbitral en texto libre; viaja por la cola sin conexión con su clave de envío
class GuardarInformeRequest extends CapturaCedulaRequest
{
    protected function reglas(): array
    {
        return [
            'informe_arbitral' => ['bail', 'present', 'nullable', 'string', 'max:5000'],
        ];
    }

    protected function mensajes(): array
    {
        return [
            'informe_arbitral.present' => 'Falta el reporte arbitral.',
            'informe_arbitral.string' => 'El reporte arbitral no es válido.',
            'informe_arbitral.max' => 'El reporte arbitral admite hasta :max caracteres.',
        ];
    }

    public function informe(): ?string
    {
        $informe = $this->validated('informe_arbitral');

        return is_string($informe) ? $informe : null;
    }
}
