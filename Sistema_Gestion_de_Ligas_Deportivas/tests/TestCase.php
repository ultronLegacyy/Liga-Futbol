<?php

namespace Tests;

use App\Http\Middleware\ExigirSegundoFactor;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * HU-082 (decisión de Daniel, 25/11): las pruebas de otros módulos inician sesión con roles obligados sin segundo
     * factor; para ellas se omite ExigirSegundoFactor. Las pruebas del segundo factor lo activan con esta propiedad.
     * En la aplicación el middleware siempre corre.
     */
    protected bool $exigeSegundoFactor = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->exigeSegundoFactor) {
            $this->withoutMiddleware(ExigirSegundoFactor::class);
        }
    }
}
