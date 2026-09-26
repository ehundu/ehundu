<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Plantillas;

use Ehundu\Avisos;
use Ehundu\Colecciones;
use Ehundu\Lector;
use Ehundu\Plantillas\Maquetador;
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

        self::assertSame(['Hay css declarado, pero ninguna plantilla escribe {{ css() }}; no se incrusta'], $this->avisos());
    }

    private function maquetar(string $ruta): string
    {
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $lectura = (new Lector())->leer($proyecto);
        $maquetador = new Maquetador($proyecto, $lectura, new Colecciones($lectura->paginas, new \DateTimeImmutable()), $this->avisos);

        foreach ($lectura->paginas as $pagina) {
            if ($pagina->ruta === $ruta) {
                return $maquetador->maquetar($pagina);
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
