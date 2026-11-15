<?php

namespace Tests\Concerns;

/**
 * Datos de HU-020 para registrar a un jugador en pruebas de otras historias: aviso de
 * privacidad mostrado y consentimiento del tutor. A los adultos de categorías sin tutor
 * los datos del tutor no les aplican y el registro los ignora.
 */
trait DatosPrivacidadJugador
{
    /** @return array{aviso_privacidad_aceptado: bool, tutor: array<string, string|bool>} */
    protected function privacidad(): array
    {
        return [
            'aviso_privacidad_aceptado' => true,
            'tutor' => [
                'nombre_completo' => 'Tutor de Prueba Registro',
                'parentesco' => 'Tutor legal',
                'telefono' => '5500000000',
                'consentimiento' => true,
            ],
        ];
    }
}
