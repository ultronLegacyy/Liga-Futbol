<?php

namespace App\Exceptions;

use LogicException;

// Se lanza al intentar modificar o borrar un registro de auditoría (HU-004, CA 2)
class AuditoriaInmutableException extends LogicException
{
    public function __construct(string $message = 'Los registros de auditoría son inmutables: no se pueden modificar ni eliminar.')
    {
        parent::__construct($message);
    }
}
