<?php

namespace Tests\Feature;

use App\Models\CedulaArbitral;
use App\Models\Partido;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

// HU-036, corrección del #78: datos sembrados para la demo de la cédula
class CedulaSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_la_cedula_sembrada_con_las_dos_firmas_queda_cerrada_y_el_arbitro_entra_a_su_partido(): void
    {
        $this->seed(DatabaseSeeder::class);

        $cedula = CedulaArbitral::query()->whereNotNull('firma_capitan_local')->whereNotNull('firma_capitan_visitante')->sole();
        $this->assertSame(CedulaArbitral::CERRADA, $cedula->estado);

        // La cuenta del árbitro está enlazada a su persona y abre la cédula del partido programado
        $arbitro = User::query()->where('email', 'arbitro@ligafutbol.com')->sole();
        $programado = Partido::query()->where('estado', 'programado')->whereHas('arbitro', fn ($consulta) => $consulta->where('persona_id', $arbitro->persona_id))->firstOrFail();
        $this->assertNotNull($arbitro->persona_id);

        $this->actingAs($arbitro)->get(route('cedulas.mis-partidos'))->assertOk()->assertSee(route('cedulas.show', $programado));
        $this->actingAs($arbitro)->post(route('cedulas.abrir', $programado))->assertSessionHasNoErrors();
        $this->assertTrue(CedulaArbitral::query()->where('partido_id', $programado->id)->exists());
    }
}
