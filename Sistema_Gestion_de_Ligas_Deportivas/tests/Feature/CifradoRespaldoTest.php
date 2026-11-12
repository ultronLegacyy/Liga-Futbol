<?php

namespace Tests\Feature;

use App\Services\Respaldos\CifradoRespaldo;
use App\Services\Respaldos\RespaldoException;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// HU-009 (SCRUM-174): cifrado por bloques de los respaldos con Sodium secretstream (D-036)
class CifradoRespaldoTest extends TestCase
{
    private string $carpeta;

    private CifradoRespaldo $cifrado;

    protected function setUp(): void
    {
        parent::setUp();

        $this->carpeta = sys_get_temp_dir().'/sgld-cifrado-prueba-'.uniqid();
        File::ensureDirectoryExists($this->carpeta);
        config(['respaldos.clave' => base64_encode(random_bytes(32))]);
        $this->cifrado = app(CifradoRespaldo::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);

        parent::tearDown();
    }

    // Varios bloques de 64 KB, un tamaño múltiplo exacto del bloque y un archivo vacío
    #[DataProvider('tamanos')]
    public function test_cifrar_y_descifrar_devuelve_el_mismo_archivo(int $bytes): void
    {
        $original = $this->archivo('original', $bytes > 0 ? random_bytes($bytes) : '');

        $this->cifrado->cifrar($original, $this->ruta('cifrado'));
        $this->cifrado->descifrar($this->ruta('cifrado'), $this->ruta('descifrado'));

        $this->assertSame(hash_file('sha256', $original), hash_file('sha256', $this->ruta('descifrado')));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function tamanos(): array
    {
        return [
            'varios bloques' => [200_000],
            'múltiplo exacto de 64 KB' => [131_072],
            'vacío' => [0],
        ];
    }

    public function test_el_archivo_cifrado_no_contiene_el_texto_original(): void
    {
        $texto = str_repeat('CURP GOMA800101HMNRRR09 · contraseña del club · ', 3000);
        $this->cifrado->cifrar($this->archivo('original', $texto), $this->ruta('cifrado'));

        $cifrado = (string) file_get_contents($this->ruta('cifrado'));
        $this->assertStringNotContainsString('GOMA800101HMNRRR09', $cifrado);
        $this->assertStringNotContainsString('contraseña', $cifrado);
        $this->assertGreaterThan(strlen($texto), strlen($cifrado));
    }

    public function test_rechaza_un_byte_alterado_y_no_deja_el_archivo_a_medias(): void
    {
        $this->cifrado->cifrar($this->archivo('original', random_bytes(150_000)), $this->ruta('cifrado'));
        $datos = (string) file_get_contents($this->ruta('cifrado'));
        $datos[100_000] = chr(ord($datos[100_000]) ^ 0x01);
        file_put_contents($this->ruta('cifrado'), $datos);

        $this->assertRechazo('No se pudo descifrar el respaldo: está alterado o se cifró con otra clave.');
        $this->assertFileDoesNotExist($this->ruta('descifrado'));
    }

    // Sin el bloque final (truncado justo después de un bloque completo)
    public function test_rechaza_un_archivo_truncado(): void
    {
        $this->cifrado->cifrar($this->archivo('original', random_bytes(150_000)), $this->ruta('cifrado'));
        $cabecera = strlen("SGLDRESP1\n") + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES;
        $bloque = 65536 + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;
        file_put_contents($this->ruta('cifrado'), substr((string) file_get_contents($this->ruta('cifrado')), 0, $cabecera + $bloque));

        $this->assertRechazo('El respaldo está truncado: le falta el bloque final.');
        $this->assertFileDoesNotExist($this->ruta('descifrado'));
    }

    public function test_rechaza_un_archivo_cifrado_con_otra_clave(): void
    {
        $this->cifrado->cifrar($this->archivo('original', 'datos de la liga'), $this->ruta('cifrado'));
        config(['respaldos.clave' => base64_encode(random_bytes(32))]);

        $this->assertRechazo('No se pudo descifrar el respaldo: está alterado o se cifró con otra clave.');
    }

    public function test_rechaza_un_archivo_que_no_es_un_respaldo(): void
    {
        $this->archivo('cifrado', 'texto plano');

        $this->assertRechazo('El archivo no es un respaldo cifrado del sistema o está truncado.');
    }

    // El error por clave faltante o inválida no muestra la clave
    #[DataProvider('clavesInvalidas')]
    public function test_la_clave_faltante_o_invalida_da_un_error_claro_sin_mostrarla(?string $clave, string $mensaje): void
    {
        config(['respaldos.clave' => $clave]);

        try {
            $this->cifrado->cifrar($this->archivo('original', 'datos'), $this->ruta('cifrado'));
            $this->fail('Se esperaba un error por la clave.');
        } catch (RespaldoException $error) {
            $this->assertSame($mensaje, $error->getMessage());

            if ($clave !== null && $clave !== '') {
                $this->assertStringNotContainsString($clave, $error->getMessage());
            }
        }

        $this->assertFileDoesNotExist($this->ruta('cifrado'));
    }

    /**
     * @return array<string, array{string|null, string}>
     */
    public static function clavesInvalidas(): array
    {
        $faltante = 'Falta la clave de cifrado de los respaldos: define RESPALDO_CLAVE (32 bytes en base64).';
        $invalida = 'La clave RESPALDO_CLAVE no es válida: debe ser base64 de exactamente 32 bytes.';

        return [
            'sin clave' => [null, $faltante],
            'vacía' => ['', $faltante],
            'de 16 bytes' => [base64_encode('ClaveCortaSecret'), $invalida],
            'no es base64' => ['ClaveSecreta$$NoBase64!!', $invalida],
        ];
    }

    private function assertRechazo(string $mensaje): void
    {
        try {
            $this->cifrado->descifrar($this->ruta('cifrado'), $this->ruta('descifrado'));
            $this->fail('Se esperaba que el descifrado fallara.');
        } catch (RespaldoException $error) {
            $this->assertSame($mensaje, $error->getMessage());
        }
    }

    private function archivo(string $nombre, string $contenido): string
    {
        file_put_contents($this->ruta($nombre), $contenido);

        return $this->ruta($nombre);
    }

    private function ruta(string $nombre): string
    {
        return $this->carpeta.'/'.$nombre;
    }
}
