# Prueba de usabilidad de la cédula · Hoja de registro

HU-075, criterio 3 (RNF-14): 5 personas sin capacitación previa, solo con el [instructivo de una página](instructivo-cedula.md), capturan un partido completo (HU-036 y HU-075 juntas).

**Se cumple si al menos 4 de las 5 personas terminan en 5 minutos o menos.**

## Preparación
1. En una base de prueba (nunca en la de operación):
   ```bash
   php artisan migrate:fresh --seed
   php artisan db:seed --class=PruebaUsabilidadCedulaSeeder
   ```
   Deja 5 partidos programados para hoy, asignados a la cuenta del árbitro sembrado (`arbitro@ligafutbol.com`), de las 09:00 a las 13:00. Cada uno enfrenta a «Rojos N» contra «Azules N», con 7 jugadores activos por equipo, dorsales del 1 al 7.
2. La persona *N* usa el partido «Rojos N vs. Azules N». La contraseña de la cuenta la da quien dirige la prueba; no se escribe en esta hoja.
3. Cada persona usa un celular con la sesión ya iniciada y la pantalla en **Mis partidos**. Se le entrega el instructivo impreso. Nadie le explica nada durante la prueba.

## Guion (se le lee a cada persona antes de empezar)
> Eres el árbitro del partido «Rojos N vs. Azules N». Con el instructivo, captura en el celular lo siguiente y cierra la cédula.

1. **2 alineaciones:** asistieron los 7 jugadores de cada equipo; el capitán de cada equipo es el dorsal 1.
2. **4 goles** (marcador final 3-1):
   - Rojos, dorsal 7, minuto 12;
   - Rojos, dorsal 5, minuto 30;
   - Azules, dorsal 3, minuto 55;
   - Rojos, dorsal 7, minuto 80.
3. **3 tarjetas:**
   - amarilla a Azules dorsal 2, minuto 20;
   - amarilla a Rojos dorsal 4, minuto 44;
   - roja a Azules dorsal 6, minuto 70.
4. **Firmas:** firma por los dos capitanes y cierra la cédula.

**Tiempo:** empieza cuando la persona toca **Ver cédula** y termina cuando aparece «Cédula cerrada: ya no se puede modificar.».
**Terminó:** sí, si la cédula quedó cerrada con el marcador 3-1, los 4 goles y las 3 tarjetas.

## Registro

Fecha: ________ · Dirige la prueba: ________

| # | Persona (iniciales) | Celular y navegador | Tiempo (mm:ss) | ¿Terminó? | ¿≤ 5 min? | Dónde se atoró / comentarios |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | | | | | | |
| 2 | | | | | | |
| 3 | | | | | | |
| 4 | | | | | | |
| 5 | | | | | | |

**Resultado:** ___ de 5 terminaron en 5 minutos o menos. Criterio 3: ☐ se cumple (4 o más) ☐ no se cumple.

## Después de la prueba
- Revisa en cada partido que la cédula quedó cerrada con lo del guion.
- Anota en los comentarios lo que confundió a más de una persona: es lo primero que se corrige en la pantalla o en el instructivo.
