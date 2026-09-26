<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Avisos;
use Ehundu\Cascada;
use Ehundu\ErrorDeProyecto;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CascadaPrueba extends TestCase
{
    use CarpetaTemporal;

    #[Test]
    public function ganaElNivelDeEncima(): void
    {
        self::assertSame(
            ['plantilla' => 'articulo', 'icono' => 'a.svg', 'titulo' => 'Hola'],
            Cascada::combinar(['plantilla' => 'pagina', 'icono' => 'a.svg'], ['plantilla' => 'articulo', 'titulo' => 'Hola']),
        );
    }

    #[Test]
    public function lasEtiquetasSeSumanSinRepetirDeLoGeneralALoConcreto(): void
    {
        self::assertSame(
            ['etiquetas' => ['blog', 'novela', 'otono']],
            Cascada::combinar(['etiquetas' => ['blog', 'novela']], ['etiquetas' => ['otono', 'novela', 'blog']]),
        );

        self::assertSame(['etiquetas' => ['blog']], Cascada::combinar(['etiquetas' => ['blog']], ['etiquetas' => null]));
        self::assertSame(['etiquetas' => ['blog']], Cascada::combinar([], ['etiquetas' => ['blog']]));
    }

    #[Test]
    public function losCssYLosJsTambienSeSuman(): void
    {
        self::assertSame(
            ['css' => ['css/comun.css', 'css/blog.css'], 'js' => ['js/menu.js', 'js/galeria.js']],
            Cascada::combinar(
                ['css' => ['css/comun.css'], 'js' => ['js/menu.js']],
                ['css' => ['css/blog.css', 'css/comun.css'], 'js' => ['js/galeria.js']],
            ),
        );
    }

    #[Test]
    public function lasListasYLosMapasSeSustituyenEnteros(): void
    {
        self::assertSame(
            ['galeria' => ['b.jpg'], 'autor' => ['nombre' => 'Luis']],
            Cascada::combinar(
                ['galeria' => ['a.jpg'], 'autor' => ['nombre' => 'Ana', 'cargo' => 'Edición']],
                ['galeria' => ['b.jpg'], 'autor' => ['nombre' => 'Luis']],
            ),
        );
    }

    #[Test]
    public function cadaCarpetaHeredaDeSusAntecesoras(): void
    {
        $this->crearFichero('contenido/_datos.yml', "plantilla: pagina\netiquetas: [sitio]\nicono: general.svg");
        $this->crearFichero('contenido/blog/_datos.yml', "layout: articulo\ntags: [blog]");
        $cascada = $this->cascada();

        self::assertSame(['plantilla' => 'pagina', 'etiquetas' => ['sitio'], 'icono' => 'general.svg'], $cascada->campos(''));

        $esperado = ['plantilla' => 'articulo', 'etiquetas' => ['sitio', 'blog'], 'icono' => 'general.svg'];
        self::assertSame($esperado, $cascada->campos('blog'));
        self::assertSame($esperado, $cascada->campos('blog/recetas'));
    }

    #[Test]
    public function losAvisosDeUnDatosYmlSalenUnaSolaVezYConSuRuta(): void
    {
        $this->crearFichero('contenido/blog/_datos.yml', "etiquetas: [blog]\nborrador: quizá");
        $avisos = new Avisos();
        $cascada = new Cascada(Proyecto::abrir($this->carpetaTemporal()), $avisos);

        $cascada->campos('blog/uno');
        $cascada->campos('blog');

        self::assertSame(
            ['contenido/blog/_datos.yml:2: «borrador» tiene que ser sí o no'],
            array_map(strval(...), $avisos->todos()),
        );
    }

    #[Test]
    public function unDatosYmlMalEscritoDetieneLaLectura(): void
    {
        $this->crearFichero('contenido/blog/_datos.yml', "etiquetas: [blog\n");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessageMatches('#^contenido/blog/_datos\.yml:\d+: El YAML no es válido#');

        $this->cascada()->campos('blog');
    }

    private function cascada(): Cascada
    {
        return new Cascada(Proyecto::abrir($this->carpetaTemporal()), new Avisos());
    }
}
