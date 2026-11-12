<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Verifica que la ruta raíz presente la vista de inicio de sesión de la liga en lugar del welcome predeterminado.
     */
    public function test_la_ruta_raiz_muestra_la_pantalla_de_inicio_de_sesion(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Liga Municipal de Fútbol');
        $response->assertSee('Iniciar sesión');
        $response->assertDontSee('Documentation');
        $response->assertDontSee('Laracasts');
    }
}
