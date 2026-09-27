<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Construccion;
use Ehundu\Constructor;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use Ehundu\Pruebas\Apoyo\Png;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * La construcción incremental tiene que dar siempre lo mismo que una
 * completa. Cada prueba cambia el proyecto de muchas maneras y, después de
 * cada cambio, compara fichero a fichero las dos construcciones.
 */
final class ConstructorIncrementalPrueba extends TestCase
{
    use CarpetaTemporal;

    private const string AHORA = '2026-01-01 12:00:00';

    /** @var array<string, string> el proyecto tal como debe quedar en disco */
    private array $ficheros = [];

    #[Test]
    public function cadaCambioRehaceSoloLoQueLeAfectaYDaLoMismoQueUnaConstruccionCompleta(): void
    {
        $this->crearSitio();
        $constructor = new Constructor(incremental: true);
        $ahora = new \DateTimeImmutable(self::AHORA);

        $pasos = [
            'primera construcción' => [fn () => null, 5],
            'nada cambia' => [fn () => null, 0],
            'cambia el cuerpo de un artículo: el artículo y la portada, que lo muestra' => [
                fn () => $this->cambiar('contenido/blog/uno.md', 'Primer artículo.', 'Primer artículo, revisado.'),
                2,
            ],
            'cambia el título de un artículo: el artículo y la portada, que recorre su colección' => [
                fn () => $this->cambiar('contenido/blog/dos.md', 'titulo: Dos', 'titulo: Segundo'),
                2,
            ],
            'cambia un parcial que solo usa una página' => [
                fn () => $this->cambiar('parciales/pie.twig', 'Escríbenos', 'Llámanos'),
                1,
            ],
            'cambia, sin cambiar de tamaño, el CSS de una página' => [
                fn () => $this->cambiar('publico/dos.css', 'red', 'tan'),
                1,
            ],
            'cambia el SVG de la cabecera: todas' => [
                fn () => $this->cambiar('publico/logo.svg', 'r="1"', 'r="2"'),
                5,
            ],
            'desaparece el documento que enlaza la portada' => [fn () => $this->quitar('publico/doc.pdf'), 1],
            'vuelve con otro tamaño' => [fn () => $this->poner('publico/doc.pdf', str_repeat('PDF', 900)), 1],
            'la foto de los artículos pasa a tener medidas: ellos y la portada, que los muestra' => [
                fn () => $this->poner('publico/foto.jpg', Png::de(4, 3)),
                3,
            ],
            'y cambian sus medidas' => [fn () => $this->poner('publico/foto.jpg', Png::de(8, 3)), 3],
            'aparece un artículo: él y la portada' => [
                fn () => $this->poner('contenido/blog/tres.md', "---\ntitulo: Tres\nfecha: 2024-02-15\n---\nTercer artículo.\n"),
                2,
            ],
            'aparece un atajo: todas las páginas con Markdown' => [
                fn () => $this->poner('parciales/atajos/aviso.twig', '<aside>{{ contenido }}</aside>'),
                5,
            ],
            'cambia el atajo: la página que lo usa' => [
                fn () => $this->cambiar('parciales/atajos/aviso.twig', '<aside>', '<aside class="aviso">'),
                1,
            ],
            'cambia un fragmento que muestra la portada' => [
                fn () => $this->cambiar('contenido/avisos/cierre.md', 'agosto', 'septiembre'),
                1,
            ],
            'cambian los datos: todo' => [fn () => $this->cambiar('datos/menu.yml', 'Inicio', 'Portada'), 6],
            'cambia sitio.yml: todo' => [fn () => $this->cambiar('sitio.yml', 'nombre: Prueba', 'nombre: Otra prueba'), 6],
            'un error en la cabecera' => [fn () => $this->cambiar('parciales/cabecera.twig', '</header>', '{% if %}</header>'), null],
            'tras el error, todo' => [fn () => $this->cambiar('parciales/cabecera.twig', '{% if %}</header>', '</header>'), 6],
            'cambia la cascada de los artículos: ellos y la portada' => [
                fn () => $this->cambiar('contenido/blog/_datos.yml', 'etiquetas: [blog]', "etiquetas: [blog]\nautor: Ane"),
                4,
            ],
            'una página cambia de nombre' => [
                function (): void {
                    $this->poner('contenido/quienes.md', $this->ficheros['contenido/acerca.md']);
                    $this->quitar('contenido/acerca.md');
                },
                1,
            ],
            'aparece un fichero que se copia tal cual' => [fn () => $this->poner('contenido/blog/plano.txt', 'plano'), 0],
            'un artículo cambia de URL: él y la portada' => [
                fn () => $this->cambiar('contenido/blog/uno.md', 'titulo: Uno', "titulo: Uno\nurl: /primero/"),
                2,
            ],
            'cambia el cuerpo en Twig de una página' => [
                fn () => $this->cambiar('contenido/contacto.twig', '<p>', '<p class="intro">'),
                1,
            ],
            'se hace borrador un artículo: sale él y cambia la portada' => [
                fn () => $this->cambiar('contenido/blog/tres.md', 'titulo: Tres', "titulo: Tres\nborrador: sí"),
                1,
            ],
            'cambia la plantilla de la portada' => [
                fn () => $this->cambiar('plantillas/portada.twig', '<ul>', '<ul class="articulos">'),
                1,
            ],
        ];

        foreach ($pasos as $paso => [$cambio, $rehechas]) {
            $cambio();
            $construccion = $this->comprobar($constructor, $ahora, $paso);

            self::assertSame($rehechas, $construccion?->rehechas, "Páginas rehechas: {$paso}");
        }

        // Llega la fecha de un artículo programado: entra él y cambia la portada.
        $construccion = $this->comprobar($constructor, new \DateTimeImmutable('2031-01-01'), 'llega una fecha de publicación');
        self::assertSame(2, $construccion?->rehechas);
    }

    #[Test]
    public function unaSecuenciaDeCambiosAlAzarDaLoMismoQueUnaConstruccionCompleta(): void
    {
        $this->crearSitio();
        $constructor = new Constructor(incremental: true);
        $ahora = new \DateTimeImmutable(self::AHORA);
        $aleatorio = new \Random\Randomizer(new \Random\Engine\Mt19937(20260926));
        $cambios = $this->cambiosAlAzar($aleatorio);
        $this->comprobar($constructor, $ahora, 'primera construcción');

        for ($paso = 1; $paso <= 60; $paso++) {
            $nombre = $aleatorio->pickArrayKeys($cambios, 1)[0];
            $cambios[$nombre]();
            $this->comprobar($constructor, $ahora, "paso {$paso}: {$nombre}");
        }
    }

    #[Test]
    public function enUnSitioEnDosIdiomasCadaCambioRehaceSoloLoQueLeAfecta(): void
    {
        $this->crearSitioEnDosIdiomas();
        $constructor = new Constructor(incremental: true);
        $ahora = new \DateTimeImmutable(self::AHORA);

        $pasos = [
            'primera construcción' => [fn () => null, 7],
            'nada cambia' => [fn () => null, 0],
            'cambia el título de una traducción: ella, su versión en castellano, que la enlaza, y las dos portadas, que recorren el blog' => [
                fn () => $this->cambiar('contenido/blog/uno.eu.md', 'titulo: Bat', 'titulo: Lehena'),
                4,
            ],
            'cambia el cuerpo de una traducción: solo ella' => [
                fn () => $this->cambiar('contenido/blog/uno.eu.md', 'Lehen artikulua.', 'Lehen artikulua, berrikusia.'),
                1,
            ],
            'aparece una traducción: ella, la página que traduce y las dos portadas' => [
                fn () => $this->poner('contenido/blog/dos.eu.md', "---\ntitulo: Bi\nfecha: 2024-02-01\n---\nBigarren artikulua.\n"),
                4,
            ],
            'cambian los datos de un idioma: todo' => [fn () => $this->cambiar('datos/menu.eu.yml', 'Hasiera', 'Atari'), 8],
            'cambian los campos comunes de una página: sus dos versiones' => [
                fn () => $this->cambiar('contenido/contacto.yml', '944000000', '944111111'),
                2,
            ],
            'cambia la cascada de un idioma: sus artículos, sus traducciones y las portadas' => [
                fn () => $this->cambiar('contenido/blog/_datos.eu.yml', 'seccion: Bloga', 'seccion: Albisteak'),
                6,
            ],
            'una traducción cambia de URL: ella, su traducción y las portadas' => [
                fn () => $this->cambiar('contenido/blog/uno.eu.md', 'titulo: Lehena', "titulo: Lehena\nurl: /lehena/"),
                4,
            ],
            'desaparece una traducción: la página que traducía y las portadas' => [fn () => $this->quitar('contenido/blog/uno.eu.md'), 3],
            'una traducción pasa a borrador: la página que traducía' => [
                fn () => $this->cambiar('contenido/contacto.eu.md', 'titulo: Kontaktua', "titulo: Kontaktua\nborrador: sí"),
                1,
            ],
            'cambian los idiomas: todo' => [fn () => $this->cambiar('sitio.yml', 'nombre: Euskara', 'nombre: Euskera'), 6],
        ];

        foreach ($pasos as $paso => [$cambio, $rehechas]) {
            $cambio();
            $construccion = $this->comprobar($constructor, $ahora, $paso);

            self::assertSame($rehechas, $construccion?->rehechas, "Páginas rehechas: {$paso}");
        }
    }

    #[Test]
    public function enUnSitioEnDosIdiomasUnaSecuenciaDeCambiosAlAzarDaLoMismoQueUnaConstruccionCompleta(): void
    {
        $this->crearSitioEnDosIdiomas();
        $constructor = new Constructor(incremental: true);
        $ahora = new \DateTimeImmutable(self::AHORA);
        $aleatorio = new \Random\Randomizer(new \Random\Engine\Mt19937(20260927));
        $cambios = $this->cambiosAlAzarEnDosIdiomas($aleatorio);
        $this->comprobar($constructor, $ahora, 'primera construcción');

        for ($paso = 1; $paso <= 80; $paso++) {
            $nombre = $aleatorio->pickArrayKeys($cambios, 1)[0];
            $cambios[$nombre]();
            $this->comprobar($constructor, $ahora, "paso {$paso}: {$nombre}");
        }
    }

    #[Test]
    public function losAvisosDeLoQueSeAprovechaSeRepiten(): void
    {
        $this->crearSitio();
        $constructor = new Constructor(incremental: true);
        $ahora = new \DateTimeImmutable(self::AHORA);

        $primera = $this->comprobar($constructor, $ahora, 'primera');
        $segunda = $this->comprobar($constructor, $ahora, 'segunda');

        self::assertSame(0, $segunda?->rehechas);
        self::assertContains('contenido/acerca.md:6: [imagen] no existe publico/falta.jpg', array_map('strval', $segunda->avisos ?? []));
        self::assertEquals($primera?->avisos, $segunda?->avisos);
    }

    #[Test]
    public function olvidarOCambiarLosBorradoresRehaceTodo(): void
    {
        $this->crearSitio();
        $constructor = new Constructor(incremental: true);
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $ahora = new \DateTimeImmutable(self::AHORA);

        $constructor->construir($proyecto, $ahora);
        self::assertSame(0, $constructor->construir($proyecto, $ahora)->rehechas);

        $constructor->olvidar();
        self::assertSame(5, $constructor->construir($proyecto, $ahora)->rehechas);

        $conBorradores = $constructor->construir($proyecto, $ahora, conBorradores: true);
        self::assertSame(7, $conBorradores->rehechas);
        self::assertSame((new Constructor())->construir($proyecto, $ahora, conBorradores: true)->escritos, $conBorradores->escritos);
    }

    #[Test]
    public function unConstructorQueNoEsIncrementalRehaceSiempreTodo(): void
    {
        $this->crearSitio();
        $constructor = new Constructor();
        $proyecto = Proyecto::abrir($this->carpetaTemporal());

        $constructor->construir($proyecto);

        self::assertSame(5, $constructor->construir($proyecto, new \DateTimeImmutable(self::AHORA))->rehechas);
    }

    /**
     * Construye con el constructor incremental y con uno nuevo, y comprueba
     * que dan lo mismo: los mismos ficheros con el mismo contenido y en el
     * mismo orden, las mismas copias y los mismos avisos; o el mismo error.
     */
    private function comprobar(Constructor $incremental, \DateTimeImmutable $ahora, string $paso): ?Construccion
    {
        $proyecto = Proyecto::abrir($this->carpetaTemporal());

        try {
            $completa = (new Constructor())->construir($proyecto, $ahora);
        } catch (\Throwable $error) {
            $completa = $error;
        }

        try {
            $parcial = $incremental->construir($proyecto, $ahora);
        } catch (\Throwable $error) {
            $parcial = $error;
        }

        if ($completa instanceof \Throwable) {
            self::assertInstanceOf(\Throwable::class, $parcial, "Tenía que fallar: {$paso}");
            self::assertSame($completa->getMessage(), $parcial->getMessage(), $paso);

            return null;
        }

        self::assertInstanceOf(Construccion::class, $parcial, $parcial instanceof \Throwable ? "{$paso}: {$parcial->getMessage()}" : $paso);
        self::assertSame(array_keys($completa->escritos), array_keys($parcial->escritos), $paso);

        foreach ($completa->escritos as $fichero => $contenido) {
            self::assertSame($contenido, $parcial->escritos[$fichero], "{$paso}: {$fichero}");
        }

        self::assertSame($completa->copias, $parcial->copias, $paso);
        self::assertSame($completa->paginas, $parcial->paginas, $paso);
        self::assertSame(array_map('strval', $completa->avisos), array_map('strval', $parcial->avisos), $paso);

        return $parcial;
    }

    /**
     * Un sitio pequeño con todo lo que puede hacer que una página dependa de
     * otra cosa: plantillas que heredan e incluyen, un SVG incrustado, CSS y
     * JS por página, colecciones, contenidos de otras páginas, fragmentos,
     * atajos que miran `publico/`, datos, un artículo programado y un
     * borrador. Salen cinco páginas.
     */
    private function crearSitio(): void
    {
        $this->ficheros = [
            'sitio.yml' => "nombre: Prueba\nurl: https://ejemplo.com\nfeed:\n  coleccion: blog\n",
            'datos/menu.yml' => "- texto: Inicio\n  url: /\n- texto: Contacto\n  url: /contacto/\n",
            'plantillas/base.twig' => <<<'TWIG'
                <!doctype html>
                <title>{{ pagina.titulo }} · {{ sitio.nombre }}</title>
                <style>{{ css() }}</style>
                {% include 'parciales/cabecera.twig' %}
                <main>{% block principal %}{{ pagina.contenido }}{% endblock %}</main>
                <script>{{ js() }}</script>

                TWIG,
            'plantillas/pagina.twig' => "{% extends 'plantillas/base.twig' %}\n",
            'plantillas/portada.twig' => <<<'TWIG'
                {% extends 'plantillas/base.twig' %}
                {% block principal %}
                {{ pagina.contenido }}
                {% for aviso in coleccion('avisos') %}<div>{{ aviso.contenido }}</div>{% endfor %}
                <ul>{% for articulo in coleccion('blog')|invertir %}<li><a href="{{ articulo.url }}">{{ articulo.titulo }}</a> {{ articulo.contenido }}</li>{% endfor %}</ul>
                {% endblock %}

                TWIG,
            'parciales/cabecera.twig' => <<<'TWIG'
                <header>{{ svg('logo.svg') }}{{ css('base.css') }}
                {% for enlace in datos.menu %}<a href="{{ enlace.url }}"{{ activo(enlace.url) ? ' class="activo"' }}>{{ enlace.texto }}</a>{% endfor %}
                </header>

                TWIG,
            'parciales/pie.twig' => "<footer>Escríbenos</footer>\n",
            'contenido/index.md' => "---\ntitulo: Inicio\nplantilla: portada\n---\nBienvenida. [archivo fichero=\"doc.pdf\" texto=\"Folleto\"]\n",
            'contenido/acerca.md' => "---\ntitulo: Acerca\n---\nSomos nosotros.\n\n[imagen fichero=\"falta.jpg\" alt=\"Falta\"]\n\n[aviso]\nImportante.\n[/aviso]\n",
            'contenido/contacto.twig' => "---\ntitulo: Contacto\njs: [contacto.js]\n---\n<p>{{ pagina.titulo }}</p>\n{% include 'parciales/pie.twig' %}\n",
            'contenido/avisos/cierre.md' => "---\nurl: false\netiquetas: [avisos]\n---\nCerrado en **agosto**.\n",
            'contenido/blog/_datos.yml' => "etiquetas: [blog]\n",
            'contenido/blog/uno.md' => "---\ntitulo: Uno\nfecha: 2024-01-01\n---\nPrimer artículo.\n\n![Una foto](/foto.jpg)\n",
            'contenido/blog/dos.md' => "---\ntitulo: Dos\nfecha: 2024-02-01\ncss: [dos.css]\n---\nSegundo artículo con [imagen fichero=\"foto.jpg\" alt=\"Foto\"].\n",
            'contenido/blog/futuro.md' => "---\ntitulo: Futuro\nfecha: 2024-03-01\npublicar: 2030-01-01\n---\nAún no.\n",
            'contenido/blog/borrador.md' => "---\ntitulo: Borrador\nborrador: sí\n---\nSin terminar.\n",
            'publico/base.css' => "body{margin:0}\n",
            'publico/dos.css' => ".dos{color:red}\n",
            'publico/contacto.js' => "console.log(1);\n",
            'publico/logo.svg' => "<?xml version=\"1.0\"?>\n<svg><circle r=\"1\"/></svg>\n",
            'publico/foto.jpg' => 'JPEG',
            'publico/doc.pdf' => str_repeat('PDF', 500),
        ];

        foreach ($this->ficheros as $ruta => $contenido) {
            $this->crearFichero($ruta, $contenido);
        }
    }

    /**
     * Cambios con los que se hace la secuencia al azar. Algunos no cambian
     * nada cuando no pueden aplicarse, y está bien: también hay que probar
     * construcciones sin cambios.
     *
     * @return array<string, \Closure(): void>
     */
    private function cambiosAlAzar(\Random\Randomizer $aleatorio): array
    {
        $palabra = fn () => $aleatorio->getBytesFromString('abcdefghijklmnopqrstuvwxyz', 5);
        $paginas = fn () => array_values(array_filter(
            array_keys($this->ficheros),
            fn (string $ruta) => str_starts_with($ruta, 'contenido/') && preg_match('/\.(md|twig)$/', $ruta) === 1,
        ));
        $una = function (array $lista) use ($aleatorio): string {
            return $lista[$aleatorio->getInt(0, count($lista) - 1)];
        };
        $nuevas = 0;

        return [
            'cuerpo de una página' => function () use ($paginas, $una, $palabra): void {
                $ruta = $una($paginas());
                $this->poner($ruta, $this->ficheros[$ruta] . $palabra() . "\n");
            },
            'título de una página' => function () use ($paginas, $una, $palabra): void {
                $ruta = $una($paginas());
                $this->poner($ruta, (string) preg_replace('/^---\n/', "---\ntitulo: " . $palabra() . "\n", preg_replace('/^titulo: .*\n/m', '', $this->ficheros[$ruta], 1) ?? ''));
            },
            'borrador sí o no' => function () use ($paginas, $una): void {
                $ruta = $una($paginas());
                $texto = $this->ficheros[$ruta];
                $this->poner($ruta, str_contains($texto, "borrador: sí\n")
                    ? str_replace("borrador: sí\n", '', $texto)
                    : (string) preg_replace('/^---\n/', "---\nborrador: sí\n", $texto));
            },
            'fragmento en los avisos' => function () use ($paginas, $una): void {
                $ruta = $una($paginas());
                $texto = $this->ficheros[$ruta];
                $this->poner($ruta, str_contains($texto, "etiquetas: [avisos]\n")
                    ? str_replace("etiquetas: [avisos]\n", '', $texto)
                    : (string) preg_replace('/^---\n/', "---\netiquetas: [avisos]\n", $texto));
            },
            'página nueva' => function () use (&$nuevas, $palabra): void {
                $nuevas++;
                $this->poner("contenido/blog/nueva-{$nuevas}.md", "---\ntitulo: Nueva {$nuevas}\nfecha: 2024-01-" . sprintf('%02d', $nuevas % 28 + 1) . "\n---\n" . $palabra() . "\n");
            },
            'página borrada' => function () use ($paginas, $una): void {
                $ruta = $una($paginas());

                if ($ruta !== 'contenido/index.md') {
                    $this->quitar($ruta);
                }
            },
            'CSS del mismo tamaño' => fn () => $this->poner('publico/dos.css', ".dos{color:{$una(['red', 'tan', 'sky'])}}\n"),
            'SVG' => fn () => $this->poner('publico/logo.svg', '<svg><circle r="' . $aleatorio->getInt(1, 9) . "\"/></svg>\n"),
            'documento que va y viene' => function () use ($aleatorio): void {
                isset($this->ficheros['publico/doc.pdf'])
                    ? $this->quitar('publico/doc.pdf')
                    : $this->poner('publico/doc.pdf', str_repeat('PDF', $aleatorio->getInt(1, 2000)));
            },
            'foto con otras medidas' => fn () => $this->poner('publico/foto.jpg', Png::de($aleatorio->getInt(1, 9), $aleatorio->getInt(1, 9))),
            'foto que va y viene' => function (): void {
                isset($this->ficheros['publico/falta.jpg']) ? $this->quitar('publico/falta.jpg') : $this->poner('publico/falta.jpg', 'JPEG');
            },
            'parcial' => fn () => $this->poner('parciales/pie.twig', '<footer>' . $palabra() . "</footer>\n"),
            'plantilla' => fn () => $this->poner('plantillas/portada.twig', $this->ficheros['plantillas/portada.twig'] . '{# ' . $palabra() . " #}\n"),
            'atajo que va y viene' => function () use ($palabra): void {
                isset($this->ficheros['parciales/atajos/aviso.twig'])
                    ? $this->quitar('parciales/atajos/aviso.twig')
                    : $this->poner('parciales/atajos/aviso.twig', '<aside title="' . $palabra() . '">{{ contenido }}</aside>');
            },
            'error en la cabecera que va y viene' => function (): void {
                $texto = $this->ficheros['parciales/cabecera.twig'];
                $this->poner('parciales/cabecera.twig', str_contains($texto, '{% if %}')
                    ? str_replace('{% if %}', '', $texto)
                    : str_replace('</header>', '{% if %}</header>', $texto));
            },
            'datos' => fn () => $this->poner('datos/menu.yml', "- texto: {$palabra()}\n  url: /\n"),
            'nada' => fn () => null,
        ];
    }

    /**
     * Un sitio en castellano y euskera en el que las páginas dependen de sus
     * traducciones (los `hreflang` llevan su título), de los datos de su
     * idioma, de la cascada de su idioma y de los campos comunes de una
     * página. Salen siete páginas.
     */
    private function crearSitioEnDosIdiomas(): void
    {
        $this->ficheros = [
            'sitio.yml' => "nombre: Prueba\nurl: https://ejemplo.com\nidiomas:\n  - codigo: es\n    nombre: Castellano\n  - codigo: eu\n    nombre: Euskara\nfeed:\n  coleccion: blog\n",
            'datos/menu.yml' => "- texto: Inicio\n  url: /\n",
            'datos/menu.eu.yml' => "- texto: Hasiera\n  url: /eu/\n",
            'plantillas/base.twig' => <<<'TWIG'
                <!doctype html>
                <html lang="{{ pagina.idioma }}">
                {% for codigo, traduccion in pagina.traducciones %}<link rel="alternate" hreflang="{{ codigo }}" href="{{ traduccion.url }}" title="{{ traduccion.titulo }}">{% endfor %}
                {% for enlace in datos.menu %}<a href="{{ enlace.url }}"{{ activo(enlace.url) ? ' class="activo"' }}>{{ enlace.texto }}</a>{% endfor %}
                <main>{% block principal %}{{ pagina.seccion }} {{ pagina.telefono }} {{ pagina.contenido }}{% endblock %}</main>

                TWIG,
            'plantillas/pagina.twig' => "{% extends 'plantillas/base.twig' %}\n",
            'plantillas/portada.twig' => <<<'TWIG'
                {% extends 'plantillas/base.twig' %}
                {% block principal %}<ul>{% for articulo in coleccion('blog') %}<li><a href="{{ articulo.url }}">{{ articulo.titulo }}</a> {{ articulo.fecha|fecha }}</li>{% endfor %}</ul>{% endblock %}

                TWIG,
            'contenido/index.md' => "---\ntitulo: Inicio\nplantilla: portada\n---\n",
            'contenido/index.eu.md' => "---\ntitulo: Hasiera\nplantilla: portada\n---\n",
            'contenido/blog/_datos.yml' => "etiquetas: [blog]\nseccion: Blog\n",
            'contenido/blog/_datos.eu.yml' => "seccion: Bloga\n",
            'contenido/blog/uno.md' => "---\ntitulo: Uno\nfecha: 2024-01-01\n---\nPrimer artículo.\n",
            'contenido/blog/uno.eu.md' => "---\ntitulo: Bat\nfecha: 2024-01-01\n---\nLehen artikulua.\n",
            'contenido/blog/dos.md' => "---\ntitulo: Dos\nfecha: 2024-02-01\n---\nSegundo artículo.\n",
            'contenido/contacto.yml' => "telefono: '944000000'\n",
            'contenido/contacto.md' => "---\ntitulo: Contacto\n---\nEscríbenos.\n",
            'contenido/contacto.eu.md' => "---\ntitulo: Kontaktua\nurl: /kontaktua/\n---\nIdatzi.\n",
        ];

        foreach ($this->ficheros as $ruta => $contenido) {
            $this->crearFichero($ruta, $contenido);
        }
    }

    /**
     * @return array<string, \Closure(): void>
     */
    private function cambiosAlAzarEnDosIdiomas(\Random\Randomizer $aleatorio): array
    {
        $palabra = fn () => $aleatorio->getBytesFromString('abcdefghijklmnopqrstuvwxyz', 5);
        $una = fn (array $lista): string => $lista[$aleatorio->getInt(0, count($lista) - 1)];
        $enCastellano = ['contenido/blog/uno.md', 'contenido/blog/dos.md', 'contenido/contacto.md', 'contenido/blog/tres.md'];
        $paginas = fn () => array_values(array_filter(
            array_keys($this->ficheros),
            fn (string $ruta) => str_starts_with($ruta, 'contenido/') && preg_match('/\.(md|twig)$/', $ruta) === 1,
        ));
        $alternar = function (string $ruta, string $contenido): void {
            isset($this->ficheros[$ruta]) ? $this->quitar($ruta) : $this->poner($ruta, $contenido);
        };

        return [
            'traducción que va y viene' => function () use ($una, $enCastellano, $palabra, $alternar): void {
                $ruta = str_replace('.md', '.eu.md', $una($enCastellano));
                $alternar($ruta, "---\ntitulo: " . $palabra() . "\nfecha: 2024-03-01\n---\n" . $palabra() . "\n");
            },
            'página en castellano que va y viene' => fn () => $alternar('contenido/blog/tres.md', "---\ntitulo: Tres\nfecha: 2024-03-01\n---\nTercero.\n"),
            'título de una página' => function () use ($paginas, $una, $palabra): void {
                $ruta = $una($paginas());
                $this->poner($ruta, (string) preg_replace('/^titulo: .*$/m', 'titulo: ' . $palabra(), $this->ficheros[$ruta], 1));
            },
            'cuerpo de una página' => function () use ($paginas, $una, $palabra): void {
                $ruta = $una($paginas());
                $this->poner($ruta, $this->ficheros[$ruta] . $palabra() . "\n");
            },
            'URL de una página' => function () use ($paginas, $una, $palabra): void {
                $ruta = $una($paginas());
                $texto = $this->ficheros[$ruta];
                $this->poner($ruta, str_contains($texto, "\nurl: ")
                    ? (string) preg_replace('/^url: .*\n/m', '', $texto)
                    : (string) preg_replace('/^---\n/', "---\nurl: /" . $palabra() . "/\n", $texto));
            },
            'borrador sí o no' => function () use ($paginas, $una): void {
                $ruta = $una($paginas());
                $texto = $this->ficheros[$ruta];
                $this->poner($ruta, str_contains($texto, "borrador: sí\n")
                    ? str_replace("borrador: sí\n", '', $texto)
                    : (string) preg_replace('/^---\n/', "---\nborrador: sí\n", $texto));
            },
            'datos del idioma' => fn () => $this->poner('datos/menu.eu.yml', "- texto: {$palabra()}\n  url: /eu/\n"),
            'datos del idioma que van y vienen' => fn () => $alternar('datos/menu.eu.yml', "- texto: Hasiera\n  url: /eu/\n"),
            'cascada del idioma' => fn () => $alternar('contenido/blog/_datos.eu.yml', "seccion: {$palabra()}\n"),
            'campos comunes' => fn () => $alternar('contenido/contacto.yml', "telefono: '{$aleatorio->getInt(900000000, 999999999)}'\n"),
            'plantilla' => fn () => $this->poner('plantillas/portada.twig', $this->ficheros['plantillas/portada.twig'] . '{# ' . $palabra() . " #}\n"),
            'nada' => fn () => null,
        ];
    }

    private function cambiar(string $ruta, string $antes, string $despues): void
    {
        self::assertStringContainsString($antes, $this->ficheros[$ruta], "No se puede cambiar {$ruta}");
        $this->poner($ruta, str_replace($antes, $despues, $this->ficheros[$ruta]));
    }

    private function poner(string $ruta, string $contenido): void
    {
        $this->ficheros[$ruta] = $contenido;
        $this->crearFichero($ruta, $contenido);
    }

    private function quitar(string $ruta): void
    {
        unset($this->ficheros[$ruta]);
        unlink($this->carpetaTemporal() . "/{$ruta}");
    }
}
