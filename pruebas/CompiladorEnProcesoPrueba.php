<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\CompiladorEnProceso;
use Ehundu\ErrorDeProyecto;
use Ehundu\Informe;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CompiladorEnProcesoPrueba extends TestCase
{
    use CarpetaTemporal;

    #[Test]
    public function creaLaCarpetaDeSalida(): void
    {
        $this->crearSitioMinimo();

        $this->compilar();

        self::assertDirectoryExists($this->carpetaTemporal() . '/salida');
    }

    #[Test]
    public function unProyectoVacioNoGeneraPaginasNiAvisos(): void
    {
        $this->crearSitioMinimo();

        $informe = $this->compilar();

        self::assertSame(0, $informe->paginas);
        self::assertSame([], $informe->avisos);
    }

    #[Test]
    public function devuelveLosAvisosDeLaLectura(): void
    {
        $this->crearSitioMinimo();
        $this->crearPlantillaMinima();
        $this->crearFichero('contenido/index.md', "---\ntitulo: Inicio\nborrador: quizá\n---\n");

        $avisos = array_map(strval(...), $this->compilar()->avisos);

        self::assertSame(['contenido/index.md:3: «borrador» tiene que ser sí o no'], $avisos);
    }

    #[Test]
    public function dosPaginasConLaMismaUrlDetienenLaCompilacion(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/contacto.md', "---\ntitulo: Contacto\n---\n");
        $this->crearFichero('contenido/escribenos.md', "---\ntitulo: Escríbenos\nurl: /contacto/\n---\n");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('Dos páginas van al mismo fichero de salida, contacto/index.html');

        $this->compilar();
    }

    #[Test]
    public function unaPaginaQueAunNoSePublicaNoChoca(): void
    {
        $this->crearSitioMinimo();
        $this->crearPlantillaMinima();
        $this->crearFichero('contenido/contacto.md', "---\ntitulo: Contacto\n---\n");
        $this->crearFichero('contenido/escribenos.md', "---\ntitulo: Escríbenos\nurl: /contacto/\npublicar: 2026-10-01\n---\n");

        $informe = (new CompiladorEnProceso(new \DateTimeImmutable('2026-09-25', new \DateTimeZone('UTC'))))
            ->compilar(Proyecto::abrir($this->carpetaTemporal()));

        self::assertSame([], $informe->avisos);
    }

    #[Test]
    public function escribeCadaPaginaEnSuFicheroDeSalida(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('plantillas/pagina.twig', '<main>{{ pagina.contenido }}</main>');
        $this->crearFichero('contenido/index.md', 'Inicio');
        $this->crearFichero('contenido/blog/uno.md', 'Uno');
        $this->crearFichero('contenido/404.md', "---\nurl: /404.html\n---\nNo está");

        $informe = $this->compilar();

        self::assertSame(3, $informe->paginas);
        self::assertSame("<main><p>Inicio</p>\n</main>", $this->leerFichero('salida/index.html'));
        self::assertSame("<main><p>Uno</p>\n</main>", $this->leerFichero('salida/blog/uno/index.html'));
        self::assertSame("<main><p>No está</p>\n</main>", $this->leerFichero('salida/404.html'));
    }

    #[Test]
    public function noEscribeBorradoresNiFragmentos(): void
    {
        $this->crearSitioMinimo();
        $this->crearPlantillaMinima();
        $this->crearFichero('contenido/borrador.md', "---\nborrador: sí\n---\nNo");
        $this->crearFichero('contenido/fragmento.md', "---\nurl: false\n---\nNo");

        self::assertSame(0, $this->compilar()->paginas);
        self::assertSame(['.', '..', 'sitemap.xml'], scandir($this->carpetaTemporal() . '/salida'));
    }

    #[Test]
    public function vaciaLaSalidaAntesDeEscribir(): void
    {
        $this->crearSitioMinimo();
        $this->crearPlantillaMinima();
        $this->crearFichero('salida/pagina-borrada/index.html', 'resto de un build anterior');
        $this->crearFichero('contenido/index.md', 'Inicio');

        $this->compilar();

        self::assertDirectoryDoesNotExist($this->carpetaTemporal() . '/salida/pagina-borrada');
        self::assertFileExists($this->carpetaTemporal() . '/salida/index.html');
    }

    #[Test]
    public function unErrorEnUnaPlantillaNoTocaLaSalida(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('plantillas/pagina.twig', '{{ pagina.contenido|noexiste }}');
        $this->crearFichero('salida/index.html', 'lo publicado');
        $this->crearFichero('contenido/index.md', 'Inicio');

        try {
            $this->compilar();
            self::fail('Se esperaba un ErrorDeProyecto');
        } catch (ErrorDeProyecto $error) {
            self::assertSame('plantillas/pagina.twig:1: No existe el filtro «noexiste»', $error->getMessage());
            self::assertSame('lo publicado', $this->leerFichero('salida/index.html'));
        }
    }

    #[Test]
    public function copiaPublicoYLosDemasFicherosDeContenido(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('publico/css/estilos.css', 'body {}');
        $this->crearFichero('publico/.htaccess', 'Options -Indexes');
        $this->crearFichero('contenido/blog/foto.jpg', 'jpg');
        $this->crearFichero('contenido/blog/_notas.txt', 'privado');
        $this->crearFichero('contenido/blog/_datos.yml', 'etiquetas: [blog]');

        $informe = $this->compilar();

        self::assertSame(3, $informe->ficheros);
        self::assertSame('body {}', $this->leerFichero('salida/css/estilos.css'));
        self::assertSame('Options -Indexes', $this->leerFichero('salida/.htaccess'));
        self::assertSame('jpg', $this->leerFichero('salida/blog/foto.jpg'));
        self::assertFileDoesNotExist($this->carpetaTemporal() . '/salida/blog/_notas.txt');
        self::assertFileDoesNotExist($this->carpetaTemporal() . '/salida/blog/_datos.yml');
    }

    #[Test]
    public function unaPaginaYUnFicheroNoPuedenIrAlMismoSitio(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('plantillas/pagina.twig', '{{ pagina.contenido }}');
        $this->crearFichero('contenido/robots.twig', "---\nurl: /robots.txt\nplantilla: false\n---\nUser-agent: *");
        $this->crearFichero('publico/robots.txt', 'User-agent: *');

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('Dos ficheros van al mismo sitio de salida/, robots.txt: contenido/robots.twig y publico/robots.txt');

        $this->compilar();
    }

    #[Test]
    public function unFicheroDeContenidoYOtroDePublicoNoPuedenIrAlMismoSitio(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/img/Logo.png', 'uno');
        $this->crearFichero('publico/img/logo.png', 'otro');

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('Dos ficheros van al mismo sitio de salida/, img/logo.png: contenido/img/Logo.png y publico/img/logo.png');

        $this->compilar();
    }

    #[Test]
    public function generaElSitemapConLasPaginasHtmlListadas(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://www.ejemplo.com\nzonaHoraria: Europe/Madrid\n");
        $this->crearPlantillaMinima();
        $this->crearFichero('contenido/index.md', "---\nfecha: 2025-01-01\n---\nInicio");
        $this->crearFichero('contenido/blog/uno.md', "---\nfecha: 2025-03-18\n---\nUno");
        $this->crearFichero('contenido/contacto.md', 'Sin fecha');
        $this->crearFichero('contenido/aviso-legal.md', "---\nlistada: no\n---\nLegal");
        $this->crearFichero('contenido/404.md', "---\nurl: /404.html\n---\nNo está");
        $this->crearFichero('contenido/robots.twig', "---\nurl: /robots.txt\nplantilla: false\n---\nUser-agent: *");

        $this->compilar();

        self::assertSame(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
              <url>
                <loc>https://www.ejemplo.com/</loc>
                <lastmod>2025-01-01</lastmod>
              </url>
              <url>
                <loc>https://www.ejemplo.com/blog/uno/</loc>
                <lastmod>2025-03-18</lastmod>
              </url>
              <url>
                <loc>https://www.ejemplo.com/contacto/</loc>
              </url>
            </urlset>

            XML, $this->leerFichero('salida/sitemap.xml'));
    }

    #[Test]
    public function elSitemapYElFeedDelProyectoGananAlDelMotor(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://www.ejemplo.com\nfeed:\n  coleccion: blog\n");
        $this->crearFichero('publico/sitemap.xml', '<urlset>propio</urlset>');
        $this->crearFichero('contenido/feed.twig', "---\nurl: /feed.xml\nplantilla: false\n---\n<feed>propio</feed>");

        $this->compilar();

        self::assertSame('<urlset>propio</urlset>', $this->leerFichero('salida/sitemap.xml'));
        self::assertSame('<feed>propio</feed>', $this->leerFichero('salida/feed.xml'));
    }

    #[Test]
    public function sinSeccionFeedNoHayFeed(): void
    {
        $this->crearSitioMinimo();

        $this->compilar();

        self::assertFileDoesNotExist($this->carpetaTemporal() . '/salida/feed.xml');
    }

    #[Test]
    public function generaElFeedConLasEntradasMasRecientes(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Librería La Esquina\nurl: https://www.ejemplo.com\nidioma: es\nzonaHoraria: Europe/Madrid\nfeed:\n  coleccion: blog\n  limite: 2\n");
        $this->crearPlantillaMinima();
        $this->crearFichero('contenido/blog/_datos.yml', 'etiquetas: [blog]');
        $this->crearFichero('contenido/blog/antiguo.md', "---\ntitulo: Antiguo\nfecha: 2025-01-01\n---\nViejo");
        $this->crearFichero('contenido/blog/medio.md', "---\ntitulo: Medio\nfecha: 2025-02-01\n---\nMedio");
        $this->crearFichero('contenido/blog/nuevo.md', "---\ntitulo: Nuevo & mejor\nfecha: 2025-03-18\ndescripcion: Lo último\n---\nVer [contacto](/contacto/) y ![foto](/img/a.jpg \"t\").\n");
        $this->crearFichero('contenido/blog/sin-fecha.md', "---\ntitulo: Sin fecha\n---\nNada");

        $informe = $this->compilar();
        $feed = $this->leerFichero('salida/feed.xml');

        self::assertStringStartsWith("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<feed xmlns=\"http://www.w3.org/2005/Atom\" xml:lang=\"es\">\n", $feed);
        self::assertStringContainsString("  <title>Librería La Esquina</title>\n  <link href=\"https://www.ejemplo.com/feed.xml\" rel=\"self\"/>\n", $feed);
        self::assertStringContainsString("  <updated>2025-03-18T00:00:00+01:00</updated>\n", $feed);
        self::assertStringContainsString(
            "  <entry>\n    <title>Nuevo &amp; mejor</title>\n    <link href=\"https://www.ejemplo.com/blog/nuevo/\"/>\n"
            . "    <updated>2025-03-18T00:00:00+01:00</updated>\n    <id>https://www.ejemplo.com/blog/nuevo/</id>\n"
            . "    <summary>Lo último</summary>\n",
            $feed,
        );
        self::assertStringContainsString('href=&quot;https://www.ejemplo.com/contacto/&quot;', $feed);
        self::assertStringContainsString('src=&quot;https://www.ejemplo.com/img/a.jpg&quot;', $feed);
        self::assertStringContainsString('<title>Medio</title>', $feed);
        self::assertStringNotContainsString('<title>Antiguo</title>', $feed);
        self::assertSame(
            ['contenido/blog/sin-fecha.md: La página no tiene fecha y no entra en el feed'],
            array_map(strval(...), $informe->avisos),
        );
    }

    /**
     * Sin entradas, `updated` es el momento de la compilación, el mismo con
     * el que se decide qué está publicado, y no la hora del reloj: dos
     * compilaciones con el mismo momento dan el mismo feed.
     */
    #[Test]
    public function unFeedSinEntradasLlevaElMomentoDeLaCompilacion(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://www.ejemplo.com\nzonaHoraria: Europe/Madrid\nfeed:\n  coleccion: blog\n");
        $this->crearFichero('contenido/.gitkeep', '');

        $ahora = new \DateTimeImmutable('2025-03-18 11:30:52', new \DateTimeZone('UTC'));
        (new CompiladorEnProceso($ahora))->compilar(Proyecto::abrir($this->carpetaTemporal()));

        self::assertStringContainsString("  <updated>2025-03-18T12:30:52+01:00</updated>\n", $this->leerFichero('salida/feed.xml'));
    }

    #[Test]
    public function noTocaLaSalidaSiLaLecturaFalla(): void
    {
        try {
            $this->compilar();
            self::fail('Se esperaba un ErrorDeProyecto');
        } catch (ErrorDeProyecto) {
            self::assertDirectoryDoesNotExist($this->carpetaTemporal() . '/salida');
        }
    }

    private function compilar(): Informe
    {
        return (new CompiladorEnProceso())->compilar(Proyecto::abrir($this->carpetaTemporal()));
    }
}
