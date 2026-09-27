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
 * Un sitio en dos idiomas compilado entero (formato §15): URL con prefijo,
 * traducciones, selector de idioma, colecciones y datos de cada idioma,
 * fechas, sitemap y un feed por idioma.
 */
final class MultiidiomaPrueba extends TestCase
{
    use CarpetaTemporal;

    #[Test]
    public function cadaPaginaSaleEnSuIdiomaYConSuPrefijo(): void
    {
        $this->crearSitio();

        $informe = $this->compilar();

        self::assertSame([], array_map(strval(...), $informe->avisos));
        self::assertSame([
            '404.html',
            'aviso-legal/index.html',
            'blog/primer-articulo/index.html',
            'blog/segundo/index.html',
            'eu/404.html',
            'eu/blog/lehen-artikulua/index.html',
            'eu/feed.xml',
            'eu/index.html',
            'eu/lege-oharra/index.html',
            'eu/zerbitzuak/index.html',
            'feed.xml',
            'index.html',
            'servicios/index.html',
            'sitemap.xml',
        ], $this->ficherosDeSalida());
    }

    #[Test]
    public function lasPlantillasVenElIdiomaYLasTraducciones(): void
    {
        $this->crearSitio();
        $this->compilar();

        $portada = $this->leerFichero('salida/eu/index.html');

        self::assertStringContainsString('<html lang="eu">', $portada);
        self::assertStringContainsString(
            "<link rel=\"alternate\" hreflang=\"es\" href=\"https://www.ejemplo.com/\">\n"
            . "<link rel=\"alternate\" hreflang=\"eu\" href=\"https://www.ejemplo.com/eu/\">\n",
            $portada,
        );
        self::assertStringContainsString(
            '<li><a href="/" hreflang="es">Castellano</a></li><li><a href="/eu/" hreflang="eu">Euskara</a></li>',
            $portada,
        );

        // Sin traducción, el selector lleva a la portada del otro idioma.
        $segundo = $this->leerFichero('salida/blog/segundo/index.html');

        self::assertStringContainsString('<link rel="alternate" hreflang="es" href="https://www.ejemplo.com/blog/segundo/">', $segundo);
        self::assertStringNotContainsString('hreflang="eu" href', $segundo);
        self::assertStringContainsString('<li><a href="/eu/" hreflang="eu">Euskara</a></li>', $segundo);
    }

    #[Test]
    public function lasColeccionesLosDatosYLasFechasSonDelIdiomaDeLaPagina(): void
    {
        $this->crearSitio();
        $this->compilar();

        $portada = $this->leerFichero('salida/index.html');
        $atari = $this->leerFichero('salida/eu/index.html');

        self::assertStringContainsString('<main>Primer artículo, 18 de marzo de 2025|Segundo, 1 de abril de 2025| 1 en euskera, 3 en total</main>', $portada);
        self::assertStringContainsString('<main>Lehen artikulua, 2025eko martxoaren 18a| 1 en euskera, 3 en total</main>', $atari);

        self::assertStringContainsString('<nav><a href="/" class="activo">Inicio</a><a href="/blog/">Blog</a></nav>', $portada);
        self::assertStringContainsString('<nav><a href="/eu/" class="activo">Hasiera</a></nav>', $atari);
        self::assertStringContainsString('<nav><a href="/eu/">Hasiera</a></nav>', $this->leerFichero('salida/eu/blog/lehen-artikulua/index.html'));

        self::assertStringContainsString('<footer>Centro de día · De lunes a viernes</footer>', $portada);
        self::assertStringContainsString('<footer>Centro de día · Astelehenetik ostiralera</footer>', $atari);
        self::assertStringContainsString('<p>Astelehenetik ostiralera</p>', $this->leerFichero('salida/eu/lege-oharra/index.html'));
    }

    #[Test]
    public function losCamposComunesLlegaALasDosVersionesYNoSeCopian(): void
    {
        $this->crearSitio();
        $this->compilar();

        self::assertStringContainsString('<img src="/img/comedor.jpg">', $this->leerFichero('salida/servicios/index.html'));
        self::assertStringContainsString('<img src="/img/comedor.jpg">', $this->leerFichero('salida/eu/zerbitzuak/index.html'));
        self::assertFileDoesNotExist($this->carpetaTemporal() . '/salida/servicios.yml');
    }

    #[Test]
    public function elSitemapTieneLosDosIdiomasSinSusPaginasDeError(): void
    {
        $this->crearSitio();
        $this->compilar();

        preg_match_all('#<loc>(.*?)</loc>#', $this->leerFichero('salida/sitemap.xml'), $direcciones);

        self::assertSame([
            'https://www.ejemplo.com/eu/blog/lehen-artikulua/',
            'https://www.ejemplo.com/blog/primer-articulo/',
            'https://www.ejemplo.com/blog/segundo/',
            'https://www.ejemplo.com/eu/',
            'https://www.ejemplo.com/',
            'https://www.ejemplo.com/eu/zerbitzuak/',
            'https://www.ejemplo.com/servicios/',
        ], $direcciones[1]);
    }

    #[Test]
    public function hayUnFeedPorIdioma(): void
    {
        $this->crearSitio();
        $this->compilar();

        $feed = $this->leerFichero('salida/feed.xml');
        $jarioa = $this->leerFichero('salida/eu/feed.xml');

        self::assertStringContainsString('<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="es">', $feed);
        self::assertSame(2, substr_count($feed, '<entry>'));
        self::assertStringContainsString('<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="eu">', $jarioa);
        self::assertStringContainsString("  <link href=\"https://www.ejemplo.com/eu/feed.xml\" rel=\"self\"/>\n  <link href=\"https://www.ejemplo.com/eu/\"/>\n", $jarioa);
        self::assertStringContainsString('<id>https://www.ejemplo.com/eu/</id>', $jarioa);
        self::assertSame(1, substr_count($jarioa, '<entry>'));
        self::assertStringContainsString('<title>Lehen artikulua</title>', $jarioa);
    }

    #[Test]
    public function unIdiomaSinEntradasNoTieneFeedPeroElPredeterminadoSi(): void
    {
        $this->crearSitio();
        unlink($this->carpetaTemporal() . '/contenido/blog/primero.eu.md');
        unlink($this->carpetaTemporal() . '/contenido/blog/primero.md');
        unlink($this->carpetaTemporal() . '/contenido/blog/segundo.md');

        $this->compilar();

        self::assertStringNotContainsString('<entry>', $this->leerFichero('salida/feed.xml'));
        self::assertFileDoesNotExist($this->carpetaTemporal() . '/salida/eu/feed.xml');
    }

    #[Test]
    public function avisaDeUnIdiomaSinPortadaYDeUnaColeccionEnUnIdiomaQueNoExiste(): void
    {
        $this->crearSitio();
        unlink($this->carpetaTemporal() . '/contenido/index.eu.twig');
        $this->crearFichero('contenido/blog/segundo.md', "---\ntitulo: Segundo\nfecha: 2025-04-01\n---\n{{ coleccion('blog', 'fr')|length }}");
        $this->crearFichero('plantillas/articulo.twig', "{{ coleccion('blog', 'fr')|length }}{{ pagina.contenido }}");
        $this->crearFichero('contenido/blog/_datos.yml', "etiquetas: [blog]\nurl: \"/blog/{{ titulo|slug }}/\"\nplantilla: articulo\n");

        $avisos = array_map(strval(...), $this->compilar()->avisos);

        self::assertSame([
            "coleccion('blog', 'fr'): «fr» no es un idioma del sitio; la lista sale vacía",
            'sitio.yml: Ninguna página tiene la URL /eu/, la portada en «eu»: el selector de idioma llevaría a una dirección que no existe',
        ], array_values(array_unique($avisos)));
    }

    private function crearSitio(): void
    {
        $ficheros = [
            'sitio.yml' => <<<'YAML'
                nombre: Centro de día
                url: https://www.ejemplo.com
                zonaHoraria: Europe/Madrid
                idiomas:
                  - codigo: es
                    nombre: Castellano
                  - codigo: eu
                    nombre: Euskara
                feed:
                  coleccion: blog

                YAML,
            'datos/menus.yml' => "principal:\n  - { texto: Inicio, url: / }\n  - { texto: Blog, url: /blog/ }\n",
            'datos/menus.eu.yml' => "principal:\n  - { texto: Hasiera, url: /eu/ }\n",
            'datos/cliente.yml' => "nombre: Centro de día\nhorario: De lunes a viernes\n",
            'datos/cliente.eu.yml' => "horario: Astelehenetik ostiralera\n",
            'plantillas/pagina.twig' => <<<'TWIG'
                <html lang="{{ pagina.idioma }}">
                {% for codigo, traduccion in pagina.traducciones %}<link rel="alternate" hreflang="{{ codigo }}" href="{{ sitio.url }}{{ traduccion.url }}">
                {% endfor %}
                <nav>{% for enlace in datos.menus.principal %}<a href="{{ enlace.url }}"{{ activo(enlace.url) ? ' class="activo"' }}>{{ enlace.texto }}</a>{% endfor %}</nav>
                <ul>{% for idioma in sitio.idiomas %}{% set traduccion = pagina.traducciones[idioma.codigo] %}<li><a href="{{ traduccion ? traduccion.url : idioma.url }}" hreflang="{{ idioma.codigo }}">{{ idioma.nombre }}</a></li>{% endfor %}</ul>
                {% if pagina.imagen %}<img src="/{{ pagina.imagen }}">{% endif %}
                <main>{{ pagina.contenido }}</main>
                <footer>{{ datos.cliente.nombre }} · {{ datos.cliente.horario }}</footer>

                TWIG,
            'contenido/index.twig' => "---\ntitulo: Inicio\n---\n" . self::portada(),
            'contenido/index.eu.twig' => "---\ntitulo: Hasiera\n---\n" . self::portada(),
            'contenido/blog/_datos.yml' => "etiquetas: [blog]\nurl: \"/blog/{{ titulo|slug }}/\"\n",
            'contenido/blog/primero.md' => "---\ntitulo: Primer artículo\nfecha: 2025-03-18\n---\nHola.\n",
            'contenido/blog/primero.eu.md' => "---\ntitulo: Lehen artikulua\nfecha: 2025-03-18\n---\nKaixo.\n",
            'contenido/blog/segundo.md' => "---\ntitulo: Segundo\nfecha: 2025-04-01\n---\nOtra vez.\n",
            'contenido/servicios.yml' => "imagen: img/comedor.jpg\n",
            'contenido/servicios.md' => "---\ntitulo: Servicios\n---\nComedor.\n",
            'contenido/servicios.eu.md' => "---\ntitulo: Zerbitzuak\nurl: /zerbitzuak/\n---\nJangela.\n",
            'contenido/aviso-legal.md' => "---\ntitulo: Aviso legal\nlistada: no\n---\n[dato clave=\"cliente.horario\"]\n",
            'contenido/aviso-legal.eu.md' => "---\ntitulo: Lege oharra\nurl: /lege-oharra/\nlistada: no\n---\n[dato clave=\"cliente.horario\"]\n",
            'contenido/404.md' => "---\ntitulo: No está\nurl: /404.html\n---\nNo está.\n",
            'contenido/404.eu.md' => "---\ntitulo: Ez dago\nurl: /404.html\n---\nEz dago.\n",
        ];

        foreach ($ficheros as $ruta => $contenido) {
            $this->crearFichero($ruta, $contenido);
        }
    }

    /**
     * La misma portada en los dos idiomas: los artículos de su idioma, y
     * cuántos hay en euskera y en todos.
     */
    private static function portada(): string
    {
        return "{% for articulo in coleccion('blog') %}{{ articulo.titulo }}, {{ articulo.fecha|fecha }}|{% endfor %}"
            . " {{ coleccion('blog', 'eu')|length }} en euskera, {{ coleccion('blog', idioma: 'todos')|length }} en total";
    }

    /**
     * @return list<string>
     */
    private function ficherosDeSalida(): array
    {
        $salida = $this->carpetaTemporal() . '/salida';
        $ficheros = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($salida, \FilesystemIterator::SKIP_DOTS)) as $fichero) {
            $ficheros[] = str_replace('\\', '/', substr((string) $fichero, strlen($salida) + 1));
        }

        sort($ficheros);

        return $ficheros;
    }

    private function compilar(): Informe
    {
        return (new CompiladorEnProceso())->compilar(Proyecto::abrir($this->carpetaTemporal()));
    }
}
