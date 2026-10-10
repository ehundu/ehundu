<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Plantillas;

use Ehundu\Avisos;
use Ehundu\Colecciones;
use Ehundu\Lector;
use Ehundu\Plantillas\Maquetador;
use Ehundu\Plantillas\Recursos;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RecursosPrueba extends TestCase
{
    use CarpetaTemporal;

    private Avisos $avisos;

    protected function setUp(): void
    {
        $this->avisos = new Avisos();
        $this->crearFichero('sitio.yml', "nombre: Librería La Esquina\nurl: https://www.ejemplo.com\n");

        foreach (['reset', 'estilos', 'cabecera', 'pie', 'pagina', 'blog', 'portada'] as $nombre) {
            $this->crearFichero("publico/css/{$nombre}.css", ".{$nombre} {}");
        }
        $this->crearFichero('publico/js/menu.js', 'menu();');
        $this->crearFichero('publico/js/galeria.js', 'galeria();');

        $this->crearFichero('plantillas/base.twig', <<<'TWIG'
            <head>{{ css('css/reset.css', 'css/estilos.css') }}<style>{{ css() }}</style></head>
            <body>{% include 'parciales/cabecera.twig' %}{% block cuerpo %}{% endblock %}{% include 'parciales/pie.twig' %}<script>{{ js() }}</script></body>
            TWIG);
        $this->crearFichero('plantillas/pagina.twig', "{% extends 'plantillas/base.twig' %}{% block cuerpo %}{{ css('css/pagina.css') }}{{ pagina.contenido }}{% endblock %}");
        $this->crearFichero('parciales/cabecera.twig', "{{ css('css/cabecera.css') }}{{ js('js/menu.js') }}<header></header>");
        $this->crearFichero('parciales/pie.twig', "{{ css('css/pie.css', 'css/estilos.css') }}<footer></footer>");
    }

    #[Test]
    public function juntaElCssDeclaradoEnElOrdenDeLaPaginaYSinRepetir(): void
    {
        $this->crearFichero('contenido/contacto.md', "---\ntitulo: Contacto\n---\nHola");

        $html = $this->maquetar('contacto.md');

        self::assertStringContainsString(
            "<style>.reset {}\n.estilos {}\n.cabecera {}\n.pagina {}\n.pie {}</style>",
            $html,
        );
        self::assertStringContainsString('<script>menu();</script>', $html);
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function losFicherosDelFrontMatterVanAlFinalYSeSumanEnLaCascada(): void
    {
        $this->crearFichero('contenido/blog/_datos.yml', "css: [css/blog.css]\njs: js/galeria.js");
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\ncss: [css/portada.css, css/reset.css]\n---\nHola");

        $html = $this->maquetar('blog/uno.md');

        self::assertStringContainsString(
            "<style>.reset {}\n.estilos {}\n.cabecera {}\n.pagina {}\n.pie {}\n.blog {}\n.portada {}</style>",
            $html,
        );
        self::assertStringContainsString("<script>menu();\ngaleria();</script>", $html);
    }

    #[Test]
    public function elCssQueDeclaraElCuerpoDeOtraPaginaTambienCuenta(): void
    {
        $this->crearFichero('parciales/atajos/galeria.twig', "{{ css('css/blog.css') }}{{ js('js/galeria.js') }}<div class=\"galeria\"></div>");
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\netiquetas: [blog]\n---\n[galeria carpeta=\"x\"]");
        $this->crearFichero('contenido/index.twig', "---\ntitulo: Inicio\n---\n{% for a in coleccion('blog') %}{{ a.contenido }}{% endfor %}");

        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $lectura = (new Lector())->leer($proyecto);
        $maquetador = new Maquetador($proyecto, $lectura, new Colecciones($lectura->paginas, new \DateTimeImmutable()), $this->avisos);
        [$articulo, $inicio] = $lectura->paginas;

        // El artículo se convierte primero dentro de la portada, y luego se reutiliza.
        self::assertStringContainsString('.blog {}', $maquetador->maquetar($inicio));
        self::assertStringContainsString('.blog {}', $maquetador->maquetar($articulo));
        self::assertStringContainsString('galeria();', $maquetador->maquetar($articulo));
    }

    #[Test]
    public function avisaDeLoQueNoSePuedeIncrustar(): void
    {
        $this->crearFichero('plantillas/pagina.twig', "{{ css('css/falta.css', '../sitio.yml', 'js/menu.js') }}<style>{{ css() }}</style>{{ pagina.contenido }}");
        $this->crearFichero('contenido/index.md', 'Hola');

        self::assertStringContainsString('<style></style>', $this->maquetar('index.md'));
        self::assertSame([
            "css('css/falta.css'): no existe publico/css/falta.css",
            "css('../sitio.yml'): solo se incrustan ficheros .css de dentro de publico/",
            "css('js/menu.js'): solo se incrustan ficheros .css de dentro de publico/",
        ], $this->avisos());
    }

    #[Test]
    public function avisaSiSeDeclaraCssYNingunaPlantillaLoEscribe(): void
    {
        $this->crearFichero('plantillas/pagina.twig', "{{ css('css/reset.css') }}{{ pagina.contenido }}");
        $this->crearFichero('contenido/index.md', 'Hola');

        $this->maquetar('index.md');

        self::assertSame(['Hay css declarado, pero ninguna plantilla escribe {{ css() }} ni {{ css_fichero() }}; no se incluye'], $this->avisos());
    }

    #[Test]
    public function cssFicheroEnlazaUnPaqueteConLoMismoQueHabriaIncrustado(): void
    {
        $this->plantillaConFicheros();
        $this->crearFichero('contenido/contacto.md', "---\ntitulo: Contacto\n---\nHola");

        [$html, $paquetes] = $this->maquetarConPaquetes('contacto.md');

        $contenido = ".reset {}\n.estilos {}\n.cabecera {}\n.pagina {}\n.pie {}";
        $fichero = 'css/' . substr(hash('sha256', $contenido), 0, 10) . '.css';

        // El paquete de JS, con menu.js de la cabecera, es otro: aquí solo importa el de CSS.
        self::assertSame([$fichero => $contenido], array_filter($paquetes, fn (string $ruta) => str_starts_with($ruta, 'css/'), ARRAY_FILTER_USE_KEY));
        self::assertStringContainsString("<head><link rel=\"stylesheet\" href=\"/{$fichero}\"></head>", $html);
        self::assertStringNotContainsString('.reset {}', $html);
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function jsFicheroEnlazaUnPaqueteConDefer(): void
    {
        $this->plantillaConFicheros();
        $this->crearFichero('contenido/contacto.md', "---\ntitulo: Contacto\njs: js/galeria.js\n---\nHola");

        [$html, $paquetes] = $this->maquetarConPaquetes('contacto.md');

        $contenido = "menu();\ngaleria();";
        $fichero = 'js/' . substr(hash('sha256', $contenido), 0, 10) . '.js';

        self::assertArrayHasKey($fichero, $paquetes);
        self::assertSame($contenido, $paquetes[$fichero]);
        self::assertStringContainsString("<script src=\"/{$fichero}\" defer></script></body>", $html);
    }

    #[Test]
    public function elNombreDelPaqueteCambiaConSuContenidoYEsElMismoParaLasPaginasQueDeclaranLoMismo(): void
    {
        $this->plantillaConFicheros();
        $this->crearFichero('contenido/uno.md', 'Uno');
        $this->crearFichero('contenido/dos.md', 'Dos');
        $this->crearFichero('contenido/blog/tres.md', "---\ncss: css/blog.css\n---\nTres");

        $uno = array_key_first($this->maquetarConPaquetes('uno.md')[1]);
        $dos = array_key_first($this->maquetarConPaquetes('dos.md')[1]);
        $tres = array_key_first($this->maquetarConPaquetes('blog/tres.md')[1]);

        self::assertSame($uno, $dos);
        self::assertNotSame($uno, $tres);

        $this->crearFichero('publico/css/reset.css', '.reset { color: red }');

        self::assertNotSame($uno, array_key_first($this->maquetarConPaquetes('uno.md')[1]));
    }

    #[Test]
    public function sinNadaQueEnlazarNoEscribeLaEtiquetaNiElPaquete(): void
    {
        $this->crearFichero('plantillas/pagina.twig', '<head>{{ css_fichero() }}</head>{{ js_fichero() }}{{ pagina.contenido }}');
        $this->crearFichero('contenido/index.md', 'Hola');

        [$html, $paquetes] = $this->maquetarConPaquetes('index.md');

        self::assertSame("<head></head><p>Hola</p>\n", $html);
        self::assertSame([], $paquetes);
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function cssFicheroYCssPuedenConvivirYAvisaUnaSolaVezDeLoQueFalta(): void
    {
        $this->crearFichero('plantillas/pagina.twig', "{{ css('css/reset.css', 'css/falta.css') }}<style>{{ css() }}</style>{{ css_fichero() }}");
        $this->crearFichero('contenido/index.md', 'Hola');

        [$html, $paquetes] = $this->maquetarConPaquetes('index.md');

        self::assertStringContainsString('<style>.reset {}</style><link rel="stylesheet" href="/css/', $html);
        self::assertSame(['.reset {}'], array_values($paquetes));
        self::assertSame(["css('css/falta.css'): no existe publico/css/falta.css"], $this->avisos());
    }

    #[Test]
    public function cssFicheroNoDeclaraFicherosYAvisaSiSeLosDan(): void
    {
        $this->crearFichero('plantillas/pagina.twig', "{{ css_fichero('css/reset.css') }}{{ js_fichero('js/menu.js') }}");
        $this->crearFichero('contenido/index.md', 'Hola');

        [$html, $paquetes] = $this->maquetarConPaquetes('index.md');

        self::assertSame('', $html);
        self::assertSame([], $paquetes);
        self::assertSame([
            "css_fichero() no lleva ficheros, y se ignoran; se declaran con css('…')",
            "js_fichero() no lleva ficheros, y se ignoran; se declaran con js('…')",
        ], $this->avisos());
    }

    #[Test]
    public function siTodoLoDeclaradoFallaNoHayPaquete(): void
    {
        $this->crearFichero('plantillas/pagina.twig', "{{ css('css/falta.css') }}{{ css_fichero() }}");
        $this->crearFichero('contenido/index.md', 'Hola');

        [$html, $paquetes] = $this->maquetarConPaquetes('index.md');

        self::assertSame('', $html);
        self::assertSame([], $paquetes);
        self::assertSame(["css('css/falta.css'): no existe publico/css/falta.css"], $this->avisos());
    }

    #[Test]
    public function sinFicherosLasMarcasDeFicheroSalenVaciasYNoHayPaquetes(): void
    {
        $recursos = new Recursos(Proyecto::abrir($this->carpetaTemporal()), $this->avisos);
        $html = '<style>' . Recursos::MARCA_CSS . '</style>' . Recursos::MARCA_CSS_FICHERO . Recursos::MARCA_JS_FICHERO;

        ['html' => $resultado, 'paquetes' => $paquetes] = $recursos->insertar($html, ['css' => ['css/reset.css'], 'js' => []], conFicheros: false);

        self::assertSame('<style>.reset {}</style>', $resultado);
        self::assertSame([], $paquetes);
        self::assertSame([], $this->avisos());

        // Y si no hay dónde ponerlo, el aviso solo habla de lo que vale en un PDF.
        $recursos->insertar('', ['css' => ['css/reset.css'], 'js' => []], conFicheros: false);

        self::assertSame(['Hay css declarado, pero ninguna plantilla escribe {{ css() }}; no se incrusta'], $this->avisos());
    }

    private function plantillaConFicheros(): void
    {
        $this->crearFichero('plantillas/base.twig', <<<'TWIG'
            <head>{{ css('css/reset.css', 'css/estilos.css') }}{{ css_fichero() }}</head>
            <body>{% include 'parciales/cabecera.twig' %}{% block cuerpo %}{% endblock %}{% include 'parciales/pie.twig' %}{{ js_fichero() }}</body>
            TWIG);
    }

    private function maquetar(string $ruta): string
    {
        return $this->maquetarConPaquetes($ruta)[0];
    }

    /**
     * @return array{0: string, 1: array<string, string>} la página y los paquetes que enlaza
     */
    private function maquetarConPaquetes(string $ruta): array
    {
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $lectura = (new Lector())->leer($proyecto);
        $maquetador = new Maquetador($proyecto, $lectura, new Colecciones($lectura->paginas, new \DateTimeImmutable()), $this->avisos);

        foreach ($lectura->paginas as $pagina) {
            if ($pagina->ruta === $ruta) {
                return [$maquetador->maquetar($pagina), $maquetador->paquetesDe($ruta)];
            }
        }

        self::fail("No se ha leído la página {$ruta}");
    }

    /**
     * @return list<string>
     */
    private function avisos(): array
    {
        return array_map(strval(...), $this->avisos->todos());
    }
}
