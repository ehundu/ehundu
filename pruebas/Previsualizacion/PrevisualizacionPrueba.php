<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Previsualizacion;

use Ehundu\ErrorDeProyecto;
use Ehundu\Informe;
use Ehundu\Previsualizacion\Previsualizacion;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PrevisualizacionPrueba extends TestCase
{
    use CarpetaTemporal;

    protected function setUp(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('plantillas/pagina.twig', '<html><body>{{ pagina.contenido }}</body></html>');
        $this->crearFichero('contenido/index.md', 'Inicio');
        $this->crearFichero('contenido/blog/uno.md', 'Uno');
        $this->crearFichero('publico/css/estilos.css', 'body {}');
        $this->crearFichero('publico/img/logo.png', 'png');
    }

    #[Test]
    public function sirveLasPaginasConElScriptDeRecarga(): void
    {
        $respuesta = $this->previsualizacion()->responder('GET', '/');

        self::assertSame(200, $respuesta->estado);
        self::assertSame('text/html; charset=utf-8', $respuesta->tipo);
        self::assertStringStartsWith("<html><body><p>Inicio</p>\n<script>", (string) $respuesta->cuerpo);
        self::assertStringContainsString('const version = "1"', (string) $respuesta->cuerpo);
        self::assertStringEndsWith('</script></body></html>', (string) $respuesta->cuerpo);
    }

    #[Test]
    public function sirveLosFicherosDePublicoDesdeElProyecto(): void
    {
        $respuesta = $this->previsualizacion()->responder('GET', '/css/estilos.css?v=2');

        self::assertSame(200, $respuesta->estado);
        self::assertSame('text/css; charset=utf-8', $respuesta->tipo);
        self::assertSame($this->carpetaTemporal() . '/publico/css/estilos.css', $respuesta->fichero);
        self::assertSame('image/png', $this->previsualizacion()->responder('GET', '/img/logo.png')->tipo);
    }

    #[Test]
    public function redirigeUnaCarpetaSinBarraFinal(): void
    {
        $respuesta = $this->previsualizacion()->responder('GET', '/blog/uno?a=1');

        self::assertSame(301, $respuesta->estado);
        self::assertSame('/blog/uno/?a=1', $respuesta->ubicacion);
    }

    #[Test]
    public function loQueNoExisteDevuelveEl404DelSitio(): void
    {
        self::assertSame(404, $this->previsualizacion()->responder('GET', '/no-existe/')->estado);

        $this->crearFichero('contenido/404.md', "---\nurl: /404.html\n---\nNo está");
        $respuesta = $this->previsualizacion()->responder('GET', '/no-existe/');

        self::assertSame(404, $respuesta->estado);
        self::assertStringContainsString('<p>No está</p>', (string) $respuesta->cuerpo);
    }

    #[Test]
    public function daLaVersionYRechazaLoDemas(): void
    {
        $previsualizacion = $this->previsualizacion();

        self::assertSame('1', $previsualizacion->responder('GET', '/__ehundu/estado')->cuerpo);
        self::assertSame(405, $previsualizacion->responder('POST', '/')->estado);
        self::assertSame(400, $previsualizacion->responder('GET', '/../sitio.yml')->estado);
        self::assertSame(400, $previsualizacion->responder('GET', '/css/%2e%2e/%2e%2e/sitio.yml')->estado);
    }

    #[Test]
    public function noEscribeNadaEnSalida(): void
    {
        $this->previsualizacion();

        self::assertDirectoryDoesNotExist($this->carpetaTemporal() . '/salida');
    }

    #[Test]
    public function siLaConstruccionFallaLasPaginasMuestranElError(): void
    {
        $previsualizacion = $this->previsualizacion();
        $this->crearFichero('plantillas/pagina.twig', "<html>\n{{ pagina.contenido|noexiste }}</html>");

        $resultado = $previsualizacion->reconstruir();
        $pagina = $previsualizacion->responder('GET', '/blog/uno/');

        self::assertInstanceOf(ErrorDeProyecto::class, $resultado);
        self::assertSame('2', $previsualizacion->responder('GET', '/__ehundu/estado')->cuerpo);
        self::assertSame(500, $pagina->estado);
        self::assertStringContainsString('plantillas/pagina.twig:2: No existe el filtro «noexiste»', (string) $pagina->cuerpo);
        self::assertStringContainsString('const version = "2"', (string) $pagina->cuerpo);
        self::assertSame(200, $previsualizacion->responder('GET', '/css/estilos.css')->estado);

        $this->crearFichero('plantillas/pagina.twig', '<html><body>{{ pagina.contenido }}</body></html>');

        self::assertInstanceOf(Informe::class, $previsualizacion->reconstruir());
        self::assertSame(200, $previsualizacion->responder('GET', '/blog/uno/')->estado);
    }

    #[Test]
    public function conBorradoresEnsenaLoQueAunNoSePublica(): void
    {
        $this->crearFichero('contenido/borrador.md', "---\nborrador: sí\n---\nBorrador");
        $this->crearFichero('contenido/futuro.md', "---\npublicar: 2999-01-01\n---\nFuturo");

        $normal = $this->previsualizacion();
        $conBorradores = $this->previsualizacion(conBorradores: true);

        self::assertSame(404, $normal->responder('GET', '/borrador/')->estado);
        self::assertSame(404, $normal->responder('GET', '/futuro/')->estado);
        self::assertSame(200, $conBorradores->responder('GET', '/borrador/')->estado);
        self::assertSame(200, $conBorradores->responder('GET', '/futuro/')->estado);
    }

    private function previsualizacion(bool $conBorradores = false): Previsualizacion
    {
        $previsualizacion = new Previsualizacion(Proyecto::abrir($this->carpetaTemporal()), $conBorradores);
        $resultado = $previsualizacion->reconstruir();

        self::assertInstanceOf(Informe::class, $resultado);

        return $previsualizacion;
    }
}
