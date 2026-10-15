1. Core, Gobernanza y Portal Público
•	ligas: id (PK), nombre, logo_url, estado.
•	usuarios: id (PK), email, password_hash, rol (ENUM), club_id (FK_null).
•	auditoria_logs: id (PK), usuario_id (FK), accion, tabla_afectada, registro_id, valores_anteriores (JSONB), valores_nuevos (JSONB), ip, created_at.
•	asambleas: id (PK), fecha, acta_pdf_url, acuerdos (TEXT).
•	comunicados_noticias: id (PK), liga_id (FK), titulo, contenido (TEXT), estado (Borrador/Publicado), publicado_en.
•	registro_notificaciones: id (PK), destinatario_email, asunto, tipo_aviso, estado (Enviado/Fallido), fecha_envio. (Cumple el RF-15.4).

2. Estructura Deportiva
•	temporadas: id (PK), liga_id (FK), nombre, fecha_inicio, fecha_fin, estado.
•	convocatorias: id (PK), temporada_id (FK), archivo_pdf, reglas_versionadas (JSONB).
•	categorias: id (PK), nombre, edad_min, edad_max, jerarquia_salto (INT).
•	grupos: id (PK), categoria_id (FK), temporada_id (FK), nombre.
•	fases_torneo: id (PK), temporada_id (FK), tipo (Regular, Liguilla, Consolacion, CampeonDeCampeones).

3. Entidades Participantes y Documentos
•	clubes: id (PK), nombre, logo, colores, fecha_afiliacion, estado.
•	directivos_club: id (PK), club_id (FK), nombre, cargo, firma_digital_url.
•	historial_ventas_club: id (PK), club_id (FK), propietario_anterior, propietario_nuevo, carta_venta_pdf, fecha_venta.
•	equipos: id (PK), club_id (FK), grupo_id (FK), nombre, partidos_default_acumulados (INT, default 0).
•	personas: id (PK), nombre, apellidos, fecha_nacimiento, curp.
•	documentos_persona: id (PK), persona_id (FK), tipo (INE/CURP/Foto), ruta_storage, validado_en.
•	jugadores_equipos (Roster): id (PK), persona_id (FK), equipo_id (FK), dorsal, estado_elegibilidad (Activo, Inelegible, Suspendido).
•	arbitros: id (PK), persona_id (UNIQUE FK), colegio_pertenencia, tarifa_por_partido, estado. (Administración del padrón arbitral).

4. Logística y Programación
•	canchas: id (PK), nombre, direccion_ubicacion, capacidad_partidos_consecutivos, estado.
•	bloqueos_cancha: id (PK), cancha_id (FK), fecha_inicio, fecha_fin, motivo (Lluvia/Mantenimiento).
•	prestamos_cancha: id (PK), cancha_id (FK), club_solicitante_id (FK), formato_prestamo_pdf, vigencia.
•	preferencias_equipo: id (PK), equipo_id (FK), cancha_id_preferida (FK), dia_preferido, hora_preferida.
•	jornadas: id (PK), grupo_id (FK), numero_jornada, fecha_inicio, fecha_fin.
•	partidos: id (PK), jornada_id (FK), equipo_local_id (FK), equipo_visitante_id (FK), cancha_id (FK_null), arbitro_id (FK_null), fecha_partido, hora_partido, estado (ENUM: borrador, programado, confirmado, en juego, finalizado, suspendido).

5. Operación, Arbitraje y Disciplina
•	cedulas_arbitrales: id (PK), partido_id (UNIQUE FK), goles_local, goles_visitante, informe_arbitral (TEXT), firma_capitan_local, firma_capitan_visitante.
•	eventos_partido: id (PK), cedula_id (FK), jugador_id (FK_null), tipo (Gol, Amarilla, Roja), minuto.
•	catalogo_articulos: id (PK), codigo, descripcion (TEXT), castigo_base_partidos (INT).
•	expedientes_disciplinarios: id (PK), cedula_id (FK), partido_id (FK), articulo_id (FK), estado (Abierto/Resuelto).
•	sanciones: id (PK), expediente_id (FK), persona_id (FK), descripcion_sancion (TEXT), partidos_suspendidos, partidos_cumplidos, estado.
•	recursos_inconformidad: id (PK), sancion_id (FK), club_apelante_id (FK), evidencia_pdf, resolucion (TEXT), estado.

6. Competencia (Fases Finales) y Finanzas
•	tablas_posiciones: id (PK), grupo_id (FK), equipo_id (FK), pj, pg, pe, pp, gf, gc, dg, puntos.
•	llaves_liguilla: id (PK), fase_id (FK), equipo_local_id (FK), equipo_visitante_id (FK), marcador_global_local, marcador_global_visitante, avanza_equipo_id (FK_null).
•	cargos_financieros: id (PK), club_id (FK), equipo_id (FK_null), persona_id (FK_null), concepto (Inscripción/Arbitraje/Credenciales/Multa), monto, vencimiento, estado (Pendiente/Pagado/Vencido).
•	pagos: id (PK), cargo_id (FK), monto, metodo_pago, referencia, recibo_url, validado_por_tesorero_id (FK).
