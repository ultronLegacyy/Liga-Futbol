# SGLD · Sistema de Gestión para la Liga Municipal de Morelia

Sistema web para administrar la operación de la **Liga Municipal de Fútbol Amateur de Morelia, A.C.**: cuentas y roles, configuración de temporadas, canchas, afiliación de equipos y jugadores, calendario de partidos, resultados, régimen disciplinario y portal público.

Es un proyecto académico de la materia Ingeniería de Software del **TecNM Campus Morelia**. Los requisitos están en una ERS basada en ISO/IEC/IEEE 29148 y el trabajo se organiza con Scrum.

> **Estado:** en desarrollo (octubre–diciembre de 2026).

## Módulos

| Módulo | Qué cubre |
|---|---|
| Núcleo, seguridad y gobernanza | Cuentas con uno o varios roles, contraseña temporal y permisos por rol |
| Afiliación y padrón | Registro de clubes, equipos y jugadores |
| Logística y calendario | Temporadas, categorías, grupos, canchas, disponibilidad y rol de juegos |
| Operación de juego y resultados | Cédulas arbitrales, marcadores y tabla de posiciones |
| Régimen disciplinario | Sanciones y su seguimiento |
| Finanzas y administración | Pagos y control administrativo |
| Fases finales | Liguilla y torneos especiales |
| Portal público | Consulta de calendario, resultados y posiciones |

## Tecnologías

- PHP 8.5 y Laravel 13
- MariaDB 12.3 LTS
- Blade, Tailwind CSS 4 y Vite 8
- PHPUnit, PHPStan y Laravel Pint

## Instalación local

```bash
cd Sistema_Gestion_de_Ligas_Deportivas
composer install
npm install
cp .env.example .env
php artisan key:generate
# Configura la base de datos en .env
php artisan migrate --seed
npm run build
php artisan serve
```
