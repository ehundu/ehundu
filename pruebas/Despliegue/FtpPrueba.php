<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Despliegue;

use Ehundu\CompiladorEnProceso;
use Ehundu\Despliegue\Configuracion;
use Ehundu\Despliegue\Desplegador;
use Ehundu\Despliegue\Ftp;
use Ehundu\Despliegue\Manifiesto;
use Ehundu\ErrorDeProyecto;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contra un servidor FTP falso que corre en otro proceso (ver
 * `servidor-ftp-falso.php`), sin cifrar. El cifrado se prueba contra un
 * servidor de verdad. Hace falta la extensión ftp: en Windows,
 * `php -d extension=ftp vendor/bin/phpunit`.
 */
final class FtpPrueba extends TestCase
{
    use CarpetaTemporal;

    private const string CLAVE = 'clave-de-prueba';

    /** @var resource|null */
    private $proceso = null;

    private int $puerto = 0;

    private string $servidor = '';

    #[Before]
    protected function arrancarServidor(): void
    {
        if (!extension_loaded('ftp')) {
            self::markTestSkipped('Falta la extensión ftp');
        }

        $this->servidor = $this->carpetaTemporal() . '/servidor';
        mkdir($this->servidor . '/www', 0777, true);

        $this->proceso = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Apoyo/servidor-ftp-falso.php', $this->servidor, self::CLAVE],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $tuberias,
        ) ?: null;

        $this->puerto = (int) fgets($tuberias[1]);
        self::assertGreaterThan(0, $this->puerto, 'El servidor FTP falso no ha arrancado');
    }

    #[After]
    protected function pararServidor(): void
    {
        if ($this->proceso !== null) {
            proc_terminate($this->proceso);
            proc_close($this->proceso);
            $this->proceso = null;
        }
    }

    #[Test]
    public function subeLeeBorraYQuitaCarpetas(): void
    {
        $ftp = new Ftp($this->configuracion());
        $local = $this->carpetaTemporal() . '/local.css';
        file_put_contents($local, 'a{}');

        self::assertNull($ftp->leer('.ehundu.json'));

        $ftp->subir('css/tema/a.css', $local);
        $ftp->escribir('.ehundu.json', '{"ehundu": 1}');

        self::assertSame('a{}', file_get_contents("{$this->servidor}/www/css/tema/a.css"));
        self::assertSame('{"ehundu": 1}', $ftp->leer('.ehundu.json'));

        $ftp->borrar('css/tema/a.css');
        $ftp->borrar('no-existe.html');
        $ftp->quitarCarpeta('css/tema');
        $ftp->cerrar();

        self::assertFileDoesNotExist("{$this->servidor}/www/css/tema/a.css");
        self::assertDirectoryDoesNotExist("{$this->servidor}/www/css/tema");
    }

    #[Test]
    public function unaClaveQueNoValeDiceQuienNoPuedeEntrar(): void
    {
        try {
            new Ftp($this->configuracion('otra'));
            self::fail('Tenía que fallar');
        } catch (ErrorDeProyecto $error) {
            self::assertStringStartsWith('sitio.yml: No se puede entrar en 127.0.0.1 como prueba', $error->getMessage());
            self::assertStringNotContainsString('otra', $error->getMessage());
        }
    }

    #[Test]
    public function despliegaUnSitioEntero(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\ndespliegue:\n  destino: ftp\n  servidor: 127.0.0.1\n"
            . "  puerto: {$this->puerto}\n  usuario: prueba\n  ruta: /www\n  cifrado: no\n");
        $this->crearFichero('.secretos.yml', "despliegue:\n  clave: " . self::CLAVE . "\n");
        $this->crearPlantillaMinima();
        $this->crearFichero('contenido/index.md', 'Hola');
        $this->crearFichero('contenido/blog/uno.md', 'Uno');
        $this->crearFichero('publico/img/logo.svg', '<svg/>');

        $informe = (new Desplegador(new CompiladorEnProceso()))->desplegar(Proyecto::abrir($this->carpetaTemporal()));

        self::assertSame(['img/logo.svg', 'blog/uno/index.html', 'index.html', 'sitemap.xml'], $informe->subidos);
        self::assertSame('<svg/>', file_get_contents("{$this->servidor}/www/img/logo.svg"));
        self::assertFileExists("{$this->servidor}/www/" . Manifiesto::FICHERO);
        self::assertStringStartsWith("Desplegado en ftp://prueba@127.0.0.1:{$this->puerto}/www: 4 ficheros", $informe->resumen());

        unlink($this->carpetaTemporal() . '/contenido/blog/uno.md');
        $informe = (new Desplegador(new CompiladorEnProceso()))->desplegar(Proyecto::abrir($this->carpetaTemporal()));

        self::assertSame(['blog/uno/index.html'], $informe->borrados);
        self::assertDirectoryDoesNotExist("{$this->servidor}/www/blog");
    }

    private function configuracion(string $clave = self::CLAVE): Configuracion
    {
        return new Configuracion(
            destino: 'ftp',
            servidor: '127.0.0.1',
            puerto: $this->puerto,
            usuario: 'prueba',
            ruta: '/www',
            cifrado: false,
            clave: $clave,
        );
    }
}
