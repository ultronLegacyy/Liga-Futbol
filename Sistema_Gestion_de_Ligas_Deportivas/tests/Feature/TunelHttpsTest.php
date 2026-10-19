<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TunelHttpsTest extends TestCase
{
    // Direccion desde la que llega cloudflared al correr en el mismo equipo
    private const IP_PROXY_LOCAL = '127.0.0.1';

    // Direccion externa de documentacion (RFC 5737) que no es un proxy de confianza
    private const IP_EXTERNA = '203.0.113.10';

    // Direccion del visitante que el tunel reporta en X-Forwarded-For
    private const IP_VISITANTE = '198.51.100.7';

    public function test_peticion_desde_proxy_local_con_https_genera_formulario_de_login_con_https(): void
    {
        $respuesta = $this->withServerVariables(['REMOTE_ADDR' => self::IP_PROXY_LOCAL])
            ->get('/login', ['X-Forwarded-Proto' => 'https']);

        // El action del formulario debe usar https para evitar el bloqueo por contenido mixto
        $respuesta->assertOk();
        $respuesta->assertSee('action="https://', false);
    }

    public function test_peticion_desde_ip_externa_ignora_x_forwarded_proto(): void
    {
        $respuesta = $this->withServerVariables(['REMOTE_ADDR' => self::IP_EXTERNA])
            ->get('/login', ['X-Forwarded-Proto' => 'https']);

        // Un cliente externo no puede forzar el esquema con el encabezado
        $respuesta->assertOk();
        $respuesta->assertSee('action="http://', false);
        $respuesta->assertDontSee('action="https://', false);
    }

    public function test_x_forwarded_for_se_respeta_desde_el_proxy_local(): void
    {
        $this->registrarRutaDeIpCliente();

        $respuesta = $this->withServerVariables(['REMOTE_ADDR' => self::IP_PROXY_LOCAL])
            ->get('/prueba-ip-cliente', ['X-Forwarded-For' => self::IP_VISITANTE]);

        // La IP registrada debe ser la del visitante y no la del tunel
        $respuesta->assertOk();
        $respuesta->assertContent(self::IP_VISITANTE);
    }

    public function test_x_forwarded_for_se_ignora_desde_una_ip_externa(): void
    {
        $this->registrarRutaDeIpCliente();

        $respuesta = $this->withServerVariables(['REMOTE_ADDR' => self::IP_EXTERNA])
            ->get('/prueba-ip-cliente', ['X-Forwarded-For' => self::IP_VISITANTE]);

        // Un cliente externo no puede falsificar la IP que guarda la auditoria
        $respuesta->assertOk();
        $respuesta->assertContent(self::IP_EXTERNA);
    }

    // Ruta temporal que devuelve la IP del cliente tal como la resuelve Laravel
    private function registrarRutaDeIpCliente(): void
    {
        Route::get('/prueba-ip-cliente', fn () => request()->ip());
    }
}
