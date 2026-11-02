<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClubRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $directivo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $club = Club::firstOrCreate(
            ['nombre' => 'Club Base Registro Test'],
            [
                'colores' => 'Verde y blanco',
                'estado' => 'activo',
                'fecha_afiliacion' => '2024-01-01',
            ]
        );

        $this->admin = User::create([
            'name' => 'Administrador Registro Test',
            'email' => 'admin-registro-test@example.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Administrador General',
            'club_id' => null,
        ]);

        $this->admin->assignRole('Administrador General');

        $this->directivo = User::create([
            'name' => 'Directivo Registro Test',
            'email' => 'directivo-registro-test@example.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Directivo Club',
            'club_id' => $club->id,
        ]);

        $this->directivo->assignRole('Presidente del Club');
    }

    public function test_administrador_general_puede_registrar_club_directiva_y_auditarlo(): void
    {
        $respuesta = $this->actingAs($this->admin)->postJson('/clubes', [
            'nombre' => 'Club Nuevo Registro Test',
            'colores' => 'Azul y oro',
            'motivo' => 'Alta inicial para pruebas',
            'directivos' => [
                [
                    'nombre' => 'Juan Perez',
                    'cargo' => 'Presidente',
                ],
                [
                    'nombre' => 'Maria Lopez',
                    'cargo' => 'Secretaria',
                ],
            ],
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath(
                'mensaje',
                'Club y directiva registrados exitosamente.'
            )
            ->assertJsonPath('club.nombre', 'Club Nuevo Registro Test')
            ->assertJsonPath('club.estado', 'activo')
            ->assertJsonCount(2, 'club.directivos');

        $clubId = $respuesta->json('club.id');

        $this->assertDatabaseHas('clubes', [
            'id' => $clubId,
            'nombre' => 'Club Nuevo Registro Test',
            'colores' => 'Azul y oro',
            'estado' => 'activo',
        ]);

        $this->assertDatabaseHas('directivos_club', [
            'club_id' => $clubId,
            'nombre' => 'Juan Perez',
            'cargo' => 'Presidente',
        ]);

        $this->assertDatabaseHas('directivos_club', [
            'club_id' => $clubId,
            'nombre' => 'Maria Lopez',
            'cargo' => 'Secretaria',
        ]);

        $this->assertDatabaseHas('auditoria_logs', [
            'accion' => 'club_creado',
            'tabla_afectada' => 'clubes',
            'registro_id' => $clubId,
            'motivo' => 'Alta inicial para pruebas',
        ]);
    }

    public function test_directivo_no_puede_registrar_clubes(): void
    {
        $respuesta = $this->actingAs($this->directivo)->postJson('/clubes', [
            'nombre' => 'Club No Autorizado Test',
            'colores' => 'Rojo y negro',
            'directivos' => [
                [
                    'nombre' => 'Persona de prueba',
                    'cargo' => 'Presidente',
                ],
            ],
        ]);

        $respuesta->assertForbidden();

        $this->assertDatabaseMissing('clubes', [
            'nombre' => 'Club No Autorizado Test',
        ]);
    }

    public function test_registro_rechaza_nombre_duplicado(): void
    {
        Club::create([
            'nombre' => 'Club Duplicado Registro Test',
            'colores' => 'Negro',
            'fecha_afiliacion' => '2025-01-01',
            'estado' => 'activo',
        ]);

        $respuesta = $this->actingAs($this->admin)->postJson('/clubes', [
            'nombre' => 'Club Duplicado Registro Test',
            'directivos' => [
                [
                    'nombre' => 'Persona de prueba',
                    'cargo' => 'Presidente',
                ],
            ],
        ]);

        $respuesta->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre']);
    }

    public function test_administrador_puede_asignar_propietario_al_registrar_club(): void
    {
        // Crear usuario propietario
        $propietario = User::create([
            'name' => 'Propietario Club Test',
            'email' => 'propietario-club-test@example.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Consultor',
            'club_id' => null,
        ]);

        // Registrar club con propietario
        $respuesta = $this->actingAs($this->admin)->postJson('/clubes', [
            'nombre' => 'Club Propietario Test',
            'colores' => 'Verde y blanco',
            'propietario_usuario_id' => $propietario->id,
            'motivo' => 'Prueba de asignación de propietario',
            'directivos' => [
                [
                    'nombre' => 'Directivo Propietario Test',
                    'cargo' => 'Presidente',
                ],
            ],
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('club.nombre', 'Club Propietario Test')
            ->assertJsonPath(
                'club.propietario_usuario_id',
                $propietario->id
            )
            ->assertJsonPath(
                'club.propietario.id',
                $propietario->id
            );

        $clubId = $respuesta->json('club.id');

        // Comprobar que el propietario se guardó
        $this->assertDatabaseHas('clubes', [
            'id' => $clubId,
            'propietario_usuario_id' => $propietario->id,
        ]);

        // Comprobar la relación entre club y propietario
        $club = Club::with('propietario')->findOrFail($clubId);

        $this->assertNotNull($club->propietario);

        $this->assertEquals(
            $propietario->id,
            $club->propietario->id
        );
    }
}
