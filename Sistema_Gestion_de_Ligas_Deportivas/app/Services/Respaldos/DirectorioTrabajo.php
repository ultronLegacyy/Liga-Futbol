<?php

namespace App\Services\Respaldos;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

// Carpeta temporal privada de un respaldo o una restauración; se borra siempre al terminar
class DirectorioTrabajo
{
    private string $raiz;

    public function __construct()
    {
        $base = rtrim((string) config('respaldos.trabajo'), '/');
        $this->raiz = $base.'/'.now()->format('YmdHis').'-'.Str::lower(Str::random(8));

        if (! @mkdir($this->raiz, 0700, true) && ! is_dir($this->raiz)) {
            throw new RespaldoException('No se pudo crear la carpeta temporal de los respaldos.');
        }
    }

    public function ruta(string $archivo): string
    {
        return $this->raiz.'/'.$archivo;
    }

    public function limpiar(): void
    {
        File::deleteDirectory($this->raiz);
    }
}
