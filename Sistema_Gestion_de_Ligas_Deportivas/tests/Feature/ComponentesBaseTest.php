<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

// HAB-S7 · S7-T01 (SCRUM-234): componentes base del nuevo diseño
class ComponentesBaseTest extends TestCase
{
    public function test_el_boton_es_enlace_con_href_y_boton_sin_el_con_al_menos_44_px(): void
    {
        $enlace = Blade::render('<x-button href="/panel">Ir</x-button>');
        $boton = Blade::render('<x-button type="submit" variante="peligro">Borrar</x-button>');

        $this->assertStringContainsString('<a href="/panel"', $enlace);
        $this->assertStringContainsString('min-h-11', $enlace);
        $this->assertStringContainsString('<button type="submit"', $boton);
        $this->assertStringContainsString('bg-red-700', $boton);
    }

    public function test_la_tarjeta_muestra_su_titulo_y_contenido(): void
    {
        $html = Blade::render('<x-card titulo="Resumen" class="mt-4">Contenido</x-card>');

        $this->assertStringContainsString('<h2 class="mb-4 text-lg font-bold text-white">Resumen</h2>', $html);
        $this->assertStringContainsString('Contenido', $html);
        $this->assertStringContainsString('mt-4', $html);
        $this->assertStringContainsString('rounded-2xl', $html);
    }
}
