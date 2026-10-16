<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Construccion;
use Ehundu\Constructor;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `paginar` (formato §6.4): una página que parte una colección en trozos. Ella
 * es la primera, en su `url`, y el motor genera las demás.
 */
final class PaginarPrueba extends TestCase
{
    use CarpetaTemporal;

    private const string AHORA = '2026-01-01 12:00:00';

    /** Lo que escribe la plantilla de la lista: dónde está, sus páginas y los enlaces. */
    private const string LISTA = '{% set p = pagina.paginacion %}[{{ p.actual }}/{{ p.total }}]{% for a in pagina.paginas %}{{ a.titulo }},{% endfor %}'
        . '|prev={{ p.anterior }}|next={{ p.siguiente }}|{% for e in p.enlaces %}{{ e.numero }}{{ e.actual ? \'*\' : \'\' }}={{ e.url }};{% endfor %}';

    #[Test]
    public function parteLaColeccionEnTrozosEnElOrdenPedidoYLaPrimeraEstaEnSuUrl(): void
    {
        $this->crearSitio();

        $construccion = $this->construir();

        self::assertSame([], $this->avisos($construccion));
        self::assertSame(
            ['blog/index.html', 'blog/pagina/2/index.html', 'blog/pagina/3/index.html'],
            $this->paginasDeLaLista($construccion),
        );
        self::assertSame(
            '[1/3]Siete,Seis,Cinco,|prev=|next=/blog/pagina/2/|1*=/blog/;2=/blog/pagina/2/;3=/blog/pagina/3/;',
            $construccion->escritos['blog/index.html'],
        );
        self::assertSame(
            '[2/3]Cuatro,Tres,Dos,|prev=/blog/|next=/blog/pagina/3/|1=/blog/;2*=/blog/pagina/2/;3=/blog/pagina/3/;',
            $construccion->escritos['blog/pagina/2/index.html'],
        );
        self::assertSame(
            '[3/3]Uno,|prev=/blog/pagina/2/|next=|1=/blog/;2=/blog/pagina/2/;3*=/blog/pagina/3/;',
            $construccion->escritos['blog/pagina/3/index.html'],
        );
    }

    #[Test]
    public function laPaginacionDaElRecuentoYLosExtremos(): void
    {
        $this->crearSitio();
        $this->crearFichero(
            'plantillas/lista.twig',
            '{% set p = pagina.paginacion %}{{ p.cantidad }}|{{ p.porPagina }}|{{ p.primera }}|{{ p.ultima }}',
        );

        $construccion = $this->construir();

        self::assertSame('7|3|/blog/|/blog/pagina/3/', $construccion->escritos['blog/index.html']);
        self::assertSame('7|3|/blog/|/blog/pagina/3/', $construccion->escritos['blog/pagina/2/index.html']);
    }

    #[Test]
    public function sinOrdenSigueElOrdenDeLaColeccion(): void
    {
        $this->crearSitio(sinOrden: true);

        $construccion = $this->construir();

        self::assertStringStartsWith('[1/3]Uno,Dos,Tres,|', $construccion->escritos['blog/index.html']);
        self::assertStringStartsWith('[3/3]Siete,|', $construccion->escritos['blog/pagina/3/index.html']);
    }

    #[Test]
    public function laPropiaPaginaNoSeListaAunqueHeredeLaEtiquetaDeSuCarpeta(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/recuento.twig', "---\ntitulo: Recuento\nurl: /recuento/\nplantilla: false\n---\n{{ coleccion('blog')|length }}|{{ coleccion('todo')|length }}");

        $construccion = $this->construir();

        // El blog tiene siete artículos y la lista, que hereda la etiqueta; el
        // sitio, esos ocho, el recuento y nada más: las que genera no cuentan.
        self::assertSame('8|9', $construccion->escritos['recuento/index.html']);
        self::assertStringContainsString('[1/3]Siete,Seis,Cinco,', $construccion->escritos['blog/index.html']);
    }

    #[Test]
    public function laPrimeraSaleEnElSitemapYLasGeneradasNo(): void
    {
        $this->crearSitio();

        $sitemap = $this->construir()->escritos['sitemap.xml'];

        self::assertStringContainsString('<loc>https://ejemplo.com/blog/</loc>', $sitemap);
        self::assertStringContainsString('<loc>https://ejemplo.com/blog/siete/</loc>', $sitemap);
        self::assertStringNotContainsString('pagina/2', $sitemap);
        self::assertStringNotContainsString('pagina/3', $sitemap);
    }

    #[Test]
    public function unaColeccionQueCabeEnUnaPaginaNoGeneraNinguna(): void
    {
        $this->crearSitio();
        $this->cambiarLaDefinicion('porPagina: 3', 'porPagina: 7');

        $construccion = $this->construir();

        self::assertSame(['blog/index.html'], $this->paginasDeLaLista($construccion));
        self::assertStringStartsWith('[1/1]Siete,Seis,Cinco,Cuatro,Tres,Dos,Uno,|prev=|next=|1*=/blog/;', $construccion->escritos['blog/index.html']);
    }

    #[Test]
    public function unaColeccionQueSeReparteExactaNoDejaUnaUltimaPaginaVacia(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/blog/ocho.md', "---\ntitulo: Ocho\nfecha: 2024-08-01\n---\nOcho.\n");
        $this->cambiarLaDefinicion('porPagina: 3', 'porPagina: 4');

        $construccion = $this->construir();

        self::assertSame(['blog/index.html', 'blog/pagina/2/index.html'], $this->paginasDeLaLista($construccion));
        self::assertStringStartsWith('[2/2]Cuatro,Tres,Dos,Uno,|', $construccion->escritos['blog/pagina/2/index.html']);
    }

    #[Test]
    public function unaColeccionSinPaginasDaUnaSolaPaginaVacia(): void
    {
        $this->crearSitio();
        $this->cambiarLaDefinicion('coleccion: blog', 'coleccion: vacia');

        $construccion = $this->construir();

        self::assertSame(['blog/index.html'], $this->paginasDeLaLista($construccion));
        self::assertSame('[1/1]|prev=|next=|1*=/blog/;', $construccion->escritos['blog/index.html']);
        self::assertSame([], $this->avisos($construccion));
    }

    #[Test]
    public function paginarUrlCambiaLaDireccionDeLasQueGenera(): void
    {
        $this->crearSitio();
        $this->cambiarLaDefinicion('orden: fecha desc', "orden: fecha desc\n  url: \"/blog/{{ numero }}/\"");

        $construccion = $this->construir();

        self::assertSame(['blog/2/index.html', 'blog/3/index.html', 'blog/index.html'], $this->paginasDeLaLista($construccion));
        self::assertStringContainsString('|next=/blog/2/|1*=/blog/;2=/blog/2/;3=/blog/3/;', $construccion->escritos['blog/index.html']);
    }

    #[Test]
    public function unaUrlSinNumeroNoPartePorqueTodasIrianAlMismoFichero(): void
    {
        $this->crearSitio();
        $this->cambiarLaDefinicion('orden: fecha desc', "orden: fecha desc\n  url: /blog/mas/");

        $construccion = $this->construir();

        self::assertSame(['blog/index.html'], $this->paginasDeLaLista($construccion));
        self::assertSame(
            ['contenido/blog/index.md: Una «url» de «paginar» tiene que usar {{ numero }}: sin él, todas las páginas irían al mismo fichero; no se parte nada'],
            $this->avisos($construccion),
        );
        // Es una página más: sin `paginas` ni `paginacion`.
        self::assertStringStartsWith('[/]|prev=|next=|', $construccion->escritos['blog/index.html']);
    }

    #[Test]
    public function sinBarraFinalHaceFaltaPaginarUrl(): void
    {
        $this->crearSitio();
        $this->cambiarLaDefinicion('url: /blog/', 'url: /blog.html');

        $construccion = $this->construir();

        self::assertSame(['blog.html'], array_values(array_filter(array_keys($construccion->escritos), fn (string $f) => str_starts_with($f, 'blog.') || str_contains($f, 'pagina'))));
        self::assertSame(
            ['contenido/blog/index.md: La «url» de una página con «paginar» tiene que acabar en / para que las demás salgan en /blog.htmlpagina/2/; si no, se escribe «paginar.url». No se parte nada'],
            $this->avisos($construccion),
        );
    }

    #[Test]
    public function conPaginarUrlUnaUrlSinBarraFinalSirve(): void
    {
        $this->crearSitio();
        $this->cambiarLaDefinicion('url: /blog/', 'url: /blog.html');
        $this->cambiarLaDefinicion('orden: fecha desc', "orden: fecha desc\n  url: \"/blog-{{ numero }}.html\"");

        $construccion = $this->construir();

        self::assertSame([], $this->avisos($construccion));
        self::assertArrayHasKey('blog.html', $construccion->escritos);
        self::assertArrayHasKey('blog-2.html', $construccion->escritos);
        self::assertArrayHasKey('blog-3.html', $construccion->escritos);
    }

    #[Test]
    public function loQueFaltaOSobraEnLaDefinicionSeAvisaYNoSePartePorqueEsUnaPaginaMas(): void
    {
        $casos = [
            'sin porPagina' => ["  coleccion: blog\n", '«paginar» necesita «porPagina», cuántas páginas lleva cada una; no se parte nada'],
            'porPagina cero' => ["  coleccion: blog\n  porPagina: 0\n", '«paginar»: «porPagina» tiene que ser un número entero mayor que cero; no se parte nada'],
            'porPagina texto' => ["  coleccion: blog\n  porPagina: diez\n", '«paginar»: «porPagina» tiene que ser un número entero mayor que cero; no se parte nada'],
            'sin coleccion' => ["  porPagina: 3\n", '«paginar» necesita «coleccion», la colección de la que salen las páginas; no se parte nada'],
        ];

        foreach ($casos as $nombre => [$definicion, $aviso]) {
            $this->crearSitio();
            $this->crearFichero(
                'contenido/blog/index.md',
                "---\ntitulo: Blog\nplantilla: lista\nurl: /blog/\npaginar:\n{$definicion}---\nLista.\n",
            );

            $construccion = $this->construir();

            self::assertSame(['blog/index.html'], $this->paginasDeLaLista($construccion), $nombre);
            self::assertStringContainsString($aviso, implode("\n", $this->avisos($construccion)), $nombre);
        }
    }

    #[Test]
    public function unaClaveQueNoAdmiteSeAvisaYSeIgnora(): void
    {
        $this->crearSitio();
        $this->cambiarLaDefinicion('porPagina: 3', "porPagina: 3\n  tamano: 5");

        $construccion = $this->construir();

        self::assertCount(3, $this->paginasDeLaLista($construccion));
        self::assertStringContainsString('«paginar» no admite «tamano»; se ignora', implode("\n", $this->avisos($construccion)));
    }

    #[Test]
    public function paginarQueNoEsUnMapaSeIgnora(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/blog/index.md', "---\ntitulo: Blog\nplantilla: lista\nurl: /blog/\npaginar: sí\n---\nLista.\n");

        $construccion = $this->construir();

        self::assertSame(['blog/index.html'], $this->paginasDeLaLista($construccion));
        self::assertStringContainsString('«paginar» tiene que ser un mapa con «coleccion» y «porPagina»; se ignora', implode("\n", $this->avisos($construccion)));
    }

    #[Test]
    public function unOrdenQueNoSeEntiendeSeAvisaYSeUsaElDeLaColeccion(): void
    {
        $this->crearSitio();
        $this->cambiarLaDefinicion('orden: fecha desc', 'orden: fecha sube');

        $construccion = $this->construir();

        self::assertStringStartsWith('[1/3]Uno,Dos,Tres,|', $construccion->escritos['blog/index.html']);
        self::assertStringContainsString('«paginar»: «orden» no entiende «fecha sube»', implode("\n", $this->avisos($construccion)));
    }

    #[Test]
    public function unFragmentoNoSePartePorqueNoTieneUrl(): void
    {
        $this->crearSitio();
        $this->cambiarLaDefinicion('url: /blog/', 'url: false');

        $construccion = $this->construir();

        self::assertSame([], $this->paginasDeLaLista($construccion));
        self::assertStringContainsString('es un fragmento (url: false); no se parte nada', implode("\n", $this->avisos($construccion)));
    }

    #[Test]
    public function paginarNoSeHeredaDeUnaCarpeta(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/blog/_datos.yml', "etiquetas: [blog]\nurl: \"/blog/{{ titulo|slug }}/\"\npaginar:\n  coleccion: blog\n  porPagina: 3\n");
        $this->crearFichero('contenido/blog/index.md', "---\ntitulo: Blog\nplantilla: lista\nurl: /blog/\n---\nLista.\n");

        $construccion = $this->construir();

        self::assertSame(['blog/index.html'], $this->paginasDeLaLista($construccion));
        self::assertStringContainsString('«paginar» no se hereda', implode("\n", $this->avisos($construccion)));
    }

    #[Test]
    public function conAgruparPaginarSeIgnoraPorqueQuedaReservado(): void
    {
        $this->crearSitio();
        $this->crearFichero(
            'contenido/tema.md',
            "---\ntitulo: Tema\nplantilla: lista\nurl: \"/tema/{{ grupo|slug }}/\"\nagrupar:\n  coleccion: blog\n  campo: etiquetas\n  excluir: [blog]\npaginar:\n  coleccion: blog\n  porPagina: 3\n---\nLista.\n",
        );
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\nfecha: 2024-01-01\netiquetas: [ideas]\n---\nUno.\n");

        $construccion = $this->construir();

        self::assertStringContainsString('Una página no puede llevar «agrupar» y «paginar» a la vez; «paginar» se ignora', implode("\n", $this->avisos($construccion)));
        // Genera sus grupos y no parte ninguno: la página de «ideas» no tiene paginación.
        self::assertArrayHasKey('tema/ideas/index.html', $construccion->escritos);
        self::assertArrayNotHasKey('tema/ideas/pagina/2/index.html', $construccion->escritos);
        self::assertStringStartsWith('[/]', $construccion->escritos['tema/ideas/index.html']);
    }

    #[Test]
    public function unaEntradaPorPublicarNoEntraHastaQueLlegaSuFecha(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/blog/futuro.md', "---\ntitulo: Futuro\nfecha: 2024-09-01\npublicar: 2030-01-01\n---\nAún no.\n");

        $antes = $this->construir();
        $despues = $this->construir(new \DateTimeImmutable('2031-01-01'));

        self::assertCount(3, $this->paginasDeLaLista($antes));
        self::assertStringStartsWith('[1/3]Siete,', $antes->escritos['blog/index.html']);
        self::assertStringStartsWith('[1/3]Futuro,Siete,Seis,', $despues->escritos['blog/index.html']);
        self::assertStringStartsWith('[3/3]Dos,Uno,|', $despues->escritos['blog/pagina/3/index.html']);
    }

    #[Test]
    public function cadaIdiomaPartePorSuCuentaConElPrefijoDelSuyo(): void
    {
        $this->crearSitio();
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\nidiomas:\n  - codigo: es\n  - codigo: eu\n");
        $this->crearFichero('plantillas/lista.twig', self::LISTA . '|{{ pagina.traducciones|length }}');
        $this->crearFichero(
            'contenido/blog/index.eu.md',
            "---\ntitulo: Bloga\nplantilla: lista\nurl: /blog/\npaginar:\n  coleccion: blog\n  porPagina: 1\n---\nZerrenda.\n",
        );
        $this->crearFichero('contenido/blog/bat.eu.md', "---\ntitulo: Bat\nfecha: 2024-01-01\n---\nBat.\n");
        $this->crearFichero('contenido/blog/bi.eu.md', "---\ntitulo: Bi\nfecha: 2024-02-01\n---\nBi.\n");

        $construccion = $this->construir();

        self::assertArrayHasKey('blog/pagina/3/index.html', $construccion->escritos);
        self::assertArrayHasKey('eu/blog/index.html', $construccion->escritos);
        self::assertArrayHasKey('eu/blog/pagina/2/index.html', $construccion->escritos);
        self::assertArrayNotHasKey('eu/blog/pagina/3/index.html', $construccion->escritos);
        // La primera comparte sus traducciones; las que se generan no tienen.
        self::assertSame('[1/2]Bat,|prev=|next=/eu/blog/pagina/2/|1*=/eu/blog/;2=/eu/blog/pagina/2/;|2', $construccion->escritos['eu/blog/index.html']);
        self::assertSame('[2/2]Bi,|prev=/eu/blog/|next=|1=/eu/blog/;2*=/eu/blog/pagina/2/;|1', $construccion->escritos['eu/blog/pagina/2/index.html']);
        self::assertStringEndsWith('|2', $construccion->escritos['blog/index.html']);
        self::assertStringEndsWith('|1', $construccion->escritos['blog/pagina/2/index.html']);
    }

    /**
     * Siete artículos, uno al mes, y una lista del blog que los reparte de tres
     * en tres, de más reciente a más antiguo. La lista hereda la etiqueta
     * `blog` de su carpeta.
     */
    private function crearSitio(bool $sinOrden = false): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\n");
        $this->crearFichero('plantillas/pagina.twig', '{{ pagina.contenido }}');
        $this->crearFichero('plantillas/lista.twig', self::LISTA);
        $this->crearFichero('contenido/blog/_datos.yml', "etiquetas: [blog]\nurl: \"/blog/{{ titulo|slug }}/\"\n");

        foreach (['Uno', 'Dos', 'Tres', 'Cuatro', 'Cinco', 'Seis', 'Siete'] as $i => $titulo) {
            $mes = sprintf('%02d', $i + 1);
            $this->crearFichero('contenido/' . 'blog/' . strtolower($titulo) . '.md', "---\ntitulo: {$titulo}\nfecha: 2024-{$mes}-01\n---\n{$titulo}.\n");
        }

        $orden = $sinOrden ? '' : "  orden: fecha desc\n";
        $this->crearFichero(
            'contenido/blog/index.md',
            "---\ntitulo: Blog\nplantilla: lista\nurl: /blog/\npaginar:\n  coleccion: blog\n  porPagina: 3\n{$orden}---\nLista.\n",
        );
    }

    private function cambiarLaDefinicion(string $antes, string $despues): void
    {
        $ruta = $this->carpetaTemporal() . '/contenido/blog/index.md';
        $contenido = (string) file_get_contents($ruta);

        self::assertStringContainsString($antes, $contenido);
        file_put_contents($ruta, str_replace($antes, $despues, $contenido));
    }

    /**
     * Los ficheros de la lista y de sus páginas entre lo construido.
     *
     * @return list<string>
     */
    private function paginasDeLaLista(Construccion $construccion): array
    {
        $ficheros = array_values(array_filter(
            array_keys($construccion->escritos),
            fn (string $fichero) => $fichero === 'blog/index.html'
                || preg_match('#^blog/(pagina/)?\d+/index\.html$#', $fichero) === 1
                || str_starts_with($fichero, 'blog/pagina/'),
        ));
        sort($ficheros);

        return $ficheros;
    }

    /**
     * @return list<string>
     */
    private function avisos(Construccion $construccion): array
    {
        return array_map(strval(...), $construccion->avisos);
    }

    private function construir(?\DateTimeImmutable $ahora = null): Construccion
    {
        return (new Constructor())->construir(Proyecto::abrir($this->carpetaTemporal()), $ahora ?? new \DateTimeImmutable(self::AHORA));
    }
}
