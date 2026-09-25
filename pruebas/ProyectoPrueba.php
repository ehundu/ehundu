<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\ErrorDeProyecto;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProyectoPrueba extends TestCase
{
    use CarpetaTemporal;

    #[Test]
    public function abreUnaCarpetaExistente(): void
    {
        $proyecto = Proyecto::abrir($this->carpetaTemporal());

        self::assertSame($this->carpetaTemporal(), $proyecto->raiz);
    }

    #[Test]
    public function guardaLaRaizConBarrasNormalesYSinBarraFinal(): void
    {
        $conBarraInvertida = str_replace('/', DIRECTORY_SEPARATOR, $this->carpetaTemporal()) . DIRECTORY_SEPARATOR;

        $proyecto = Proyecto::abrir($conBarraInvertida);

        self::assertStringNotContainsString('\\', $proyecto->raiz);
        self::assertStringEndsNotWith('/', $proyecto->raiz);
    }

    #[Test]
    public function componeRutasDentroDelProyecto(): void
    {
        $proyecto = Proyecto::abrir($this->carpetaTemporal());

        self::assertSame(
            $this->carpetaTemporal() . '/contenido/blog/index.md',
            $proyecto->ruta(Proyecto::CONTENIDO, 'blog', 'index.md'),
        );
    }

    #[Test]
    public function fallaSiLaCarpetaNoExiste(): void
    {
        $ruta = $this->carpetaTemporal() . '/no-existe';

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage("No existe la carpeta del proyecto: {$ruta}");

        Proyecto::abrir($ruta);
    }

    #[Test]
    public function leeTextoSinBomYConFinalesDeLineaLf(): void
    {
        $this->crearFichero('sitio.yml', "\u{FEFF}nombre: Prueba\r\nurl: https://ejemplo.com\r\n");

        self::assertSame(
            "nombre: Prueba\nurl: https://ejemplo.com\n",
            Proyecto::abrir($this->carpetaTemporal())->leerTexto('sitio.yml'),
        );
    }

    #[Test]
    public function rechazaUnFicheroQueNoEstaEnUtf8(): void
    {
        $this->crearFichero('contenido/index.md', "titulo: Canci\xF3n\n");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('contenido/index.md: El fichero no está en UTF-8');

        Proyecto::abrir($this->carpetaTemporal())->leerTexto('contenido/index.md');
    }

    #[Test]
    public function avisaDeUnFicheroQueNoSePuedeLeer(): void
    {
        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('datos/cliente.yml: No se puede leer el fichero');

        Proyecto::abrir($this->carpetaTemporal())->leerTexto('datos/cliente.yml');
    }

    #[Test]
    public function vaciarSalidaBorraTodoLoQueHayDentro(): void
    {
        $this->crearFichero('salida/viejo.html', 'viejo');
        $this->crearFichero('salida/blog/antiguo/index.html', 'viejo');
        $this->crearFichero('contenido/index.md', 'se queda');

        Proyecto::abrir($this->carpetaTemporal())->vaciarSalida();

        self::assertDirectoryExists($this->carpetaTemporal() . '/salida');
        self::assertSame(['.', '..'], scandir($this->carpetaTemporal() . '/salida'));
        self::assertFileExists($this->carpetaTemporal() . '/contenido/index.md');
    }

    #[Test]
    public function vaciarSalidaLaCreaSiNoExiste(): void
    {
        Proyecto::abrir($this->carpetaTemporal())->vaciarSalida();

        self::assertDirectoryExists($this->carpetaTemporal() . '/salida');
    }

    #[Test]
    public function escribeEnSalidaConSusCarpetas(): void
    {
        Proyecto::abrir($this->carpetaTemporal())->escribirEnSalida('blog/uno/index.html', '<p>Uno</p>');

        self::assertSame('<p>Uno</p>', $this->leerFichero('salida/blog/uno/index.html'));
    }

    #[Test]
    public function noEscribeFueraDeSalida(): void
    {
        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('No se escribe fuera de salida/: ../contenido/index.md');

        Proyecto::abrir($this->carpetaTemporal())->escribirEnSalida('../contenido/index.md', 'fuera');
    }

    #[Test]
    public function fallaSiLaRutaEsUnFichero(): void
    {
        $fichero = $this->carpetaTemporal() . '/sitio.yml';
        file_put_contents($fichero, '');

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage("La ruta del proyecto no es una carpeta: {$fichero}");

        Proyecto::abrir($fichero);
    }
}
