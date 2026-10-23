<?php

namespace Tests\Feature;

use App\Models\BloqueoCancha;
use App\Models\Cancha;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// SCRUM-141 (S2-T28): Pruebas de disponibilidad y bloqueos de canchas (HU-024, RF-05.2)
class CanchaBloqueoTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $secretarioSoloLectura;

    private User $tesoreroSinAcceso;

    private Cancha $canchaPrincipal;

    private Cancha $canchaSecundaria;

    private Cancha $canchaInactiva;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        // Administrador General: posee campos.ver y campos.bloquear
        $this->admin = $this->crearUsuario('admin_test_bloqueo@liga.com', 'Administrador General');

        // Secretario: posee campos.ver pero NO campos.bloquear (solo consulta)
        $this->secretarioSoloLectura = $this->crearUsuario('secretario_test_bloqueo@liga.com', 'Secretario');

        // Tesorero: aislado de canchas (sin campos.ver ni campos.bloquear)
        $this->tesoreroSinAcceso = $this->crearUsuario('tesorero_test_bloqueo@liga.com', 'Tesorero');

        $this->canchaPrincipal = Cancha::create([
            'nombre' => 'Cancha 12 U.D.C.',
            'direccion_ubicacion' => 'Unidad Deportiva Cuauhtemoc, Morelia',
            'capacidad_partidos_consecutivos' => 2,
            'estado' => 'Disponible',
        ]);

        $this->canchaSecundaria = Cancha::create([
            'nombre' => 'Cancha 14 U.D.C.',
            'direccion_ubicacion' => 'Unidad Deportiva Cuauhtemoc, Morelia',
            'capacidad_partidos_consecutivos' => 2,
            'estado' => 'Disponible',
        ]);

        $this->canchaInactiva = Cancha::create([
            'nombre' => 'Cancha 3 Clausurada',
            'direccion_ubicacion' => 'Instalaciones Privadas',
            'capacidad_partidos_consecutivos' => 1,
            'estado' => 'Inactiva',
        ]);
    }

    // =========================================================================
    // BLOQUE 1: Criterio de Aceptacion 1 (CA1) - Disponibilidad por fecha y hora
    // =========================================================================

    public function test_cancha_activa_sin_bloqueos_esta_disponible_en_cualquier_horario(): void
    {
        $inicio = Carbon::now()->addDays(2)->setTime(8, 0);
        $fin = Carbon::now()->addDays(2)->setTime(12, 0);

        $this->assertTrue(
            $this->canchaPrincipal->estaDisponible($inicio, $fin)
        );

        $this->assertTrue(
            $this->canchaPrincipal->estaDisponible(
                $inicio->toDateTimeString(),
                $fin->toDateTimeString()
            )
        );
    }

    public function test_metodo_esta_disponible_admite_sobrecarga_de_tres_parametros_fecha_y_horas(): void
    {
        $fecha = Carbon::now()->addDays(3)->format('Y-m-d');

        $this->assertTrue(
            $this->canchaPrincipal->estaDisponible($fecha, '09:00:00', '13:00:00')
        );
    }

    public function test_metodo_esta_disponible_admite_instancias_carbon(): void
    {
        $inicio = Carbon::now()->addDays(4)->setTime(14, 0);
        $fin = Carbon::now()->addDays(4)->setTime(18, 0);

        $this->assertTrue(
            $this->canchaPrincipal->estaDisponible($inicio, $fin)
        );
    }

    public function test_scope_disponibles_en_retorna_unicamente_canchas_libres_y_activas(): void
    {
        $inicio = Carbon::now()->addDays(5)->setTime(10, 0);
        $fin = Carbon::now()->addDays(5)->setTime(14, 0);

        // Bloqueamos la cancha secundaria
        BloqueoCancha::create([
            'cancha_id' => $this->canchaSecundaria->id,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'motivo' => 'Mantenimiento en cancha secundaria',
        ]);

        $canchasDisponibles = Cancha::disponiblesEn($inicio, $fin)->pluck('id');

        // La principal debe estar disponible
        $this->assertTrue($canchasDisponibles->contains($this->canchaPrincipal->id));

        // La secundaria bloqueada NO debe aparecer
        $this->assertFalse($canchasDisponibles->contains($this->canchaSecundaria->id));

        // La inactiva nunca debe aparecer
        $this->assertFalse($canchasDisponibles->contains($this->canchaInactiva->id));
    }

    // =========================================================================
    // BLOQUE 2: Criterio de Aceptacion 2 (CA2) - Exclusion de canchas bloqueadas
    // =========================================================================

    public function test_cancha_con_bloqueo_no_esta_disponible_en_el_intervalo_del_bloqueo(): void
    {
        $inicio = Carbon::now()->addDays(1)->setTime(10, 0);
        $fin = Carbon::now()->addDays(1)->setTime(14, 0);

        BloqueoCancha::create([
            'cancha_id' => $this->canchaPrincipal->id,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'motivo' => 'Lluvias torrenciales y anegamiento',
        ]);

        $this->assertFalse(
            $this->canchaPrincipal->estaDisponible($inicio, $fin)
        );

        $this->assertFalse(
            $this->canchaPrincipal->estaDisponible(
                Carbon::now()->addDays(1)->setTime(11, 0),
                Carbon::now()->addDays(1)->setTime(13, 0)
            )
        );
    }

    public function test_cancha_inactiva_nunca_esta_disponible_para_programacion_deportiva(): void
    {
        $inicio = Carbon::now()->addDays(2)->setTime(8, 0);
        $fin = Carbon::now()->addDays(2)->setTime(12, 0);

        $this->assertFalse(
            $this->canchaInactiva->estaDisponible($inicio, $fin)
        );
    }

    public function test_bloqueo_en_una_cancha_no_afecta_la_disponibilidad_de_otra_cancha_en_el_mismo_horario(): void
    {
        $inicio = Carbon::now()->addDays(2)->setTime(8, 0);
        $fin = Carbon::now()->addDays(2)->setTime(12, 0);

        BloqueoCancha::create([
            'cancha_id' => $this->canchaPrincipal->id,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'motivo' => 'Reparacion de cesped',
        ]);

        $this->assertFalse($this->canchaPrincipal->estaDisponible($inicio, $fin));
        $this->assertTrue($this->canchaSecundaria->estaDisponible($inicio, $fin));
    }

    public function test_canchas_bloqueadas_son_excluidas_del_scope_disponibles_en(): void
    {
        $inicio = Carbon::now()->addDays(6)->setTime(9, 0);
        $fin = Carbon::now()->addDays(6)->setTime(13, 0);

        BloqueoCancha::create([
            'cancha_id' => $this->canchaPrincipal->id,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'motivo' => 'Pintura y demarcacion de lineas',
        ]);

        $disponibles = Cancha::disponiblesEn($inicio, $fin)->get();

        $this->assertFalse($disponibles->contains('id', $this->canchaPrincipal->id));
        $this->assertTrue($disponibles->contains('id', $this->canchaSecundaria->id));
    }

    // =========================================================================
    // BLOQUE 3: Criterio de Aceptacion 3 (CA3) - Rechazo estricto de traslapes
    // =========================================================================

    public function test_rechaza_traslape_cuando_nuevo_bloqueo_esta_contenido_en_uno_existente(): void
    {
        $inicioExistente = Carbon::now()->addDays(3)->setTime(8, 0);
        $finExistente = Carbon::now()->addDays(3)->setTime(16, 0);

        BloqueoCancha::create([
            'cancha_id' => $this->canchaPrincipal->id,
            'fecha_inicio' => $inicioExistente,
            'fecha_fin' => $finExistente,
            'motivo' => 'Bloqueo general del dia',
        ]);

        $respuesta = $this->actingAs($this->admin)
            ->postJson(route('canchas.bloqueos.store', $this->canchaPrincipal), [
                'fecha_inicio' => Carbon::now()->addDays(3)->setTime(10, 0)->toDateTimeString(),
                'fecha_fin' => Carbon::now()->addDays(3)->setTime(12, 0)->toDateTimeString(),
                'motivo' => 'Intento de bloqueo interno',
            ]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonValidationErrors(['fecha_inicio']);
        $respuesta->assertJsonFragment([
            'fecha_inicio' => ['La cancha ya cuenta con un bloqueo registrado que se traslapa con el horario seleccionado.'],
        ]);
    }

    public function test_rechaza_traslape_cuando_nuevo_bloqueo_inicia_antes_y_termina_dentro_del_existente(): void
    {
        $inicioExistente = Carbon::now()->addDays(4)->setTime(10, 0);
        $finExistente = Carbon::now()->addDays(4)->setTime(14, 0);

        BloqueoCancha::create([
            'cancha_id' => $this->canchaPrincipal->id,
            'fecha_inicio' => $inicioExistente,
            'fecha_fin' => $finExistente,
            'motivo' => 'Bloqueo previo',
        ]);

        $respuesta = $this->actingAs($this->admin)
            ->postJson(route('canchas.bloqueos.store', $this->canchaPrincipal), [
                'fecha_inicio' => Carbon::now()->addDays(4)->setTime(8, 0)->toDateTimeString(),
                'fecha_fin' => Carbon::now()->addDays(4)->setTime(11, 0)->toDateTimeString(),
                'motivo' => 'Intento de solapamiento frontal',
            ]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonValidationErrors(['fecha_inicio']);
    }

    public function test_rechaza_traslape_cuando_nuevo_bloqueo_inicia_dentro_y_termina_despues_del_existente(): void
    {
        $inicioExistente = Carbon::now()->addDays(5)->setTime(10, 0);
        $finExistente = Carbon::now()->addDays(5)->setTime(14, 0);

        BloqueoCancha::create([
            'cancha_id' => $this->canchaPrincipal->id,
            'fecha_inicio' => $inicioExistente,
            'fecha_fin' => $finExistente,
            'motivo' => 'Bloqueo existente',
        ]);

        $respuesta = $this->actingAs($this->admin)
            ->postJson(route('canchas.bloqueos.store', $this->canchaPrincipal), [
                'fecha_inicio' => Carbon::now()->addDays(5)->setTime(13, 0)->toDateTimeString(),
                'fecha_fin' => Carbon::now()->addDays(5)->setTime(16, 0)->toDateTimeString(),
                'motivo' => 'Intento de solapamiento posterior',
            ]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonValidationErrors(['fecha_inicio']);
    }

    public function test_rechaza_traslape_cuando_nuevo_bloqueo_envuelve_completamente_al_existente(): void
    {
        $inicioExistente = Carbon::now()->addDays(6)->setTime(11, 0);
        $finExistente = Carbon::now()->addDays(6)->setTime(13, 0);

        BloqueoCancha::create([
            'cancha_id' => $this->canchaPrincipal->id,
            'fecha_inicio' => $inicioExistente,
            'fecha_fin' => $finExistente,
            'motivo' => 'Bloqueo corto previo',
        ]);

        $respuesta = $this->actingAs($this->admin)
            ->postJson(route('canchas.bloqueos.store', $this->canchaPrincipal), [
                'fecha_inicio' => Carbon::now()->addDays(6)->setTime(9, 0)->toDateTimeString(),
                'fecha_fin' => Carbon::now()->addDays(6)->setTime(15, 0)->toDateTimeString(),
                'motivo' => 'Intento de solapamiento envolvente',
            ]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonValidationErrors(['fecha_inicio']);
    }

    public function test_permite_bloqueos_contiguos_donde_termino_de_uno_coincide_con_inicio_del_siguiente(): void
    {
        $puntoIntermedio = Carbon::now()->addDays(7)->setTime(12, 0);

        BloqueoCancha::create([
            'cancha_id' => $this->canchaPrincipal->id,
            'fecha_inicio' => Carbon::now()->addDays(7)->setTime(8, 0),
            'fecha_fin' => $puntoIntermedio,
            'motivo' => 'Primer turno matutino',
        ]);

        // El segundo inicia exactamente cuando el primero termina: NO es solape
        $respuesta = $this->actingAs($this->admin)
            ->postJson(route('canchas.bloqueos.store', $this->canchaPrincipal), [
                'fecha_inicio' => $puntoIntermedio->toDateTimeString(),
                'fecha_fin' => Carbon::now()->addDays(7)->setTime(16, 0)->toDateTimeString(),
                'motivo' => 'Segundo turno vespertino',
            ]);

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('bloqueos_cancha', [
            'cancha_id' => $this->canchaPrincipal->id,
            'motivo' => 'Segundo turno vespertino',
        ]);
    }

    public function test_validacion_de_formulario_exige_fecha_fin_posterior_a_fecha_inicio(): void
    {
        $fecha = Carbon::now()->addDays(8);

        $respuesta = $this->actingAs($this->admin)
            ->postJson(route('canchas.bloqueos.store', $this->canchaPrincipal), [
                'fecha_inicio' => $fecha->copy()->setTime(14, 0)->toDateTimeString(),
                'fecha_fin' => $fecha->copy()->setTime(10, 0)->toDateTimeString(),
                'motivo' => 'Horario invertido no permitido',
            ]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonValidationErrors(['fecha_fin']);
    }

    public function test_validacion_de_formulario_exige_motivo_minimo_tres_caracteres_y_maximo_255(): void
    {
        $inicio = Carbon::now()->addDays(9)->setTime(8, 0)->toDateTimeString();
        $fin = Carbon::now()->addDays(9)->setTime(12, 0)->toDateTimeString();

        // Motivo demasiado corto
        $resCorto = $this->actingAs($this->admin)
            ->postJson(route('canchas.bloqueos.store', $this->canchaPrincipal), [
                'fecha_inicio' => $inicio,
                'fecha_fin' => $fin,
                'motivo' => 'Ok',
            ]);
        $resCorto->assertStatus(422);
        $resCorto->assertJsonValidationErrors(['motivo']);

        // Motivo vacio
        $resVacio = $this->actingAs($this->admin)
            ->postJson(route('canchas.bloqueos.store', $this->canchaPrincipal), [
                'fecha_inicio' => $inicio,
                'fecha_fin' => $fin,
                'motivo' => '',
            ]);
        $resVacio->assertStatus(422);
        $resVacio->assertJsonValidationErrors(['motivo']);

        // Motivo mayor a 255 caracteres
        $resLargo = $this->actingAs($this->admin)
            ->postJson(route('canchas.bloqueos.store', $this->canchaPrincipal), [
                'fecha_inicio' => $inicio,
                'fecha_fin' => $fin,
                'motivo' => str_repeat('A', 256),
            ]);
        $resLargo->assertStatus(422);
        $resLargo->assertJsonValidationErrors(['motivo']);
    }

    // =========================================================================
    // BLOQUE 4: Criterio de Aceptacion 4 (CA4) - Auditoria inmutable
    // =========================================================================

    public function test_alta_de_bloqueo_crea_registro_en_auditoria_logs_con_usuario_fecha_y_motivo(): void
    {
        $motivo = 'Mantenimiento de drenaje pluvial';

        $respuesta = $this->actingAs($this->admin)
            ->postJson(route('canchas.bloqueos.store', $this->canchaPrincipal), [
                'fecha_inicio' => Carbon::now()->addDays(10)->setTime(8, 0)->toDateTimeString(),
                'fecha_fin' => Carbon::now()->addDays(10)->setTime(12, 0)->toDateTimeString(),
                'motivo' => $motivo,
            ]);

        $respuesta->assertStatus(201);

        $bloqueoId = (int) $respuesta->json('bloqueo.id');

        $this->assertDatabaseHas('auditoria_logs', [
            'usuario_id' => $this->admin->id,
            'accion' => 'crear',
            'tabla_afectada' => 'bloqueos_cancha',
            'registro_id' => $bloqueoId,
            'motivo' => $motivo,
        ]);
    }

    public function test_cancelacion_de_bloqueo_elimina_registro_y_crea_auditoria_con_motivo_justificado(): void
    {
        $bloqueo = BloqueoCancha::create([
            'cancha_id' => $this->canchaPrincipal->id,
            'fecha_inicio' => Carbon::now()->addDays(11)->setTime(8, 0),
            'fecha_fin' => Carbon::now()->addDays(11)->setTime(14, 0),
            'motivo' => 'Reparacion preventiva de luminarias',
        ]);

        $motivoLiberacion = 'Se reprogramo el mantenimiento de luminarias para el mes proximo';

        $respuesta = $this->actingAs($this->admin)
            ->deleteJson(route('canchas.bloqueos.destroy', [$this->canchaPrincipal, $bloqueo]), [
                'motivo' => $motivoLiberacion,
            ]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseMissing('bloqueos_cancha', ['id' => $bloqueo->id]);

        $this->assertDatabaseHas('auditoria_logs', [
            'usuario_id' => $this->admin->id,
            'accion' => 'eliminar',
            'tabla_afectada' => 'bloqueos_cancha',
            'registro_id' => $bloqueo->id,
            'motivo' => $motivoLiberacion,
        ]);
    }

    // =========================================================================
    // BLOQUE 5: Autorizacion y Seguridad RBAC (HU-071 & RF-05.2)
    // =========================================================================

    public function test_usuario_no_autenticado_es_rechazado_con_redireccion_al_login(): void
    {
        $respuesta = $this->get(route('canchas.bloqueos.index', $this->canchaPrincipal));
        $respuesta->assertRedirect(route('login'));

        $resPost = $this->post(route('canchas.bloqueos.store', $this->canchaPrincipal), [
            'fecha_inicio' => Carbon::now()->addDay()->setTime(8, 0)->toDateTimeString(),
            'fecha_fin' => Carbon::now()->addDay()->setTime(12, 0)->toDateTimeString(),
            'motivo' => 'Intento anonimo',
        ]);
        $resPost->assertRedirect(route('login'));
    }

    public function test_usuario_tesorero_sin_permisos_recibe_403_en_todas_las_operaciones_de_canchas(): void
    {
        // 1. Consulta de listado
        $resIndex = $this->actingAs($this->tesoreroSinAcceso)
            ->get(route('canchas.bloqueos.index', $this->canchaPrincipal));
        $resIndex->assertStatus(403);

        // 2. Intento de crear bloqueo
        $resStore = $this->actingAs($this->tesoreroSinAcceso)
            ->post(route('canchas.bloqueos.store', $this->canchaPrincipal), [
                'fecha_inicio' => Carbon::now()->addDays(2)->setTime(8, 0)->toDateTimeString(),
                'fecha_fin' => Carbon::now()->addDays(2)->setTime(12, 0)->toDateTimeString(),
                'motivo' => 'Intento no autorizado de tesorero',
            ]);
        $resStore->assertStatus(403);

        // 3. Consulta de disponibilidad
        $resDisp = $this->actingAs($this->tesoreroSinAcceso)
            ->getJson(route('canchas.disponibilidad', [
                'cancha' => $this->canchaPrincipal,
                'fecha_inicio' => Carbon::now()->addDays(2)->setTime(8, 0)->toDateTimeString(),
                'fecha_fin' => Carbon::now()->addDays(2)->setTime(12, 0)->toDateTimeString(),
            ]));
        $resDisp->assertStatus(403);
    }

    public function test_usuario_secretario_con_solo_lectura_puede_consultar_pero_recibe_403_al_intentar_bloquear(): void
    {
        // El secretario puede consultar
        $resIndex = $this->actingAs($this->secretarioSoloLectura)
            ->get(route('canchas.bloqueos.index', $this->canchaPrincipal));
        $resIndex->assertStatus(200);

        // Pero recibe 403 al intentar registrar un bloqueo
        $resStore = $this->actingAs($this->secretarioSoloLectura)
            ->post(route('canchas.bloqueos.store', $this->canchaPrincipal), [
                'fecha_inicio' => Carbon::now()->addDays(3)->setTime(8, 0)->toDateTimeString(),
                'fecha_fin' => Carbon::now()->addDays(3)->setTime(12, 0)->toDateTimeString(),
                'motivo' => 'Intento no autorizado de creacion',
            ]);
        $resStore->assertStatus(403);
    }

    public function test_usuario_secretario_recibe_403_al_intentar_liberar_bloqueo(): void
    {
        $bloqueo = BloqueoCancha::create([
            'cancha_id' => $this->canchaPrincipal->id,
            'fecha_inicio' => Carbon::now()->addDays(3)->setTime(8, 0),
            'fecha_fin' => Carbon::now()->addDays(3)->setTime(12, 0),
            'motivo' => 'Bloqueo para prueba de borrado',
        ]);

        $resDelete = $this->actingAs($this->secretarioSoloLectura)
            ->delete(route('canchas.bloqueos.destroy', [$this->canchaPrincipal, $bloqueo]), [
                'motivo' => 'Intento no autorizado de liberacion',
            ]);

        $resDelete->assertStatus(403);
    }

    public function test_intento_de_cancelar_bloqueo_con_cancha_incorrecta_responde_404(): void
    {
        // Bloqueo perteneciente a cancha principal
        $bloqueo = BloqueoCancha::create([
            'cancha_id' => $this->canchaPrincipal->id,
            'fecha_inicio' => Carbon::now()->addDays(3)->setTime(8, 0),
            'fecha_fin' => Carbon::now()->addDays(3)->setTime(12, 0),
            'motivo' => 'Bloqueo en cancha principal',
        ]);

        // Intentamos cancelarlo usando la ruta de la cancha secundaria
        $respuesta = $this->actingAs($this->admin)
            ->deleteJson(route('canchas.bloqueos.destroy', [$this->canchaSecundaria, $bloqueo]), [
                'motivo' => 'Cruce indebido de cancha',
            ]);

        $respuesta->assertStatus(404);
    }

    // =========================================================================
    // BLOQUE 6: Endpoints y Validaciones Preventivas
    // =========================================================================

    public function test_endpoint_disponibilidad_con_parametros_fecha_hora_inicio_hora_fin(): void
    {
        $fecha = Carbon::now()->addDays(4)->format('Y-m-d');

        $respuesta = $this->actingAs($this->admin)
            ->getJson(route('canchas.disponibilidad', [
                'cancha' => $this->canchaPrincipal,
                'fecha' => $fecha,
                'hora_inicio' => '10:00:00',
                'hora_fin' => '12:00:00',
            ]));

        $respuesta->assertStatus(200);
        $respuesta->assertJson([
            'cancha_id' => $this->canchaPrincipal->id,
            'nombre' => $this->canchaPrincipal->nombre,
            'disponible' => true,
        ]);
    }

    public function test_endpoint_disponibilidad_valida_parametros_obligatorios_con_422(): void
    {
        $respuesta = $this->actingAs($this->admin)
            ->getJson(route('canchas.disponibilidad', [
                'cancha' => $this->canchaPrincipal,
                // No se envian parametros requeridos
            ]));

        $respuesta->assertStatus(422);
        $respuesta->assertJsonValidationErrors(['fecha_inicio', 'fecha_fin']);
    }

    public function test_factory_de_bloqueos_cancha_genera_registros_consistentes(): void
    {
        $bloqueo = BloqueoCancha::factory()->create([
            'cancha_id' => $this->canchaPrincipal->id,
            'motivo' => 'Generado por factory para pruebas',
        ]);

        $this->assertInstanceOf(BloqueoCancha::class, $bloqueo);
        $this->assertSame($this->canchaPrincipal->id, $bloqueo->cancha_id);
        $this->assertTrue($bloqueo->fecha_fin?->greaterThan($bloqueo->fecha_inicio));
        $this->assertSame('Generado por factory para pruebas', $bloqueo->motivo);
    }

    private function crearUsuario(string $email, string $rol): User
    {
        $usuario = User::create([
            'name' => 'Usuario Test Cancha Bloqueo',
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => $rol,
        ]);

        $usuario->assignRole($rol);

        return $usuario;
    }
}
