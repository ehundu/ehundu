<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\CompiladorEnProceso;
use Ehundu\ErrorDeProyecto;
use Ehundu\FicherosPhp;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FicherosPhpPrueba extends TestCase
{
    use CarpetaTemporal;

    /**
     * @return iterable<string, array{string}>
     */
    public static function ficherosPhp(): iterable
    {
        yield 'php' => ['contacto.php'];
        yield 'en mayúsculas' => ['CONTACTO.PHP'];
        yield 'en una carpeta' => ['img/subidas/shell.php'];
        yield 'php5' => ['a.php5'];
        yield 'php8' => ['a.php8'];
        yield 'phtml' => ['a.phtml'];
        yield 'pht' => ['a.pht'];
        yield 'phar' => ['a.phar'];
        yield 'con otra extensión detrás' => ['foto.php.jpg'];
        yield 'con dos extensiones' => ['a.php.txt.jpg'];
        yield 'un fichero oculto' => ['.php'];
        yield 'con barras de Windows' => ['img\\shell.php'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function ficherosQueNoSonPhp(): iterable
    {
        yield 'php sin extensión' => ['php'];
        yield 'php en el nombre' => ['php.jpg'];
        yield 'una carpeta con php' => ['php.d/foto.jpg'];
        yield 'otra extensión que empieza igual' => ['a.phpx'];
        yield 'phps' => ['a.phps'];
        yield 'php9' => ['a.php9'];
        yield 'el htaccess' => ['.htaccess'];
        yield 'una imagen' => ['img/foto.jpg'];
        yield 'sin extensión' => ['LICENSE'];
    }

    #[Test]
    #[DataProvider('ficherosPhp')]
    public function reconoceLosFicherosPhp(string $ruta): void
    {
        self::assertTrue(FicherosPhp::es($ruta));
    }

    #[Test]
    #[DataProvider('ficherosQueNoSonPhp')]
    public function noTomaPorPhpLoQueNoLoEs(string $ruta): void
    {
        self::assertFalse(FicherosPhp::es($ruta));
    }

    #[Test]
    public function unFicheroPhpEnPublicoDetieneLaCompilacionYDiceCual(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('publico/img/foto.php.jpg', 'GIF89a<?php system($_GET["c"]);');

        try {
            $this->compilar();
            self::fail('Tenía que detenerse');
        } catch (ErrorDeProyecto $error) {
            self::assertSame('publico/img/foto.php.jpg', $error->fichero);
            self::assertStringContainsString('Es un fichero PHP y no se copia a salida/', $error->getMessage());
            self::assertStringContainsString('estático', $error->getMessage());
        }

        self::assertFileDoesNotExist($this->carpetaTemporal() . '/salida/img/foto.php.jpg');
    }

    #[Test]
    public function unFicheroPhpEnContenidoDetieneLaCompilacion(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/subidas/enviar.PHP', '<?php echo 1;');

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('contenido/subidas/enviar.PHP: Es un fichero PHP');

        $this->compilar();
    }

    #[Test]
    public function unFicheroPhpQueEmpiezaPorGuionBajoNoSePublicaAsiQueNoDetieneNada(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/_ayuda.php', '<?php echo 1;');

        $this->compilar();

        self::assertFileDoesNotExist($this->carpetaTemporal() . '/salida/_ayuda.php');
    }

    #[Test]
    public function unProyectoSinPhpSeCompilaComoSiempre(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('publico/.htaccess', "ErrorDocument 404 /404.html\n");
        $this->crearFichero('publico/php.txt', 'hola');
        $this->crearFichero('publico/img/foto.jpg', 'JPEG');

        $this->compilar();

        self::assertFileExists($this->carpetaTemporal() . '/salida/php.txt');
        self::assertFileExists($this->carpetaTemporal() . '/salida/.htaccess');
    }

    private function compilar(): void
    {
        (new CompiladorEnProceso())->compilar(Proyecto::abrir($this->carpetaTemporal()));
    }
}
