<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\ErrorDeProyecto;
use Ehundu\Lector;
use Ehundu\Lectura;
use Ehundu\Pagina;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LectorPrueba extends TestCase
{
    use CarpetaTemporal;

    #[Test]
    public function fallaSinSitioYml(): void
    {
        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('Falta sitio.yml en la raíz del proyecto');

        $this->leer();
    }

    #[Test]
    public function fallaSinNombre(): void
    {
        $this->crearFichero('sitio.yml', "url: https://ejemplo.com\n");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('sitio.yml: Falta el campo «nombre»');

        $this->leer();
    }

    #[Test]
    public function fallaSinUrl(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Librería La Esquina\n");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('sitio.yml: Falta el campo «url»');

        $this->leer();
    }

    #[Test]
    public function fallaSiLaUrlNoEsCompleta(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Librería La Esquina\nurl: www.ejemplo.com\n");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('sitio.yml:2: «url» tiene que ser la dirección completa del sitio, con http:// o https://');

        $this->leer();
    }

    #[Test]
    public function leeElSitioSinLaBarraFinalNiElDespliegue(): void
    {
        $this->crearFichero('sitio.yml', <<<'YAML'
            nombre: Librería La Esquina
            url: https://www.ejemplo.com/
            idioma: es
            despliegue:
              destino: sftp
              servidor: ftp.ejemplo.com
            YAML);

        $sitio = $this->leer()->sitio;

        self::assertSame('Librería La Esquina', $sitio->nombre);
        self::assertSame('https://www.ejemplo.com', $sitio->url);
        self::assertSame(
            ['nombre' => 'Librería La Esquina', 'url' => 'https://www.ejemplo.com', 'idioma' => 'es'],
            $sitio->campos,
        );
    }

    #[Test]
    public function laZonaHorariaEsLaDelSitioOUtc(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Librería La Esquina\nurl: https://www.ejemplo.com\nzonaHoraria: Europe/Madrid\n");
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\nfecha: 2025-03-18\n---\n");

        $lectura = $this->leer();

        self::assertSame('Europe/Madrid', $lectura->sitio->zonaHoraria->getName());
        self::assertSame('2025-03-18 00:00:00 Europe/Madrid', $lectura->paginas[0]->campos['fecha']->format('Y-m-d H:i:s e'));
    }

    #[Test]
    public function sinZonaHorariaEsUtc(): void
    {
        $this->crearSitioMinimo();

        self::assertSame('UTC', $this->leer()->sitio->zonaHoraria->getName());
    }

    #[Test]
    public function fallaConUnaZonaHorariaQueNoExiste(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Librería La Esquina\nurl: https://www.ejemplo.com\nzonaHoraria: Madrid\n");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('sitio.yml:3: «zonaHoraria» no es una zona horaria válida');

        $this->leer();
    }

    #[Test]
    public function leeLosDatosPorNombre(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('datos/cliente.yml', "nombre: La Esquina\ntelefonos: [900000000]");
        $this->crearFichero('datos/menus.json', '[{"texto": "Inicio", "url": "/"}]');

        $lectura = $this->leer();

        self::assertSame(
            ['cliente' => ['nombre' => 'La Esquina', 'telefonos' => [900000000]], 'menus' => [['texto' => 'Inicio', 'url' => '/']]],
            $lectura->datos,
        );
        self::assertSame([], $lectura->avisos);
    }

    #[Test]
    public function avisaDeLoQueNoSeLeeEnDatos(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('datos/images.js', 'export default [];');
        $this->crearFichero('datos/menus.es.yml', 'a: 1');
        $this->crearFichero('datos/menus/principal.yml', 'a: 1');
        $this->crearFichero('datos/.gitkeep', '');

        $lectura = $this->leer();

        self::assertSame([], $lectura->datos);
        self::assertSame([
            'datos/images.js: En datos/ solo se leen ficheros .yml y .json; este se ignora',
            'datos/menus: Las subcarpetas de datos/ no se leen',
            'datos/menus.es.yml: El punto en el nombre está reservado para el idioma (formato §15.5); este fichero no se lee',
        ], $this->avisos($lectura));
    }

    #[Test]
    public function siUnDatoSaleDeYmlYDeJsonGanaElYml(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('datos/cliente.json', '{"nombre": "json"}');
        $this->crearFichero('datos/cliente.yml', 'nombre: yml');

        $lectura = $this->leer();

        self::assertSame(['cliente' => ['nombre' => 'yml']], $lectura->datos);
        self::assertSame(
            ['datos/cliente.json: «datos.cliente» sale también de datos/cliente.yml; este fichero se ignora'],
            $this->avisos($lectura),
        );
    }

    #[Test]
    public function explicaUnJsonMalEscrito(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('datos/cliente.json', '{"nombre": "La Esquina",}');

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('datos/cliente.json: El JSON no es válido: hay un error de sintaxis');

        $this->leer();
    }

    #[Test]
    public function leeSoloLasPaginasYLasDevuelveEnOrden(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/index.md', '# Inicio');
        $this->crearFichero('contenido/contacto.twig', '<h1>Contacto</h1>');
        $this->crearFichero('contenido/blog/b.md', 'B');
        $this->crearFichero('contenido/blog/a.md', 'A');
        $this->crearFichero('contenido/blog/_datos.yml', 'etiquetas: [blog]');
        $this->crearFichero('contenido/img/foto.jpg', 'jpg');
        $this->crearFichero('contenido/.borrador.md', 'oculto');
        $this->crearFichero('contenido/.git/config', 'oculto');

        $paginas = $this->leer()->paginas;

        self::assertSame(
            ['blog/a.md', 'blog/b.md', 'contacto.twig', 'index.md'],
            array_map(fn (Pagina $pagina) => $pagina->ruta, $paginas),
        );
        self::assertSame(['md', 'md', 'twig', 'md'], array_map(fn (Pagina $pagina) => $pagina->formato, $paginas));
    }

    #[Test]
    public function losDemasFicherosDeContenidoSeCopianSalvoLosReservadosYLosDeOtrosGeneradores(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/blog/foto.jpg', 'jpg');
        $this->crearFichero('contenido/blog/_notas.txt', 'privado');
        $this->crearFichero('contenido/index.njk', '{{ content }}');
        $this->crearFichero('contenido/blog/blog.11tydata.js', 'export default {};');
        $this->crearFichero('contenido/datos.11tydata.json', '{}');
        $this->crearFichero('contenido/lista.liquid', '{{ x }}');

        $lectura = $this->leer();

        self::assertSame(['blog/foto.jpg'], $lectura->ficheros);
        self::assertSame([
            'contenido/datos.11tydata.json: Es una plantilla o unos datos de otro generador; no se copia a salida/',
            'contenido/index.njk: Es una plantilla o unos datos de otro generador; no se copia a salida/',
            'contenido/lista.liquid: Es una plantilla o unos datos de otro generador; no se copia a salida/',
            'contenido/blog/blog.11tydata.js: Es una plantilla o unos datos de otro generador; no se copia a salida/',
        ], $this->avisos($lectura));
    }

    #[Test]
    public function combinaLaCascadaConElFrontMatter(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/blog/_datos.yml', "plantilla: articulo\ntags: [blog]\nurl: \"/blog/{{ titulo|slug }}/\"");
        $this->crearFichero('contenido/blog/novelas-de-otono.md', <<<'MD'
            ---
            title: Diez novelas para leer en otoño
            date: 2025-03-18
            tags: ["novela", "otono", "recomendaciones"]
            imgmain: blog/otono.jpg
            ---

            Llega el otoño…
            MD);

        $pagina = $this->leer()->paginas[0];

        self::assertSame('Diez novelas para leer en otoño', $pagina->campos['titulo']);
        self::assertSame('articulo', $pagina->campos['plantilla']);
        self::assertSame('/blog/{{ titulo|slug }}/', $pagina->campos['url']);
        self::assertSame('/blog/diez-novelas-para-leer-en-otono/', $pagina->url);
        self::assertSame(['blog', 'novela', 'otono', 'recomendaciones'], $pagina->campos['etiquetas']);
        self::assertSame('blog/otono.jpg', $pagina->campos['imgmain']);
        self::assertSame('2025-03-18', $pagina->campos['fecha']->format('Y-m-d'));
        self::assertSame("\nLlega el otoño…", $pagina->cuerpo);
        self::assertSame(7, $pagina->lineaCuerpo);
    }

    #[Test]
    public function leeFicherosConBomYFinalesDeLineaDeWindows(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/index.md', "\u{FEFF}---\r\ntitulo: Inicio\r\n---\r\nCuerpo\r\n");

        $pagina = $this->leer()->paginas[0];

        self::assertSame(['titulo' => 'Inicio'], $pagina->campos);
        self::assertSame("Cuerpo\n", $pagina->cuerpo);
    }

    #[Test]
    public function avisaYSaltaLasPaginasConPuntoEnElNombre(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/contacto.en.md', 'Contact');
        $this->crearFichero('contenido/sitemap.xml.twig', '<urlset/>');

        $lectura = $this->leer();

        self::assertSame([], $lectura->paginas);
        self::assertSame([
            'contenido/contacto.en.md: El punto en el nombre está reservado para el idioma (formato §15.5); este fichero no se compila',
            'contenido/sitemap.xml.twig: El punto en el nombre está reservado para el idioma (formato §15.5); este fichero no se compila',
        ], $this->avisos($lectura));
    }

    #[Test]
    public function losAvisosDeUnaPaginaLlevanSuRutaYLinea(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\nfecha: 2025-02-31\n---\n");

        self::assertSame(
            ['contenido/blog/uno.md:3: «fecha» no es una fecha posible: 2025-02-31'],
            $this->avisos($this->leer()),
        );
    }

    #[Test]
    public function unFrontMatterMalEscritoDetieneLaLectura(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/libros/novedades.md', "---\ntitulo: Novedades: otoño\n---\n");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('contenido/libros/novedades.md:2: El YAML no es válido: hay dos puntos');

        $this->leer();
    }

    #[Test]
    public function avisaSiNoHayCarpetaDeContenido(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\n");

        $lectura = $this->leer();

        self::assertSame([], $lectura->paginas);
        self::assertSame(['No hay carpeta contenido/: no se genera ninguna página'], $this->avisos($lectura));
    }

    private function leer(): Lectura
    {
        return (new Lector())->leer(Proyecto::abrir($this->carpetaTemporal()));
    }

    /**
     * @return list<string>
     */
    private function avisos(Lectura $lectura): array
    {
        return array_map(strval(...), $lectura->avisos);
    }
}
