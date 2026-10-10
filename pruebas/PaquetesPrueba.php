<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\CompiladorEnProceso;
use Ehundu\ErrorDeProyecto;
use Ehundu\Informe;
use Ehundu\Previsualizacion\Previsualizacion;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * El CSS y el JS que enlazan las páginas con `css_fichero()` y `js_fichero()`
 * (formato §9.1) llegan a `salida/` y a la previsualización como cualquier
 * otro fichero generado.
 */
final class PaquetesPrueba extends TestCase
{
    use CarpetaTemporal;

    private const string CSS_BASE = 'body { margin: 0 }';

    protected function setUp(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('plantillas/pagina.twig', '<head>{{ css(\'base.css\') }}{{ css_fichero() }}</head>{{ pagina.contenido }}{{ js_fichero() }}');
        $this->crearFichero('publico/base.css', self::CSS_BASE);
        $this->crearFichero('publico/blog.css', '.blog { color: red }');
        $this->crearFichero('publico/galeria.js', 'galeria();');
        $this->crearFichero('contenido/index.md', 'Inicio');
        $this->crearFichero('contenido/contacto.md', 'Contacto');
        $this->crearFichero('contenido/blog/uno.md', "---\ncss: blog.css\njs: galeria.js\n---\nUno");
    }

    #[Test]
    public function compilarEscribeCadaPaqueteUnaVezYLasPaginasLoEnlazan(): void
    {
        $this->compilar();

        $base = 'css/' . $this->huella(self::CSS_BASE) . '.css';
        $blog = 'css/' . $this->huella(self::CSS_BASE . "\n.blog { color: red }") . '.css';
        $galeria = 'js/' . $this->huella('galeria();') . '.js';

        self::assertSame(self::CSS_BASE, $this->leerFichero("salida/{$base}"));
        self::assertSame(self::CSS_BASE . "\n.blog { color: red }", $this->leerFichero("salida/{$blog}"));
        self::assertSame('galeria();', $this->leerFichero("salida/{$galeria}"));

        self::assertStringContainsString("href=\"/{$base}\"", $this->leerFichero('salida/index.html'));
        self::assertStringContainsString("href=\"/{$base}\"", $this->leerFichero('salida/contacto/index.html'));
        self::assertStringContainsString("href=\"/{$blog}\"", $this->leerFichero('salida/blog/uno/index.html'));
        self::assertStringContainsString("<script src=\"/{$galeria}\" defer></script>", $this->leerFichero('salida/blog/uno/index.html'));

        // Dos paquetes de CSS y uno de JS, sin repetir el de las dos páginas que declaran lo mismo.
        self::assertCount(2, glob($this->carpetaTemporal() . '/salida/css/*.css') ?: []);
        self::assertCount(1, glob($this->carpetaTemporal() . '/salida/js/*.js') ?: []);
    }

    #[Test]
    public function alCambiarElCssElPaqueteViejoDesapareceDeLaSalida(): void
    {
        $this->compilar();
        $viejo = 'css/' . $this->huella(self::CSS_BASE) . '.css';

        self::assertFileExists($this->carpetaTemporal() . "/salida/{$viejo}");

        $this->crearFichero('publico/base.css', 'body { margin: 1px }');
        $this->compilar();
        $nuevo = 'css/' . $this->huella('body { margin: 1px }') . '.css';

        self::assertFileDoesNotExist($this->carpetaTemporal() . "/salida/{$viejo}");
        self::assertFileExists($this->carpetaTemporal() . "/salida/{$nuevo}");
        self::assertStringContainsString("href=\"/{$nuevo}\"", $this->leerFichero('salida/index.html'));
    }

    #[Test]
    public function unFicheroDePublicoConElNombreDelPaqueteDetieneLaCompilacion(): void
    {
        $nombre = 'css/' . $this->huella(self::CSS_BASE) . '.css';
        $this->crearFichero("publico/{$nombre}", 'otra cosa');

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage("Dos ficheros van al mismo sitio de salida/, {$nombre}");

        $this->compilar();
    }

    #[Test]
    public function laPrevisualizacionSirveElPaqueteComoCss(): void
    {
        $previsualizacion = new Previsualizacion(Proyecto::abrir($this->carpetaTemporal()));
        self::assertInstanceOf(Informe::class, $previsualizacion->reconstruir());

        $respuesta = $previsualizacion->responder('GET', '/css/' . $this->huella(self::CSS_BASE) . '.css');

        self::assertSame(200, $respuesta->estado);
        self::assertSame('text/css; charset=utf-8', $respuesta->tipo);
        self::assertSame(self::CSS_BASE, $respuesta->cuerpo);
    }

    private function compilar(): Informe
    {
        return (new CompiladorEnProceso())->compilar(Proyecto::abrir($this->carpetaTemporal()));
    }

    private function huella(string $contenido): string
    {
        return substr(hash('sha256', $contenido), 0, 10);
    }
}
