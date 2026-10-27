<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ComponentesReutilizablesTest extends TestCase
{
    public function test_el_componente_de_alerta_muestra_mensajes_en_espanol(): void
    {
        $html = Blade::render(
            '<x-alert type="success" message="Los cambios se guardaron correctamente." />'
        );

        $this->assertStringContainsString(
            'Los cambios se guardaron correctamente.',
            $html
        );
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('bg-green-500/10', $html);
    }

    public function test_el_componente_de_alerta_muestra_errores_accesibles(): void
    {
        $html = Blade::render(
            '<x-alert type="error" message="No se pudo guardar la información." />'
        );

        $this->assertStringContainsString(
            'No se pudo guardar la información.',
            $html
        );
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('aria-live="assertive"', $html);
    }

    public function test_el_componente_de_tabla_muestra_titulo_y_contenido(): void
    {
        $html = Blade::render(
            '<x-table caption="Catálogo de canchas y sedes">
                <thead><tr><th scope="col">Nombre</th></tr></thead>
                <tbody><tr><td>Cancha Central</td></tr></tbody>
            </x-table>'
        );

        $this->assertStringContainsString('Catálogo de canchas y sedes', $html);
        $this->assertStringContainsString('scope="col"', $html);
        $this->assertStringContainsString('Cancha Central', $html);
        $this->assertStringContainsString('overflow-x-auto', $html);
    }

    public function test_el_campo_de_formulario_muestra_etiqueta_y_ayuda(): void
    {
        $html = Blade::render(
            '<x-form.field name="nombre" label="Nombre" required
                help="Escribe el nombre de la cancha.">
                <input id="nombre" name="nombre" type="text" required>
            </x-form.field>',
            ['errors' => new ViewErrorBag()]
        );

        $this->assertStringContainsString('Nombre', $html);
        $this->assertStringContainsString('(obligatorio)', $html);
        $this->assertStringContainsString(
            'Escribe el nombre de la cancha.',
            $html
        );
        $this->assertStringContainsString('for="nombre"', $html);
    }
}
