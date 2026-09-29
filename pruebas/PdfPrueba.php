<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Construccion;
use Ehundu\Constructor;
use Ehundu\ErrorDeProyecto;
use Ehundu\Previsualizacion\Previsualizacion;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * El PDF de una página (formato §10.3): `pdf: sí` da, además del HTML, un PDF
 * hecho con `plantillas/<plantilla>.pdf.twig`.
 */
final class PdfPrueba extends TestCase
{
    use CarpetaTemporal;

    private const string PLANTILLA_PDF = '<html><head><meta charset="utf-8"></head><body><h1>{{ pagina.titulo }}</h1>{{ pagina.contenido }}</body></html>';

    protected function setUp(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('plantillas/pagina.twig', '<html><body>{{ pagina.contenido }}{% if pagina.pdf %}<a href="{{ pagina.pdf }}">PDF</a>{% endif %}</body></html>');
        $this->crearFichero('plantillas/pagina.pdf.twig', self::PLANTILLA_PDF);
    }

    #[Test]
    public function unaPaginaConPdfSiSaleTambienComoPdf(): void
    {
        $this->crearFichero('contenido/menu.md', "---\ntitulo: Menú\npdf: sí\n---\nSopa de pescado\n");

        $construccion = $this->construir();

        self::assertSame(['menu/index.html', 'menu.pdf', 'sitemap.xml'], array_keys($construccion->escritos));
        self::assertStringStartsWith('%PDF-', $construccion->escritos['menu.pdf']);
        self::assertSame(1, $construccion->paginas);
        self::assertSame([], $construccion->avisos);
    }

    #[Test]
    public function laPlantillaWebEnlazaSuPdfConPaginaPdf(): void
    {
        $this->crearFichero('contenido/menu.md', "---\ntitulo: Menú\npdf: sí\n---\nHola\n");
        $this->crearFichero('contenido/otra.md', "---\ntitulo: Otra\n---\nHola\n");

        $construccion = $this->construir();

        self::assertStringContainsString('<a href="/menu.pdf">PDF</a>', $construccion->escritos['menu/index.html']);
        self::assertStringNotContainsString('PDF</a>', $construccion->escritos['otra/index.html']);
    }

    #[Test]
    public function sinPdfNoHayFicheroPdfNiHaceFaltaLaPlantilla(): void
    {
        $this->crearFichero('contenido/menu.md', "---\ntitulo: Menú\n---\nHola\n");
        unlink($this->carpetaTemporal() . '/plantillas/pagina.pdf.twig');

        $construccion = $this->construir();

        self::assertSame(['menu/index.html', 'sitemap.xml'], array_keys($construccion->escritos));
    }

    #[Test]
    public function pdfNoEsSiNoDesactivaElPdf(): void
    {
        $this->crearFichero('contenido/menu.md', "---\ntitulo: Menú\npdf: no\n---\nHola\n");

        self::assertNotContains('menu.pdf', array_keys($this->construir()->escritos));
    }

    #[Test]
    public function elPdfSaleEnLaDireccionDeLaPaginaConPdfEnLugarDeLaBarraFinal(): void
    {
        $this->crearFichero('contenido/index.md', "---\ntitulo: Inicio\npdf: sí\n---\nHola\n");
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\npdf: sí\n---\nHola\n");
        $this->crearFichero('contenido/informes/anual.md', "---\ntitulo: Anual\nurl: /informes/anual.html\npdf: sí\n---\nHola\n");

        $ficheros = array_keys($this->construir()->escritos);

        self::assertContains('index.pdf', $ficheros);
        self::assertContains('blog/uno.pdf', $ficheros);
        self::assertContains('informes/anual.pdf', $ficheros);
    }

    #[Test]
    public function elPdfSePideDesdeUnDatosYml(): void
    {
        $this->crearFichero('contenido/menus/_datos.yml', "pdf: sí\n");
        $this->crearFichero('contenido/menus/dia.md', "---\ntitulo: Del día\n---\nHola\n");
        $this->crearFichero('contenido/menus/carta.md', "---\ntitulo: Carta\npdf: no\n---\nHola\n");

        $ficheros = array_keys($this->construir()->escritos);

        self::assertContains('menus/dia.pdf', $ficheros);
        self::assertNotContains('menus/carta.pdf', $ficheros);
    }

    #[Test]
    public function unBorradorNoDaPdf(): void
    {
        $this->crearFichero('contenido/menu.md', "---\ntitulo: Menú\npdf: sí\nborrador: sí\n---\nHola\n");

        self::assertNotContains('menu.pdf', array_keys($this->construir()->escritos));
    }

    #[Test]
    public function enUnSitioEnVariosIdiomasCadaIdiomaDaSuPdfYLasTraduccionesLoEnlazan(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\nidiomas:\n  - codigo: eu\n  - codigo: es\n");
        $this->crearFichero('plantillas/pagina.twig', '{% for codigo, t in pagina.traducciones %}[{{ codigo }}={{ t.pdf }}]{% endfor %}');
        $this->crearFichero('plantillas/pagina.pdf.twig', '<html><body><p lang="{{ pagina.idioma }}">{{ pagina.titulo }}</p></body></html>');
        $this->crearFichero('contenido/menu.yml', "pdf: sí\n");
        $this->crearFichero('contenido/menu.md', "---\ntitulo: Menua\nurl: /menua/\n---\n");
        $this->crearFichero('contenido/menu.es.md', "---\ntitulo: Menú\nurl: /menu/\n---\n");

        $construccion = $this->construir();

        self::assertSame(['es/menu/index.html', 'menua/index.html', 'es/menu.pdf', 'menua.pdf', 'sitemap.xml'], array_keys($construccion->escritos));
        self::assertSame('[eu=/menua.pdf][es=/es/menu.pdf]', $construccion->escritos['menua/index.html']);
        self::assertNotSame($construccion->escritos['menua.pdf'], $construccion->escritos['es/menu.pdf']);
    }

    #[Test]
    public function laPlantillaDelPdfNoEsLaDeLaWebSinoLaDeLaPaginaConPdf(): void
    {
        $this->crearFichero('plantillas/carta.twig', '<html><body>WEB {{ pagina.titulo }}</body></html>');
        $this->crearFichero('plantillas/carta.pdf.twig', '<html><body>PDF {{ pagina.titulo }}</body></html>');
        $this->crearFichero('contenido/carta.md', "---\ntitulo: Carta\nplantilla: carta\npdf: sí\n---\n");

        $construccion = $this->construir();

        self::assertSame('<html><body>WEB Carta</body></html>', $construccion->escritos['carta/index.html']);
        self::assertStringStartsWith('%PDF-', $construccion->escritos['carta.pdf']);
    }

    #[Test]
    public function laPlantillaDelPdfRecibeLasMismasVariablesQueLaDeLaPagina(): void
    {
        $this->crearFichero('datos/cliente.yml', "telefono: '900 000 000'\n");
        $this->crearFichero('plantillas/pagina.pdf.twig', '<html><body><p>{{ sitio.nombre }} {{ datos.cliente.telefono }} {{ pagina.subtitulo }} {{ pagina.contenido }}</p></body></html>');
        $this->crearFichero('contenido/menu.md', "---\ntitulo: Menú\nsubtitulo: Del día\npdf: sí\n---\nSopa\n");

        $sinPdf = $this->construir()->escritos['menu.pdf'];
        $this->crearFichero('datos/cliente.yml', "telefono: '944 000 000'\n");
        $conOtroTelefono = $this->construir()->escritos['menu.pdf'];

        self::assertNotSame($sinPdf, $conOtroTelefono);
    }

    #[Test]
    public function siFaltaLaPlantillaDelPdfElBuildSeDetieneYDiceCual(): void
    {
        unlink($this->carpetaTemporal() . '/plantillas/pagina.pdf.twig');
        $this->crearFichero('contenido/menu.md', "---\ntitulo: Menú\npdf: sí\n---\nHola\n");

        try {
            $this->construir();
            self::fail('Tenía que detenerse');
        } catch (ErrorDeProyecto $error) {
            self::assertSame('contenido/menu.md', $error->fichero);
            self::assertStringContainsString('no existe plantillas/pagina.pdf.twig', $error->getMessage());
        }
    }

    #[Test]
    public function conPlantillaFalseNoHayDeDondeSacarLaDelPdf(): void
    {
        $this->crearFichero('contenido/menu.md', "---\ntitulo: Menú\nplantilla: false\npdf: sí\n---\nHola\n");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('lleva «plantilla: false»');

        $this->construir();
    }

    #[Test]
    public function unErrorEnLaPlantillaDelPdfDetieneElBuildConSuFicheroYSuLinea(): void
    {
        $this->crearFichero('plantillas/pagina.pdf.twig', "<html>\n<body>{% if %}</body></html>");
        $this->crearFichero('contenido/menu.md', "---\ntitulo: Menú\npdf: sí\n---\nHola\n");

        try {
            $this->construir();
            self::fail('Tenía que detenerse');
        } catch (ErrorDeProyecto $error) {
            self::assertSame('plantillas/pagina.pdf.twig', $error->fichero);
            self::assertSame(2, $error->linea);
        }
    }

    #[Test]
    public function unFragmentoOUnaUrlSinBarraNiHtmlNoPuedenTenerPdfYSeAvisa(): void
    {
        $this->crearFichero('contenido/fragmento.md', "---\nurl: false\npdf: sí\n---\nHola\n");
        $this->crearFichero('contenido/robots.twig', "---\nurl: /robots.txt\nplantilla: false\npdf: sí\n---\nSitemap: x\n");

        $construccion = $this->construir();

        self::assertSame(['robots.txt', 'sitemap.xml'], array_keys($construccion->escritos));
        self::assertSame(
            [
                'contenido/fragmento.md: «pdf» solo vale en una página cuya URL acaba en / o en .html; no se genera el PDF',
                'contenido/robots.twig: «pdf» solo vale en una página cuya URL acaba en / o en .html; no se genera el PDF',
            ],
            array_map(strval(...), $construccion->avisos),
        );
    }

    #[Test]
    public function elPdfNoEntraEnElSitemap(): void
    {
        $this->crearFichero('contenido/menu.md', "---\ntitulo: Menú\npdf: sí\n---\nHola\n");

        $sitemap = $this->construir()->escritos['sitemap.xml'];

        self::assertStringContainsString('https://ejemplo.com/menu/', $sitemap);
        self::assertStringNotContainsString('.pdf', $sitemap);
    }

    #[Test]
    public function dosCosasEnElMismoFicheroDePdfDetienenElBuild(): void
    {
        $this->crearFichero('contenido/menu.md', "---\ntitulo: Menú\npdf: sí\n---\nHola\n");
        $this->crearFichero('publico/menu.pdf', '%PDF-1.4 otro');

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('Dos ficheros van al mismo sitio de salida/, menu.pdf');

        $this->construir();
    }

    #[Test]
    public function elCssDeLaPlantillaDelPdfSeIncrustaEnElPdfYNoEnLaWeb(): void
    {
        $this->crearFichero('publico/pdf.css', "p { color: #c00 }\n");
        $this->crearFichero('plantillas/pagina.twig', '<html><head><style>{{ css() }}</style></head><body>{{ pagina.contenido }}</body></html>');
        $this->crearFichero('plantillas/pagina.pdf.twig', "<html><head><style>{{ css() }}</style></head><body>{{ css('pdf.css') }}<p>{{ pagina.titulo }}</p></body></html>");
        $this->crearFichero('contenido/menu.md', "---\ntitulo: Menú\npdf: sí\n---\nHola\n");

        $construccion = $this->construir();

        self::assertStringNotContainsString('#c00', $construccion->escritos['menu/index.html']);
        self::assertSame([], $construccion->avisos);

        // El CSS que se declaró y se incrustó cuenta para el PDF: si cambia, cambia el PDF.
        $antes = $construccion->escritos['menu.pdf'];
        $this->crearFichero('publico/pdf.css', "p { color: #0a0 }\n");

        self::assertNotSame($antes, $this->construir()->escritos['menu.pdf']);
    }

    #[Test]
    public function elCampoCssDeLaPaginaEsElDeLaWebYNoSeAplicaAlPdf(): void
    {
        $this->crearFichero('publico/web.css', ".fila { display: flex; gap: 8px }\n");
        $this->crearFichero('plantillas/pagina.twig', '<html><head><style>{{ css() }}</style></head><body>{{ pagina.contenido }}</body></html>');
        $this->crearFichero('plantillas/pagina.pdf.twig', '<html><head><style>{{ css() }}</style></head><body><p>{{ pagina.titulo }}</p></body></html>');
        $this->crearFichero('contenido/menu.md', "---\ntitulo: Menú\ncss: [web.css]\npdf: sí\n---\nHola\n");

        $construccion = $this->construir();

        self::assertSame([], array_map(strval(...), $construccion->avisos));
    }

    #[Test]
    public function avisaDeLoQueDompdfNoEntiendeConLaPlantillaDelPdf(): void
    {
        $this->crearFichero('plantillas/pagina.pdf.twig', '<html><head><style>p { display: flex; gap: 8px }</style></head><body><p>{{ pagina.titulo }}</p></body></html>');
        $this->crearFichero('contenido/menu.md', "---\ntitulo: Menú\npdf: sí\n---\nHola\n");

        $avisos = array_map(strval(...), $this->construir()->avisos);

        self::assertNotSame([], $avisos);

        foreach ($avisos as $aviso) {
            self::assertStringStartsWith('plantillas/pagina.pdf.twig: PDF: ', $aviso);
        }
    }

    #[Test]
    public function elMismoProyectoDaLosMismosBytesYUnaSegundaCompilacionNoEscribeNada(): void
    {
        $this->crearFichero('contenido/menu.md', "---\ntitulo: Menú\nfecha: 2026-09-29\npdf: sí\n---\nSopa de pescado\n");
        $proyecto = Proyecto::abrir($this->carpetaTemporal());

        $primera = $this->construir();
        $proyecto->sincronizarSalida($primera->escritos, $primera->copias);
        $segunda = $this->construir();

        self::assertSame(hash('sha256', $primera->escritos['menu.pdf']), hash('sha256', $segunda->escritos['menu.pdf']));
        self::assertSame(0, $proyecto->sincronizarSalida($segunda->escritos, $segunda->copias));
        self::assertSame($primera->escritos['menu.pdf'], file_get_contents($this->carpetaTemporal() . '/salida/menu.pdf'));
    }

    #[Test]
    public function laPrevisualizacionSirveElPdfConSuTipo(): void
    {
        $this->crearFichero('contenido/menu.md', "---\ntitulo: Menú\npdf: sí\n---\nHola\n");

        $previsualizacion = new Previsualizacion(Proyecto::abrir($this->carpetaTemporal()));
        $previsualizacion->reconstruir();
        $respuesta = $previsualizacion->responder('GET', '/menu.pdf');

        self::assertSame(200, $respuesta->estado);
        self::assertSame('application/pdf', $respuesta->tipo);
        self::assertStringStartsWith('%PDF-', (string) $respuesta->cuerpo);
    }

    private function construir(): Construccion
    {
        return (new Constructor())->construir(Proyecto::abrir($this->carpetaTemporal()));
    }
}
