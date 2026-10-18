<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\CompiladorEnProceso;
use Ehundu\Informe;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * El título del feed en un sitio con varios idiomas (formato §10.2 y §15.10, decisión 150): un texto vale para todos, o
 * hay uno por idioma con su código como clave; un idioma sin título lleva el nombre del sitio.
 */
final class FeedPorIdiomaPrueba extends TestCase
{
    use CarpetaTemporal;

    #[Test]
    public function unTextoVaParaTodosLosIdiomasSinAvisar(): void
    {
        $this->crearSitio("  titulo: Ehundu\n");

        $informe = $this->compilar();

        self::assertSame([], array_map(strval(...), $informe->avisos));
        self::assertSame('Ehundu', $this->tituloDelFeed('salida/feed.xml'));
        self::assertSame('Ehundu', $this->tituloDelFeed('salida/eu/feed.xml'));
    }

    #[Test]
    public function unTextoPorIdiomaDaACadaFeedElSuyo(): void
    {
        $this->crearSitio("  titulo:\n    es: Novedades de la casa\n    eu: Etxeko berriak\n");

        $informe = $this->compilar();

        self::assertSame([], array_map(strval(...), $informe->avisos));
        self::assertSame('Novedades de la casa', $this->tituloDelFeed('salida/feed.xml'));
        self::assertSame('Etxeko berriak', $this->tituloDelFeed('salida/eu/feed.xml'));
    }

    #[Test]
    public function sinTituloElFeedLlevaElNombreDelSitio(): void
    {
        $this->crearSitio('');

        $informe = $this->compilar();

        self::assertSame([], array_map(strval(...), $informe->avisos));
        self::assertSame('Centro de día', $this->tituloDelFeed('salida/feed.xml'));
        self::assertSame('Centro de día', $this->tituloDelFeed('salida/eu/feed.xml'));
    }

    #[Test]
    public function unIdiomaSinTituloLlevaElNombreDelSitioYSeAvisa(): void
    {
        $this->crearSitio("  titulo:\n    es: Novedades de la casa\n");

        $avisos = array_map(strval(...), $this->compilar()->avisos);

        self::assertSame(
            ['sitio.yml: «feed»: «titulo» no tiene texto en «eu»; el feed de ese idioma se titula como el sitio'],
            array_values(array_unique($avisos)),
        );
        self::assertSame('Novedades de la casa', $this->tituloDelFeed('salida/feed.xml'));
        self::assertSame('Centro de día', $this->tituloDelFeed('salida/eu/feed.xml'));
    }

    #[Test]
    public function unTituloParaUnIdiomaQueElSitioNoTieneSeAvisaYSeIgnora(): void
    {
        $this->crearSitio("  titulo:\n    es: Novedades\n    eu: Berriak\n    fr: Nouvelles\n");

        $avisos = array_map(strval(...), $this->compilar()->avisos);

        self::assertSame(
            ['sitio.yml: «feed»: «titulo» tiene un texto para «fr», que no es un idioma del sitio; se ignora'],
            array_values(array_unique($avisos)),
        );
        self::assertSame('Novedades', $this->tituloDelFeed('salida/feed.xml'));
        self::assertSame('Berriak', $this->tituloDelFeed('salida/eu/feed.xml'));
    }

    #[Test]
    public function unTituloQueNoEsTextoSeAvisaYElFeedLlevaElNombreDelSitio(): void
    {
        $this->crearSitio("  titulo: [Novedades, Berriak]\n");

        $avisos = array_map(strval(...), $this->compilar()->avisos);

        self::assertSame(
            ['sitio.yml: «feed»: «titulo» tiene que ser un texto, o un texto por idioma con su código como clave; se ignora'],
            array_values(array_unique($avisos)),
        );
        self::assertSame('Centro de día', $this->tituloDelFeed('salida/feed.xml'));
    }

    #[Test]
    public function unTituloDeUnValorQueNoEsTextoEnUnIdiomaSeAvisaYEseIdiomaLlevaElNombreDelSitio(): void
    {
        $this->crearSitio("  titulo:\n    es: Novedades\n    eu: 5\n");

        $avisos = array_map(strval(...), $this->compilar()->avisos);

        self::assertSame(
            ['sitio.yml: «feed»: el «titulo» de «eu» tiene que ser un texto; el feed de ese idioma se titula como el sitio'],
            array_values(array_unique($avisos)),
        );
        self::assertSame('Centro de día', $this->tituloDelFeed('salida/eu/feed.xml'));
    }

    #[Test]
    public function enUnSitioDeUnSoloIdiomaUnTextoSigueValiendo(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Un blog\nurl: https://ejemplo.com\nfeed:\n  coleccion: blog\n  titulo: Las novedades\n");
        $this->crearFichero('plantillas/pagina.twig', '{{ pagina.contenido }}');
        $this->crearFichero('contenido/blog/_datos.yml', "etiquetas: [blog]\nurl: \"/blog/{{ titulo|slug }}/\"\n");
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\nfecha: 2025-03-18\n---\nHola.\n");

        $informe = $this->compilar();

        self::assertSame([], array_map(strval(...), $informe->avisos));
        self::assertSame('Las novedades', $this->tituloDelFeed('salida/feed.xml'));
    }

    /**
     * Un sitio en castellano y euskera con una entrada del blog en cada idioma y un feed cuya sección `titulo` se
     * escribe aquí tal cual (con su sangría, o nada).
     */
    private function crearSitio(string $titulo): void
    {
        $this->crearFichero(
            'sitio.yml',
            "nombre: Centro de día\nurl: https://www.ejemplo.com\nidiomas:\n  - codigo: es\n    nombre: Castellano\n  - codigo: eu\n    nombre: Euskara\nfeed:\n  coleccion: blog\n{$titulo}",
        );
        $this->crearFichero('plantillas/pagina.twig', '{{ pagina.contenido }}');
        $this->crearFichero('contenido/index.md', "---\ntitulo: Inicio\n---\nHola.\n");
        $this->crearFichero('contenido/index.eu.md', "---\ntitulo: Hasiera\n---\nKaixo.\n");
        $this->crearFichero('contenido/blog/_datos.yml', "etiquetas: [blog]\nurl: \"/blog/{{ titulo|slug }}/\"\n");
        $this->crearFichero('contenido/blog/primero.md', "---\ntitulo: Primer artículo\nfecha: 2025-03-18\n---\nHola.\n");
        $this->crearFichero('contenido/blog/primero.eu.md', "---\ntitulo: Lehen artikulua\nfecha: 2025-03-18\n---\nKaixo.\n");
    }

    /**
     * El `<title>` del propio feed, que es el primero del fichero (los de las entradas van dentro de `<entry>`).
     */
    private function tituloDelFeed(string $ruta): string
    {
        self::assertSame(1, preg_match('~^<feed [^>]*>\s*<title>(.*?)</title>~s', substr($this->leerFichero($ruta), 39), $partes), "El feed {$ruta} no tiene título al principio");

        return $partes[1];
    }

    private function compilar(): Informe
    {
        return (new CompiladorEnProceso())->compilar(Proyecto::abrir($this->carpetaTemporal()));
    }
}
