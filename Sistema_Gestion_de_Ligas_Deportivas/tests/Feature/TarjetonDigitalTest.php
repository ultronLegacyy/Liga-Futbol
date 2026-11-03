<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Club;
use App\Models\DirectivoClub;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\TarjetonDigital;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TarjetonDigitalTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_presidente_genera_tarjeton_con_club_directiva_firmas_y_equipos_de_la_temporada(): void
    {
        $club = $this->crearClub('Club Tarjetón');
        $temporada = Temporada::factory()->activa()->create();
        $categoria = Categoria::factory()->create(['temporada_id' => $temporada->id]);
        $grupo = Grupo::factory()->create([
            'categoria_id' => $categoria->id,
            'temporada_id' => $temporada->id,
        ]);
        DirectivoClub::create([
            'club_id' => $club->id,
            'nombre' => 'Ana Directiva',
            'cargo' => 'Presidenta',
            'firma_digital_url' => 'firmas/ana.png',
        ]);
        $equipo = Equipo::create([
            'club_id' => $club->id,
            'grupo_id' => $grupo->id,
            'nombre' => 'Halcones',
        ]);

        $this->actingAs($this->crearPresidente($club))
            ->postJson($this->rutaTarjetones($club, $temporada))
            ->assertCreated()
            ->assertJsonPath('tarjeton.version', 1)
            ->assertJsonPath('tarjeton.contenido.club.nombre', 'Club Tarjetón')
            ->assertJsonPath('tarjeton.contenido.directiva.0.firma_digital_url', 'firmas/ana.png')
            ->assertJsonPath('tarjeton.contenido.equipos.0.id', $equipo->id)
            ->assertJsonPath('tarjeton.contenido.equipos.0.categoria.nombre', $categoria->nombre)
            ->assertJsonPath('tarjeton.contenido.equipos.0.grupo.nombre', $grupo->nombre);

        $this->assertDatabaseHas('tarjetones_digitales', [
            'club_id' => $club->id,
            'temporada_id' => $temporada->id,
            'version' => 1,
        ]);
    }

    public function test_tarjeton_se_consulta_por_temporada_y_se_puede_recuperar_una_version(): void
    {
        $club = $this->crearClub('Club Consultable');
        $temporada = Temporada::factory()->activa()->create();
        $presidente = $this->crearPresidente($club);

        $this->actingAs($presidente)->postJson($this->rutaTarjetones($club, $temporada))->assertCreated();

        $this->getJson($this->rutaTarjetones($club, $temporada))
            ->assertOk()
            ->assertJsonPath('temporada_id', $temporada->id)
            ->assertJsonCount(1, 'tarjetones')
            ->assertJsonPath('tarjetones.0.version', 1);

        $this->getJson($this->rutaTarjetones($club, $temporada).'/1')
            ->assertOk()
            ->assertJsonPath('tarjeton.version', 1)
            ->assertJsonPath('tarjeton.contenido.club.nombre', 'Club Consultable');
    }

    public function test_cambios_en_club_y_directiva_crean_versiones_nuevas_y_conservan_las_anteriores(): void
    {
        $club = $this->crearClub('Nombre Inicial');
        $temporada = Temporada::factory()->activa()->create();
        $directivo = DirectivoClub::create([
            'club_id' => $club->id,
            'nombre' => 'Directivo Inicial',
            'cargo' => 'Secretario',
            'firma_digital_url' => 'firmas/inicial.png',
        ]);
        $presidente = $this->crearPresidente($club);

        $this->actingAs($presidente)->postJson($this->rutaTarjetones($club, $temporada))->assertCreated();

        $club->update(['nombre' => 'Nombre Actualizado']);
        $directivo->update(['firma_digital_url' => 'firmas/actualizada.png']);

        $this->assertSame(3, TarjetonDigital::query()
            ->where('club_id', $club->id)
            ->where('temporada_id', $temporada->id)
            ->count());
        $this->assertDatabaseHas('tarjetones_digitales', [
            'club_id' => $club->id,
            'temporada_id' => $temporada->id,
            'version' => 1,
        ]);

        $this->getJson($this->rutaTarjetones($club, $temporada).'/1')
            ->assertOk()
            ->assertJsonPath('tarjeton.contenido.club.nombre', 'Nombre Inicial')
            ->assertJsonPath('tarjeton.contenido.directiva.0.firma_digital_url', 'firmas/inicial.png');

        $this->getJson($this->rutaTarjetones($club, $temporada).'/3')
            ->assertOk()
            ->assertJsonPath('tarjeton.contenido.club.nombre', 'Nombre Actualizado')
            ->assertJsonPath('tarjeton.contenido.directiva.0.firma_digital_url', 'firmas/actualizada.png');
    }

    public function test_no_presidente_recibe_403_al_generar_tarjeton(): void
    {
        $club = $this->crearClub('Club Protegido');
        $temporada = Temporada::factory()->activa()->create();
        $usuario = User::create([
            'name' => 'Delegado',
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'rol' => 'Directivo Club',
            'estatus' => 'activo',
            'club_id' => $club->id,
        ]);
        $usuario->assignRole('Delegado del Club');

        $this->actingAs($usuario)
            ->postJson($this->rutaTarjetones($club, $temporada))
            ->assertForbidden();

        $this->assertSame(0, TarjetonDigital::query()->count());
    }

    public function test_no_permite_generar_tarjeton_para_una_temporada_cerrada(): void
    {
        $club = $this->crearClub('Club de Temporada Cerrada');
        $temporada = Temporada::factory()->cerrada()->create();

        $this->actingAs($this->crearPresidente($club))
            ->postJson($this->rutaTarjetones($club, $temporada))
            ->assertForbidden();

        $this->assertSame(0, TarjetonDigital::query()->count());
    }

    private function crearClub(string $nombre): Club
    {
        return Club::create(['nombre' => $nombre, 'estado' => 'activo']);
    }

    private function crearPresidente(Club $club): User
    {
        $usuario = User::create([
            'name' => 'Presidente de '.$club->nombre,
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'rol' => 'Directivo Club',
            'estatus' => 'activo',
            'club_id' => $club->id,
        ]);
        $usuario->assignRole('Presidente del Club');

        return $usuario;
    }

    private function rutaTarjetones(Club $club, Temporada $temporada): string
    {
        return route('clubes.temporadas.tarjetones.index', [$club, $temporada]);
    }
}
