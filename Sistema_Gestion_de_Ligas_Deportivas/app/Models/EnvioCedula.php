<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Clave de envío ya aplicada a la alineación o al marcador de una cédula (HU-036, RNF-21).
 * Los eventos y las incidencias guardan su clave en su propia fila.
 *
 * @property int $id
 * @property int $cedula_id
 * @property string $tipo
 * @property string $clave_envio
 */
class EnvioCedula extends Model
{
    public const ALINEACION = 'alineacion';

    public const MARCADOR = 'marcador';

    protected $table = 'envios_cedula';

    protected $fillable = ['cedula_id', 'tipo', 'clave_envio'];
}
