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
 * `publicarEnSuFecha` (formato §4): en una carpeta de un blog que se escribe por
 * adelantado, una página no se publica antes de su `fecha`, igual que con
 * `publicar` pero sin repetirla en cada fichero.
 */
final class PublicarEnSuFechaPrueba extends TestCase
{
    use CarpetaTemporal;

    #[Test]
    public function laFechaFuturaDeUnaPaginaDeUnaCarpetaConElInterruptorLaRetiene(): void
    {
        $this->crearSitio();

        $construccion = $this->construir($this->madrid('2026-10-09 12:00:00'));

        self::assertSame(
            ['blog/evento/index.html', 'blog/hoy/index.html', 'blog/lejana/index.html', 'blog/pasada/index.html', 'blog/sin-fecha/index.html'],
            $this->paginasDelBlog($construccion),
        );
        self::assertSame('Evento;Hoy;Lejana;Pasada;Sin fecha;', $construccion->escritos['index.html']);
    }

    #[Test]
    public function laPaginaRetenidaNoSaleEnElSitemapNiEnElFeed(): void
    {
        $this->crearSitio();

        $construccion = $this->construir($this->madrid('2026-10-09 12:00:00'));

        self::assertStringContainsString('<loc>https://ejemplo.com/blog/hoy/</loc>', $construccion->escritos['sitemap.xml']);
        self::assertStringNotContainsString('/blog/manana/', $construccion->escritos['sitemap.xml']);
        self::assertStringContainsString('<title>Hoy</title>', $construccion->escritos['feed.xml']);
        self::assertStringNotContainsString('<title>Manana</title>', $construccion->escritos['feed.xml']);
    }

    #[Test]
    public function laPaginaSaleCuandoLlegaSuDiaYUnPublicarMandaSobreLaFecha(): void
    {
        $this->crearSitio();

        $despues = $this->construir($this->madrid('2027-02-01 12:00:00'));

        // `Manana` (fecha 2026-10-10) y `Retenida` (publicar 2027-01-01) ya han llegado; el borrador no.
        self::assertSame(
            [
                'blog/evento/index.html',
                'blog/hoy/index.html',
                'blog/lejana/index.html',
                'blog/manana/index.html',
                'blog/pasada/index.html',
                'blog/retenida/index.html',
                'blog/sin-fecha/index.html',
            ],
            $this->paginasDelBlog($despues),
        );
    }

    #[Test]
    public function unPublicarAnteriorALaFechaPublicaYUnoPosteriorRetiene(): void
    {
        $this->crearSitio();

        $construccion = $this->construir($this->madrid('2026-10-09 12:00:00'));

        // `Lejana`: fecha 2027-01-01 pero `publicar` pasado. `Retenida`: fecha pasada pero `publicar` futuro.
        self::assertArrayHasKey('blog/lejana/index.html', $construccion->escritos);
        self::assertArrayNotHasKey('blog/retenida/index.html', $construccion->escritos);
    }

    #[Test]
    public function seCuentaDesdeLaMedianocheDeLaFechaEnLaZonaDelSitio(): void
    {
        $this->crearSitio();

        $antes = $this->construir($this->madrid('2026-10-09 23:59:59'));
        $despues = $this->construir($this->madrid('2026-10-10 00:00:00'));

        self::assertArrayNotHasKey('blog/manana/index.html', $antes->escritos);
        self::assertArrayHasKey('blog/manana/index.html', $despues->escritos);
    }

    #[Test]
    public function unaSubcarpetaPuedeQuitarElInterruptor(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/blog/agenda/proximo.md', "---\ntitulo: Próximo\nfecha: 2027-05-01\n---\nUn evento.\n");

        $construccion = $this->construir($this->madrid('2026-10-09 12:00:00'));

        // Está en `blog/agenda/`, que dice `publicarEnSuFecha: no`.
        self::assertArrayHasKey('blog/proximo/index.html', $construccion->escritos);
    }

    #[Test]
    public function sinElInterruptorUnaFechaFuturaNoOcultaNada(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/blog/_datos.yml', "etiquetas: [blog]\nurl: \"/blog/{{ titulo|slug }}/\"\n");

        $construccion = $this->construir($this->madrid('2026-10-09 12:00:00'));

        self::assertArrayHasKey('blog/manana/index.html', $construccion->escritos);
    }

    #[Test]
    public function puedePonerseEnLaRaizDeContenidoParaTodoElSitio(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/blog/_datos.yml', "etiquetas: [blog]\nurl: \"/blog/{{ titulo|slug }}/\"\n");
        $this->crearFichero('contenido/_datos.yml', "publicarEnSuFecha: sí\n");
        $this->crearFichero('contenido/agenda.md', "---\ntitulo: Agenda\nfecha: 2027-05-01\n---\nPronto.\n");

        $hoy = $this->construir($this->madrid('2026-10-09 12:00:00'));
        $despues = $this->construir($this->madrid('2027-06-01 12:00:00'));

        self::assertArrayNotHasKey('agenda/index.html', $hoy->escritos);
        self::assertArrayNotHasKey('blog/manana/index.html', $hoy->escritos);
        self::assertArrayHasKey('agenda/index.html', $despues->escritos);
    }

    #[Test]
    public function unValorQueNoEsSiONoSeAvisaYSeTrataComoSiNoEstuviera(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/blog/_datos.yml', "etiquetas: [blog]\nurl: \"/blog/{{ titulo|slug }}/\"\npublicarEnSuFecha: pronto\n");

        $construccion = $this->construir($this->madrid('2026-10-09 12:00:00'));

        self::assertContains(
            'contenido/blog/_datos.yml:3: «publicarEnSuFecha» tiene que ser sí o no',
            array_map(strval(...), $construccion->avisos),
        );
        self::assertArrayHasKey('blog/manana/index.html', $construccion->escritos);
    }

    #[Test]
    public function enVariosIdiomasCadaTraduccionMiraSuFecha(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\nidiomas:\n  - codigo: es\n  - codigo: eu\n");
        $this->crearFichero('plantillas/pagina.twig', '{% for codigo, t in pagina.traducciones %}{{ codigo }}={{ t.url }};{% endfor %}');
        $this->crearFichero('contenido/blog/_datos.yml', "etiquetas: [blog]\nurl: \"/blog/{{ titulo|slug }}/\"\npublicarEnSuFecha: sí\n");
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\nfecha: 2026-09-01\n---\nUno.\n");
        $this->crearFichero('contenido/blog/uno.eu.md', "---\ntitulo: Bat\nfecha: 2027-01-01\n---\nBat.\n");

        $hoy = $this->construir($this->madrid('2026-10-09 12:00:00'));
        $despues = $this->construir($this->madrid('2027-02-01 12:00:00'));

        self::assertSame('es=/blog/uno/;', $hoy->escritos['blog/uno/index.html']);
        self::assertArrayNotHasKey('eu/blog/bat/index.html', $hoy->escritos);
        self::assertSame('es=/blog/uno/;eu=/eu/blog/bat/;', $despues->escritos['blog/uno/index.html']);
        self::assertArrayHasKey('eu/blog/bat/index.html', $despues->escritos);
    }

    #[Test]
    public function unaEtiquetaQueSoloUsanPaginasProgramadasNoTienePaginaHastaSuDia(): void
    {
        $this->crearSitioConEtiquetas();

        $hoy = $this->construir($this->madrid('2026-10-09 12:00:00'));
        $despues = $this->construir($this->madrid('2026-10-10 00:00:00'));

        self::assertSame(['tema/ideas/index.html'], $this->paginasGeneradas($hoy));
        self::assertSame(['tema/ideas/index.html', 'tema/novedades/index.html'], $this->paginasGeneradas($despues));
    }

    #[Test]
    public function laIncrementalRehaceSoloLoQueSaleAlLlegarSuDiaYDaLoMismoQueUnaCompleta(): void
    {
        $this->crearSitioConEtiquetas();
        $constructor = new Constructor(incremental: true);
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $hoy = $this->madrid('2026-10-09 12:00:00');
        $manana = $this->madrid('2026-10-10 00:00:00');

        $primera = $constructor->construir($proyecto, $hoy);
        self::assertSame(3, $primera->rehechas);

        $igual = $constructor->construir($proyecto, $hoy);
        self::assertSame(0, $igual->rehechas);

        // Llega el día: la página, su etiqueta nueva y el índice, que lista el blog.
        $llega = $constructor->construir($proyecto, $manana);
        self::assertSame(3, $llega->rehechas);
        self::assertSame((new Constructor())->construir($proyecto, $manana)->escritos, $llega->escritos);

        // Quitar el interruptor lo cambia todo en el blog.
        $this->crearFichero('contenido/blog/_datos.yml', "etiquetas: [blog]\nurl: \"/blog/{{ titulo|slug }}/\"\n");
        $sinInterruptor = $constructor->construir($proyecto, $hoy);
        self::assertSame((new Constructor())->construir($proyecto, $hoy)->escritos, $sinInterruptor->escritos);
        self::assertContains('tema/novedades/index.html', array_keys($sinInterruptor->escritos));
    }

    /**
     * Un blog que se escribe por adelantado: el interruptor está en la carpeta
     * y cada página tiene una fecha o un `publicar` distintos. El día de las
     * pruebas es el 9 de octubre de 2026.
     */
    private function crearSitio(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\nzonaHoraria: Europe/Madrid\nfeed:\n  coleccion: blog\n");
        $this->crearFichero('plantillas/pagina.twig', '{{ pagina.contenido }}');
        $this->crearFichero(
            'contenido/index.twig',
            "---\ntitulo: Inicio\n---\n{% for articulo in coleccion('blog')|orden('titulo') %}{{ articulo.titulo }};{% endfor %}",
        );
        $this->crearFichero('contenido/blog/_datos.yml', "etiquetas: [blog]\nurl: \"/blog/{{ titulo|slug }}/\"\npublicarEnSuFecha: sí\n");
        $this->crearFichero('contenido/blog/agenda/_datos.yml', "publicarEnSuFecha: no\n");
        $this->crearFichero('contenido/blog/agenda/evento.md', "---\ntitulo: Evento\nfecha: 2027-05-01\n---\nUn evento.\n");
        $this->crearFichero('contenido/blog/pasada.md', "---\ntitulo: Pasada\nfecha: 2026-09-01\n---\nPasada.\n");
        $this->crearFichero('contenido/blog/hoy.md', "---\ntitulo: Hoy\nfecha: 2026-10-09\n---\nHoy.\n");
        $this->crearFichero('contenido/blog/manana.md', "---\ntitulo: Manana\nfecha: 2026-10-10\n---\nMañana.\n");
        $this->crearFichero('contenido/blog/lejana.md', "---\ntitulo: Lejana\nfecha: 2027-01-01\npublicar: 2026-09-01\n---\nCon publicar anterior.\n");
        $this->crearFichero('contenido/blog/retenida.md', "---\ntitulo: Retenida\nfecha: 2026-09-01\npublicar: 2027-01-01\n---\nCon publicar posterior.\n");
        $this->crearFichero('contenido/blog/sin-fecha.md', "---\ntitulo: Sin fecha\n---\nSin fecha.\n");
        $this->crearFichero('contenido/blog/borrador.md', "---\ntitulo: Borrador\nfecha: 2026-09-01\nborrador: sí\n---\nSin terminar.\n");
    }

    /**
     * Dos artículos con una etiqueta cada uno, el segundo programado para
     * mañana, y la página que genera una por etiqueta. Salen tres páginas: el
     * índice, el artículo de hoy y su etiqueta.
     */
    private function crearSitioConEtiquetas(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\nzonaHoraria: Europe/Madrid\n");
        $this->crearFichero('plantillas/pagina.twig', '{{ pagina.contenido }}');
        $this->crearFichero('plantillas/tema.twig', '{% for articulo in pagina.paginas %}{{ articulo.titulo }};{% endfor %}');
        $this->crearFichero(
            'contenido/index.twig',
            "---\ntitulo: Inicio\n---\n{% for articulo in coleccion('blog')|orden('titulo') %}{{ articulo.titulo }};{% endfor %}",
        );
        $this->crearFichero('contenido/blog/_datos.yml', "etiquetas: [blog]\nurl: \"/blog/{{ titulo|slug }}/\"\npublicarEnSuFecha: sí\n");
        $this->crearFichero('contenido/blog/pasada.md', "---\ntitulo: Pasada\nfecha: 2026-09-01\netiquetas: [ideas]\n---\nPasada.\n");
        $this->crearFichero('contenido/blog/manana.md', "---\ntitulo: Manana\nfecha: 2026-10-10\netiquetas: [novedades]\n---\nMañana.\n");
        $this->crearFichero(
            'contenido/tema.md',
            "---\nplantilla: tema\nagrupar:\n  coleccion: blog\n  campo: etiquetas\n  excluir: [blog]\nurl: \"/tema/{{ grupo|slug }}/\"\n---\n",
        );
    }

    /**
     * @return list<string>
     */
    private function paginasDelBlog(Construccion $construccion): array
    {
        return $this->ficherosQueEmpiezanPor($construccion, 'blog/');
    }

    /**
     * @return list<string>
     */
    private function paginasGeneradas(Construccion $construccion): array
    {
        return $this->ficherosQueEmpiezanPor($construccion, 'tema/');
    }

    /**
     * @return list<string>
     */
    private function ficherosQueEmpiezanPor(Construccion $construccion, string $carpeta): array
    {
        $ficheros = array_values(array_filter(array_keys($construccion->escritos), fn (string $fichero) => str_starts_with($fichero, $carpeta)));
        sort($ficheros);

        return $ficheros;
    }

    private function madrid(string $momento): \DateTimeImmutable
    {
        return new \DateTimeImmutable($momento, new \DateTimeZone('Europe/Madrid'));
    }

    private function construir(\DateTimeImmutable $ahora): Construccion
    {
        return (new Constructor())->construir(Proyecto::abrir($this->carpetaTemporal()), $ahora);
    }
}
