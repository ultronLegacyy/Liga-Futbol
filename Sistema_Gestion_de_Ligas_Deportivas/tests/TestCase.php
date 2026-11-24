<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // HAB-S7 (SCRUM-234): el panel carga sus estilos con @vite; las pruebas no compilan los assets
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
