<?php

namespace App\Services;

use App\Models\CargoFinanciero;
use App\Models\Jugador;
use App\Models\Partido;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * HU-019 (RF-04.4, RNF-02, RNF-43): elegibilidad de un jugador antes de participar. Junta en un solo lugar las
 * reglas que ya existen en el sistema y devuelve, para cada bloqueo, el motivo y la regla que lo origina (CA1):
 * - estado del padrón «Suspendido» o «Baja» (HU-018);
 * - sanción deportiva vigente del Tribunal de Penas (HU-047, ControlSancionesDeportivasService);
 * - cuota de inscripción pendiente o vencida en la temporada (HU-050/HU-051);
 * - registro incompleto: sin la fotografía o el comprobante de domicilio que pide el registro (HU-016);
 * - categoría no permitida: edad de la rama infantil (HU-021) o más de una categoría arriba de otra inscripción
 *   suya en la misma temporada (regla de salto de ReglaSaltoCategoriaService).
 * Si faltan datos para decidir o algo falla, queda «no verificado» y nunca se habilita (CA3, RNF-43).
 */
class ElegibilidadJugadorService
{
    public const HABILITADO = 'habilitado';

    public const BLOQUEADO = 'bloqueado';

    public const NO_VERIFICADO = 'no_verificado';

    // Documentos que el formulario de registro exige (StoreJugadorRequest, HU-016)
    public const DOCUMENTOS_OBLIGATORIOS = ['Foto' => 'la fotografía', 'Comprobante' => 'el comprobante de domicilio'];

    public function __construct(
        private readonly ControlSancionesDeportivasService $sanciones,
        private readonly ReglaEdadCategoriaService $reglaEdad,
    ) {
    }

    /**
     * @return array{estado: string, motivos: list<array{motivo: string, regla: string}>}
     */
    public function evaluar(Jugador $jugador, ?Partido $partido = null): array
    {
        try {
            // Copia propia con todos los datos: quien llama pudo cargar la persona solo con su nombre (sin la CURP)
            $jugador = Jugador::query()
                ->with(['persona.documentos', 'equipo.grupo.categoria.temporada', 'equipo.temporada'])
                ->findOrFail($jugador->getKey());

            $faltantes = $this->datosFaltantes($jugador);
            if ($faltantes !== []) {
                return $this->noVerificado('Faltan datos para verificar la elegibilidad: '.implode(', ', $faltantes).'.');
            }

            $motivos = [
                ...$this->estadoDelPadron($jugador),
                ...$this->sancionVigente($jugador, $partido),
                ...$this->cuotaPendiente($jugador),
                ...$this->registroIncompleto($jugador),
                ...$this->categoriaNoPermitida($jugador),
            ];

            return ['estado' => $motivos === [] ? self::HABILITADO : self::BLOQUEADO, 'motivos' => $motivos];
        } catch (Throwable $error) {
            report($error);

            return $this->noVerificado('No se pudo verificar la elegibilidad por un error interno.');
        }
    }

    /**
     * Para la alineación: si no está habilitado, rechaza con el motivo y la regla de cada bloqueo (CA1).
     */
    public function exigirHabilitado(Jugador $jugador, ?Partido $partido = null, string $campo = 'jugador_id'): void
    {
        $resultado = $this->evaluar($jugador, $partido);

        if ($resultado['estado'] === self::HABILITADO) {
            return;
        }

        $persona = $jugador->persona;
        $nombre = $persona === null ? 'El jugador' : trim($persona->nombre.' '.$persona->apellidos);
        $titulo = $resultado['estado'] === self::NO_VERIFICADO
            ? "{$nombre} no se puede alinear: su elegibilidad quedó como no verificada."
            : "{$nombre} no se puede alinear.";
        $detalle = array_map(fn (array $motivo): string => $motivo['motivo'].' (regla: '.$motivo['regla'].')', $resultado['motivos']);

        throw ValidationException::withMessages([$campo => [$titulo.' '.implode(' ', $detalle)]]);
    }

    /**
     * @return list<string>
     */
    private function datosFaltantes(Jugador $jugador): array
    {
        $persona = $jugador->persona;
        $faltan = [];

        if ($persona === null) {
            return ['los datos de la persona'];
        }
        if ($persona->getAttribute('fecha_nacimiento') === null) {
            $faltan[] = 'la fecha de nacimiento';
        }
        if (blank($persona->curp)) {
            $faltan[] = 'la CURP';
        }
        if ($jugador->equipo?->grupo?->categoria === null) {
            $faltan[] = 'la categoría de su equipo';
        }

        return $faltan;
    }

    /**
     * @return list<array{motivo: string, regla: string}>
     */
    private function estadoDelPadron(Jugador $jugador): array
    {
        return match ($jugador->estado_elegibilidad) {
            'Suspendido' => [['motivo' => 'El jugador está suspendido en el padrón.', 'regla' => 'HU-018 · estado del jugador']],
            'Baja' => [['motivo' => 'El jugador está dado de baja.', 'regla' => 'HU-017 · baja del jugador']],
            default => [],
        };
    }

    /**
     * @return list<array{motivo: string, regla: string}>
     */
    private function sancionVigente(Jugador $jugador, ?Partido $partido): array
    {
        $sancion = $this->sanciones->obtenerSancionVigenteJugador($jugador, $partido?->fecha_partido?->toDateString());

        return $sancion === null ? [] : [[
            'motivo' => 'Tiene una sanción deportiva vigente ('.$sancion->explicacionRegla().').',
            'regla' => 'HU-047 · RF-10.4 sanción vigente',
        ]];
    }

    /**
     * @return list<array{motivo: string, regla: string}>
     */
    private function cuotaPendiente(Jugador $jugador): array
    {
        // datosFaltantes() ya confirmó que hay equipo con categoría
        $temporadaId = $jugador->equipo->temporada_id ?? $jugador->equipo->grupo?->temporada_id;

        $pendiente = CargoFinanciero::query()
            ->where('persona_id', $jugador->persona_id)
            ->where('concepto', 'Inscripcion')
            ->when($temporadaId !== null, fn ($consulta) => $consulta->where('temporada_id', $temporadaId))
            ->whereIn('estado', ['Pendiente', 'Vencido'])
            ->exists();

        return $pendiente ? [[
            'motivo' => 'Tiene la cuota de inscripción de la temporada pendiente de pago.',
            'regla' => 'RF-13.1 · HU-050/HU-051 cuota cubierta',
        ]] : [];
    }

    /**
     * @return list<array{motivo: string, regla: string}>
     */
    private function registroIncompleto(Jugador $jugador): array
    {
        $tipos = $jugador->persona?->documentos->pluck('tipo')->all() ?? [];
        $faltan = array_values(array_diff_key(self::DOCUMENTOS_OBLIGATORIOS, array_flip($tipos)));

        return $faltan === [] ? [] : [[
            'motivo' => 'Registro incompleto: falta '.implode(' y ', $faltan).'.',
            'regla' => 'HU-016 · documentos obligatorios del registro',
        ]];
    }

    /**
     * @return list<array{motivo: string, regla: string}>
     */
    private function categoriaNoPermitida(Jugador $jugador): array
    {
        $motivos = [];

        try {
            $this->reglaEdad->validarAlineacion($jugador);
        } catch (ValidationException $error) {
            $motivos[] = ['motivo' => (string) collect($error->errors())->flatten()->first(), 'regla' => 'HU-021 · edad de la categoría'];
        }

        $categoria = $jugador->equipo?->grupo?->categoria;
        $temporadaId = $categoria?->temporada_id;

        // Otra inscripción vigente de la persona en la misma temporada, en una categoría más de un nivel abajo
        $origen = Jugador::query()
            ->where('persona_id', $jugador->persona_id)
            ->whereKeyNot($jugador->getKey())
            ->where('estado_elegibilidad', '!=', 'Baja')
            ->whereHas('equipo.grupo.categoria', fn ($consulta) => $consulta
                ->where('temporada_id', $temporadaId)
                ->where('jerarquia_salto', '<', (int) $categoria?->jerarquia_salto - 1))
            ->with('equipo.grupo.categoria:id,nombre,jerarquia_salto')
            ->first();

        if ($origen !== null) {
            $motivos[] = [
                'motivo' => 'Juega en una categoría superior a la permitida: está inscrito en «'.$origen->equipo?->grupo?->categoria?->nombre.'» y solo puede subir una categoría.',
                'regla' => 'RF-04.4 · salto de una categoría',
            ];
        }

        return $motivos;
    }

    /**
     * @return array{estado: string, motivos: list<array{motivo: string, regla: string}>}
     */
    private function noVerificado(string $motivo): array
    {
        return ['estado' => self::NO_VERIFICADO, 'motivos' => [['motivo' => $motivo.' No se habilita hasta verificarla.', 'regla' => 'RNF-43 · no verificado']]];
    }
}
