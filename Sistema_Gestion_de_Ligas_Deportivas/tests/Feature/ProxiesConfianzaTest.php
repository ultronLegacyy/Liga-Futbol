<?php

namespace Tests\Feature;

use Illuminate\Http\Middleware\TrustProxies;
use Tests\TestCase;

// HU-010 (SCRUM-226, RNF-42): los proxies de confianza del túnel se definen con TRUSTED_PROXIES, sin tocar el código
class ProxiesConfianzaTest extends TestCase
{
    protected function tearDown(): void
    {
        TrustProxies::flushState();

        parent::tearDown();
    }

    // Por defecto se confía solo en el cloudflared del mismo equipo: los enlaces salen con https://
    public function test_el_tunel_local_genera_enlaces_https(): void
    {
        $this->assertSame(['127.0.0.1', '::1'], config('app.proxies_confianza'));

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('/login', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'liga.example.com', 'X-Forwarded-Port' => '443'])
            ->assertOk()
            ->assertSee('action="https://liga.example.com/login"', false);
    }

    // Un cliente externo no puede falsificar el esquema ni el dominio con los encabezados
    public function test_un_cliente_externo_no_falsifica_los_encabezados(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->get('/login', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'falso.example.com'])
            ->assertOk()
            ->assertDontSee('falso.example.com');
    }
}
