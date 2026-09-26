<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Plantillas;

use Ehundu\Avisos;
use Ehundu\Colecciones;
use Ehundu\ErrorDeProyecto;
use Ehundu\Lector;
use Ehundu\Lectura;
use Ehundu\Pagina;
use Ehundu\Plantillas\Maquetador;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use Ehundu\Pruebas\Apoyo\Png;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MaquetadorPrueba extends TestCase
{
    use CarpetaTemporal;

    private Avisos $avisos;
    private Lectura $lectura;

    protected function setUp(): void
    {
        $this->avisos = new Avisos();
        $this->crearFichero('sitio.yml', "nombre: Librería La Esquina\nurl: https://www.ejemplo.com\nzonaHoraria: Europe/Madrid\n");
        $this->crearFichero('datos/cliente.yml', "telefono: 900 000 000\n");
    }

    #[Test]
    public function ponElCuerpoDentroDeSuPlantillaConLasVariablesDelSitio(): void
    {
        $this->crearFichero('plantillas/pagina.twig', '<title>{{ pagina.titulo }} · {{ sitio.nombre }}</title>{{ pagina.contenido }}<p>{{ datos.cliente.telefono }}</p>');
        $this->crearFichero('contenido/contacto.md', "---\ntitulo: Contacto\n---\nEscríbenos **hoy**.");

        self::assertSame(
            "<title>Contacto · Librería La Esquina</title><p>Escríbenos <strong>hoy</strong>.</p>\n<p>900 000 000</p>",
            $this->maquetar('contacto.md'),
        );
    }

    #[Test]
    public function lasPlantillasSeAnidanConExtendsEIncluyenParciales(): void
    {
        $this->crearFichero('plantillas/base.twig', '<html>{% include \'parciales/cabecera.twig\' %}{% block cuerpo %}{% endblock %}</html>');
        $this->crearFichero('plantillas/articulo.twig', '{% extends \'plantillas/base.twig\' %}{% block cuerpo %}<article>{{ pagina.contenido }}</article>{% endblock %}');
        $this->crearFichero('parciales/cabecera.twig', '<header>{{ sitio.nombre }}</header>');
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\nplantilla: articulo\n---\nHola");

        self::assertSame(
            "<html><header>Librería La Esquina</header><article><p>Hola</p>\n</article></html>",
            $this->maquetar('blog/uno.md'),
        );
    }

    #[Test]
    public function unaPaginaTwigSinPlantillaSaleTalCual(): void
    {
        $this->crearFichero('contenido/robots.twig', "---\nurl: /robots.txt\nplantilla: false\n---\nSitemap: {{ sitio.url }}/sitemap.xml\n");

        self::assertSame("Sitemap: https://www.ejemplo.com/sitemap.xml\n", $this->maquetar('robots.twig'));
    }

    #[Test]
    public function elContenidoDeOtrasPaginasSeConvierteCuandoSePide(): void
    {
        $this->crearFichero('plantillas/pagina.twig', '{{ pagina.contenido }}');
        $this->crearFichero('contenido/index.twig', "---\ntitulo: Inicio\n---\n{% for a in coleccion('blog') %}[{{ a.contenido }}]{% endfor %}");
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\netiquetas: [blog]\n---\nPrimer *artículo*");

        self::assertSame("[<p>Primer <em>artículo</em></p>\n]", $this->maquetar('index.twig'));
    }

    #[Test]
    public function unaPaginaQueSeNecesitaASiMismaEsUnError(): void
    {
        $this->crearFichero('plantillas/pagina.twig', '{{ pagina.contenido }}');
        $this->crearFichero('contenido/a.twig', "---\netiquetas: [grupo]\n---\n{% for p in coleccion('grupo') %}{{ p.contenido }}{% endfor %}");
        $this->crearFichero('contenido/b.twig', "---\netiquetas: [grupo]\n---\nB");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('Una página necesita su propio contenido para construirse: contenido/a.twig → contenido/a.twig');

        $this->maquetar('a.twig');
    }

    #[Test]
    public function faltaLaPlantillaQuePideLaPagina(): void
    {
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\nplantilla: articulo\n---\nHola");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('contenido/blog/uno.md: La página usa la plantilla «articulo», pero no existe plantillas/articulo.twig');

        $this->maquetar('blog/uno.md');
    }

    #[Test]
    public function unaPlantillaConExtensionDiceQueVaSinElla(): void
    {
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\nlayout: layouts/post.njk\n---\nHola");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage(
            'contenido/blog/uno.md: La página usa la plantilla «layouts/post.njk», pero no existe plantillas/layouts/post.njk.twig. '
            . 'El nombre va sin extensión: «layouts/post»',
        );

        $this->maquetar('blog/uno.md');
    }

    #[Test]
    public function losErroresDeTwigEnElCuerpoLlevanLaLineaDelFichero(): void
    {
        $this->crearFichero('contenido/index.twig', "---\ntitulo: Inicio\n---\n<h1>Hola</h1>\n{{ pagina.titulo|mayusculas }}\n");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('contenido/index.twig:5: No existe el filtro «mayusculas»');

        $this->maquetarSinPlantilla('index.twig');
    }

    #[Test]
    public function losErroresDeUnaPlantillaLlevanSuFicheroYLinea(): void
    {
        $this->crearFichero('plantillas/pagina.twig', "<main>\n{{ coleccion('blog')|orden('fecha arriba') }}\n</main>");
        $this->crearFichero('contenido/index.md', 'Hola');

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage("plantillas/pagina.twig:2: orden('fecha arriba'): cada criterio es un campo seguido, si acaso, de asc o desc");

        $this->maquetar('index.md');
    }

    #[Test]
    public function svgInsertaElFicheroTalCualSinDeclaracionXml(): void
    {
        $this->crearFichero('publico/svg/telefono.svg', "<?xml version=\"1.0\"?>\n<!DOCTYPE svg>\n<svg viewBox=\"0 0 1 1\"><path d=\"M0 0\"/></svg>\n");
        $this->crearFichero('publico/svg/sobre.svg', '<svg id="sobre"></svg>');
        $this->crearFichero('contenido/index.twig', "---\nplantilla: false\n---\n{{ svg('svg/telefono.svg') }}|{{ svg('/svg/sobre.svg') }}");

        self::assertSame("<svg viewBox=\"0 0 1 1\"><path d=\"M0 0\"/></svg>\n|<svg id=\"sobre\"></svg>", $this->maquetar('index.twig'));
    }

    #[Test]
    public function svgAvisaSiNoPuedeInsertarElFichero(): void
    {
        $this->crearFichero('publico/css/estilos.css', 'body {}');
        $this->crearFichero('contenido/index.twig', "---\nplantilla: false\n---\n{{ svg('svg/falta.svg') }}{{ svg('../sitio.yml') }}{{ svg('css/estilos.css') }}");

        self::assertSame('', $this->maquetar('index.twig'));
        self::assertSame([
            "svg('svg/falta.svg'): no existe publico/svg/falta.svg; no se inserta nada",
            "svg('../sitio.yml'): solo se insertan ficheros .svg de dentro de publico/; no se inserta nada",
            "svg('css/estilos.css'): solo se insertan ficheros .svg de dentro de publico/; no se inserta nada",
        ], array_map(strval(...), $this->avisos->todos()));
    }

    #[Test]
    public function dimensionesDaElAnchoYElAltoDeUnaImagen(): void
    {
        $this->crearFichero('publico/img/foto.png', Png::de(640, 480));
        $this->crearFichero('publico/svg/logo.svg', '<svg></svg>');
        $this->crearFichero(
            'contenido/index.twig',
            "---\nplantilla: false\n---\n{% set d = dimensiones('img/foto.png') %}{{ d.ancho }}x{{ d.alto }}|{{ dimensiones('/img/foto.png').alto }}|{{ dimensiones('svg/logo.svg') is null ? 'sin medidas' }}",
        );

        self::assertSame('640x480|480|sin medidas', $this->maquetar('index.twig'));
        self::assertSame([], $this->avisos->todos());
    }

    #[Test]
    public function dimensionesAvisaSiNoPuedeLeerElFichero(): void
    {
        $this->crearFichero('contenido/index.twig', "---\nplantilla: false\n---\n{{ dimensiones('img/falta.png') is null ? 'a' }}{{ dimensiones('../sitio.yml') is null ? 'b' }}");

        self::assertSame('ab', $this->maquetar('index.twig'));
        self::assertSame([
            "dimensiones('img/falta.png'): no existe publico/img/falta.png",
            "dimensiones('../sitio.yml'): solo se leen imágenes de dentro de publico/",
        ], array_map(strval(...), $this->avisos->todos()));
    }

    #[Test]
    public function activoMarcaLaPaginaYSuSeccion(): void
    {
        $plantilla = "{% for url in ['/', '/blog/', '/blog', '/blog/uno/', '/contacto/', '/bl'] %}{{ activo(url) ? 'sí' : 'no' }} {% endfor %}";
        $this->crearFichero('plantillas/pagina.twig', $plantilla);
        $this->crearFichero('contenido/blog/uno.md', 'Uno');
        $this->crearFichero('contenido/index.md', 'Inicio');

        self::assertSame('no sí sí sí no no ', $this->maquetar('blog/uno.md'));
        self::assertSame('sí no no no no no ', $this->maquetar('index.md'));
    }

    #[Test]
    public function lasFechasSalenEnEspanolYEnLaZonaDelSitio(): void
    {
        $this->crearFichero('plantillas/pagina.twig', "{{ pagina.fecha|fecha('d F Y') }} | {{ pagina.fecha|fecha }} | {{ pagina.fecha|date('Y-m-d H:i') }}");
        $this->crearFichero('contenido/blog/uno.md', "---\nfecha: 2025-03-18\n---\nUno");

        self::assertSame('18 marzo 2025 | 18 de marzo de 2025 | 2025-03-18 00:00', $this->maquetar('blog/uno.md'));
    }

    #[Test]
    public function lasLetrasLiteralesDeFechaLlevanDosBarrasEnLaPlantilla(): void
    {
        $this->crearFichero('plantillas/pagina.twig', "{{ pagina.fecha|fecha('j \\\\d\\\\e F') }}");
        $this->crearFichero('contenido/blog/uno.md', "---\nfecha: 2025-03-18\n---\nUno");

        self::assertStringContainsString("fecha('j \\\\d\\\\e F')", $this->leerFichero('plantillas/pagina.twig'));
        self::assertSame('18 de marzo', $this->maquetar('blog/uno.md'));
    }

    #[Test]
    public function dosProyectosEnElMismoProcesoNoCompartenPlantillas(): void
    {
        $this->crearFichero('plantillas/pagina.twig', 'primero: {{ pagina.contenido }}');
        $this->crearFichero('contenido/index.md', 'Hola');
        self::assertSame("primero: <p>Hola</p>\n", $this->maquetar('index.md'));

        $otro = sys_get_temp_dir() . '/ehundu-pruebas-otro-' . bin2hex(random_bytes(6));
        mkdir("{$otro}/plantillas", 0777, true);
        mkdir("{$otro}/contenido");
        file_put_contents("{$otro}/sitio.yml", "nombre: Otro\nurl: https://otro.ejemplo.com\n");
        file_put_contents("{$otro}/plantillas/pagina.twig", 'segundo: {{ pagina.contenido }}');
        file_put_contents("{$otro}/contenido/index.md", 'Hola');

        try {
            $proyecto = Proyecto::abrir($otro);
            $lectura = (new Lector())->leer($proyecto);
            $maquetador = new Maquetador($proyecto, $lectura, new Colecciones($lectura->paginas, new \DateTimeImmutable()), new Avisos());

            self::assertSame("segundo: <p>Hola</p>\n", $maquetador->maquetar($lectura->paginas[0]));
        } finally {
            array_map(unlink(...), ["{$otro}/plantillas/pagina.twig", "{$otro}/contenido/index.md", "{$otro}/sitio.yml"]);
            array_map(rmdir(...), ["{$otro}/plantillas", "{$otro}/contenido", $otro]);
        }
    }

    #[Test]
    public function unaPlantillaEditadaSeVuelveACompilarEnElMismoProceso(): void
    {
        $this->crearFichero('plantillas/pagina.twig', 'antes: {{ pagina.contenido }}');
        $this->crearFichero('contenido/index.md', 'Hola');
        self::assertSame("antes: <p>Hola</p>\n", $this->maquetar('index.md'));

        $this->crearFichero('plantillas/pagina.twig', 'después: {{ pagina.contenido }}');
        self::assertSame("después: <p>Hola</p>\n", $this->maquetar('index.md'));
    }

    private function maquetar(string $ruta): string
    {
        return $this->maquetador()->maquetar($this->pagina($ruta));
    }

    private function maquetarSinPlantilla(string $ruta): string
    {
        return $this->maquetador()->cuerpo($this->pagina($ruta));
    }

    private function maquetador(): Maquetador
    {
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $this->lectura = (new Lector())->leer($proyecto);

        return new Maquetador(
            $proyecto,
            $this->lectura,
            new Colecciones($this->lectura->paginas, new \DateTimeImmutable('2026-09-25')),
            $this->avisos,
        );
    }

    private function pagina(string $ruta): Pagina
    {
        foreach ($this->lectura->paginas as $pagina) {
            if ($pagina->ruta === $ruta) {
                return $pagina;
            }
        }

        self::fail("No se ha leído la página {$ruta}");
    }
}
