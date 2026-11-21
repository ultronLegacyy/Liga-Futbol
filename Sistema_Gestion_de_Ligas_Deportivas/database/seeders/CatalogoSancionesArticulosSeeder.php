<?php

namespace Database\Seeders;

use App\Models\CatalogoArticulo;
use Illuminate\Database\Seeder;

/**
 * Catálogo maestro de infracciones y artículos reglamentarios conforme al
 * Reglamento de Sanciones del Sector Amateur de la Federación Mexicana de Fútbol (FMF, REF-05).
 * Requisito: RF-10.1 / HU-044.
 */
class CatalogoSancionesArticulosSeeder extends Seeder
{
    public function run(): void
    {
        $articulos = [
            [
                'codigo' => 'ART-08',
                'descripcion' => 'Conducta antideportiva, desaprobación de decisiones arbitrales o reclamos reiterados al cuerpo arbitral durante el encuentro.',
                'castigo_base_partidos' => 1,
            ],
            [
                'codigo' => 'ART-12',
                'descripcion' => 'Juego brusco grave o conducta violenta sin balón contra un adversario o compañero en terreno de juego.',
                'castigo_base_partidos' => 2,
            ],
            [
                'codigo' => 'ART-14',
                'descripcion' => 'Expulsión del terreno de juego por recibir una segunda amonestación (doble tarjeta amarilla) en el mismo partido.',
                'castigo_base_partidos' => 1,
            ],
            [
                'codigo' => 'ART-15',
                'descripcion' => 'Insultos, injurias, ofensas verbales o ademanes soeces dirigidos a los oficiales del partido, directivos o espectadores.',
                'castigo_base_partidos' => 3,
            ],
            [
                'codigo' => 'ART-18',
                'descripcion' => 'Intento de agresión física o agresión consumada al cuerpo arbitral, comisario o directivos de la Liga.',
                'castigo_base_partidos' => 10,
            ],
            [
                'codigo' => 'ART-22',
                'descripcion' => 'Alineación indebida: participación de jugador no registrado, suspendido, inelegible o suplantación deliberada de identidad.',
                'castigo_base_partidos' => 2,
            ],
            [
                'codigo' => 'ART-30',
                'descripcion' => 'Conducta impropia, riña colectiva o invasión de campo provocada por la porra, simpatizantes o cuerpo técnico de un club.',
                'castigo_base_partidos' => 1,
            ],
            [
                'codigo' => 'ART-35',
                'descripcion' => 'Inasistencia injustificada o abandono colectivo del terreno de juego antes de la conclusión reglamentaria (Default deportivo).',
                'castigo_base_partidos' => 1,
            ],
        ];

        foreach ($articulos as $item) {
            CatalogoArticulo::updateOrCreate(
                ['codigo' => $item['codigo']],
                [
                    'descripcion' => $item['descripcion'],
                    'castigo_base_partidos' => $item['castigo_base_partidos'],
                ]
            );
        }
    }
}
