<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\ConsentimientoPrivacidadJugador;
use App\Models\Persona;
use App\Models\Temporada;
use App\Services\ConsentimientoPrivacidadService;
use App\Services\ReglaEdadCategoriaService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Edades por rama y categoría según la Tabla 4 de la ERS (temporada 2026-2027).
 * No hay una edad mínima para toda la Liga: cada categoría define la suya.
 */
class CategoriasEdadErsTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @param array<string, int|null> $edades
     */
    #[DataProvider('casosTabla4')]
    public function test_la_regla_de_edad_respeta_la_tabla_4_de_la_ers(string $rama, string $categoria, array $edades, string $nacimiento, bool $admitido): void
    {
        $temporada = Temporada::factory()->activa()->create(['fecha_inicio' => '2026-08-01']);
        $cat = Categoria::factory()->create(['temporada_id' => $temporada->id, 'rama' => $rama, 'nombre' => $categoria] + $edades);
        $persona = Persona::factory()->create(['fecha_nacimiento' => $nacimiento]);

        try {
            app(ReglaEdadCategoriaService::class)->validar($persona, $cat, $temporada);
            $this->assertTrue($admitido, $categoria.': no debía admitir a alguien nacido el '.$nacimiento.'.');
        } catch (ValidationException) {
            $this->assertFalse($admitido, $categoria.': debía admitir a alguien nacido el '.$nacimiento.'.');
        }
    }

    /** @return array<string, array{string, string, array<string, int|null>, string, bool}> */
    public static function casosTabla4(): array
    {
        $libre = ['edad_min' => 18];
        $juvenil = ['anio_nacimiento_desde' => 2009, 'anio_nacimiento_hasta' => 2010];
        $veteranosLibre = ['anio_nacimiento_hasta' => 1990];
        $infantil = ['anio_nacimiento_desde' => 2013, 'anio_nacimiento_hasta' => 2014];
        $prejuvenil = ['anio_nacimiento_desde' => 2011, 'anio_nacimiento_hasta' => 2012];
        $miniponysEspeciales = ['anio_nacimiento_desde' => 2021];
        $femenilMenores = ['anio_nacimiento_desde' => 2012];

        return [
            'Libre: mayor de 18 sin edad máxima' => ['Libre', 'Primera Fuerza', $libre, '1980-03-10', true],
            'Libre: menor de 18 no entra' => ['Libre', 'Primera Fuerza', $libre, '2010-03-10', false],
            'Juvenil: nacido en 2009' => ['Libre', 'Juvenil', $juvenil, '2009-05-01', true],
            'Juvenil: nacido en 2010' => ['Libre', 'Juvenil', $juvenil, '2010-12-31', true],
            'Juvenil: nacido en 2008 no entra' => ['Libre', 'Juvenil', $juvenil, '2008-12-31', false],
            'Juvenil: nacido en 2011 no entra' => ['Libre', 'Juvenil', $juvenil, '2011-01-01', false],
            'Veteranos Libre: 1990 o antes' => ['Veteranos', 'Veteranos Libre', $veteranosLibre, '1985-07-20', true],
            'Veteranos Libre: después de 1990 no entra' => ['Veteranos', 'Veteranos Libre', $veteranosLibre, '1991-01-01', false],
            'Infantil: nacido en 2014' => ['Infantil', 'Infantil', $infantil, '2014-02-01', true],
            'Infantil: nacido en 2012 no entra' => ['Infantil', 'Infantil', $infantil, '2012-02-01', false],
            'Pre juvenil: nacido en 2011' => ['Infantil', 'Pre juvenil', $prejuvenil, '2011-09-15', true],
            'Miniponys Especiales: 2021 o menores' => ['Infantil', 'Miniponys Especiales', $miniponysEspeciales, '2022-04-01', true],
            'Miniponys Especiales: 2020 no entra' => ['Infantil', 'Miniponys Especiales', $miniponysEspeciales, '2020-04-01', false],
            'Femenil 2012 o menores: nacida en 2013' => ['Femenil', 'Femenil 2012 o menores', $femenilMenores, '2013-06-01', true],
        ];
    }

    public function test_los_menores_de_la_rama_infantil_y_de_juvenil_requieren_tutor_y_los_adultos_de_libre_no(): void
    {
        $temporada = Temporada::factory()->activa()->create(['fecha_inicio' => '2026-08-01']);
        $consentimiento = app(ConsentimientoPrivacidadService::class);
        $infantil = Categoria::factory()->create(['temporada_id' => $temporada->id, 'rama' => 'Infantil', 'nombre' => 'Infantil', 'anio_nacimiento_desde' => 2013, 'anio_nacimiento_hasta' => 2014]);
        $juvenil = Categoria::factory()->create(['temporada_id' => $temporada->id, 'rama' => 'Libre', 'nombre' => 'Juvenil', 'anio_nacimiento_desde' => 2009, 'anio_nacimiento_hasta' => 2010]);
        $libre = Categoria::factory()->create(['temporada_id' => $temporada->id, 'rama' => 'Libre', 'nombre' => 'Primera Fuerza', 'edad_min' => 18]);
        $femenil = Categoria::factory()->create(['temporada_id' => $temporada->id, 'rama' => 'Femenil', 'nombre' => 'Femenil 2012 o menores', 'anio_nacimiento_desde' => 2012]);

        $this->assertSame(ConsentimientoPrivacidadJugador::MOTIVO_RAMA_INFANTIL, $consentimiento->motivoTutor(Persona::factory()->create(['fecha_nacimiento' => '2014-02-01']), $infantil));
        $this->assertSame(ConsentimientoPrivacidadJugador::MOTIVO_CATEGORIA_JUVENIL, $consentimiento->motivoTutor(Persona::factory()->create(['fecha_nacimiento' => '2009-05-01']), $juvenil));
        $this->assertSame(ConsentimientoPrivacidadJugador::MOTIVO_MENOR_DE_EDAD, $consentimiento->motivoTutor(Persona::factory()->create(['fecha_nacimiento' => '2013-06-01']), $femenil));
        $this->assertNull($consentimiento->motivoTutor(Persona::factory()->create(['fecha_nacimiento' => '1990-01-01']), $libre));
    }

    public function test_las_categorias_de_ejemplo_siguen_la_tabla_4_de_la_ers(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['Primera Especial', 'Primera Fuerza'] as $nombre) {
            $categoria = Categoria::query()->where('nombre', $nombre)->where('rama', 'Libre')->latest('id')->firstOrFail();
            $this->assertSame(18, $categoria->edad_min, $nombre.': mayores de 18 años.');
            $this->assertNull($categoria->edad_max, $nombre.': la ERS no fija edad máxima.');
        }

        $juvenil = Categoria::query()->where('nombre', 'Juvenil A')->latest('id')->firstOrFail();
        $this->assertSame(2009, $juvenil->anio_nacimiento_desde);
        $this->assertSame(2010, $juvenil->anio_nacimiento_hasta);
        $this->assertNull($juvenil->edad_min);
        $this->assertNull($juvenil->edad_max);
    }
}
