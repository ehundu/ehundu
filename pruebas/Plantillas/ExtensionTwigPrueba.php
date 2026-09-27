<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Plantillas;

use Ehundu\Colecciones;
use Ehundu\ErrorDeProyecto;
use Ehundu\Pagina;
use Ehundu\Plantillas\ErroresDeTwig;
use Ehundu\Plantillas\ExtensionTwig;
use Ehundu\Plantillas\VistaDePagina;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Loader\ArrayLoader;

final class ExtensionTwigPrueba extends TestCase
{
    private ExtensionTwig $extension;

    /** @var array<string, Pagina> */
    private array $paginas = [];

    protected function setUp(): void
    {
        $fecha = fn (string $dia) => new \DateTimeImmutable($dia, new \DateTimeZone('UTC'));

        $paginas = [
            new Pagina('blog/a.md', 'md', ['titulo' => 'Uno', 'etiquetas' => ['blog'], 'fecha' => $fecha('2025-01-01')], '/blog/uno/', 'Cuerpo de uno', 5),
            new Pagina('blog/b.md', 'md', ['titulo' => 'Dos', 'etiquetas' => ['blog', 'novela'], 'fecha' => $fecha('2025-02-01')], '/blog/dos/', 'Cuerpo de dos', 5),
            new Pagina('blog/c.md', 'md', ['titulo' => 'Tres', 'etiquetas' => ['blog'], 'fecha' => $fecha('2025-03-01')], '/blog/tres/', 'Cuerpo de tres', 5),
        ];

        foreach ($paginas as $pagina) {
            $this->paginas[$pagina->ruta] = $pagina;
        }

        $this->extension = new ExtensionTwig(
            new Colecciones($paginas, $fecha('2026-01-01')),
            fn (Pagina $pagina) => '<p>' . htmlspecialchars($pagina->cuerpo) . '</p>',
        );
    }

    #[Test]
    public function recorreUnaColeccionConSusCampos(): void
    {
        self::assertSame(
            'Uno /blog/uno/ blog/a.md|Dos /blog/dos/ blog/b.md|Tres /blog/tres/ blog/c.md|',
            $this->render("{% for a in coleccion('blog') %}{{ a.titulo }} {{ a.url }} {{ a.ruta }}|{% endfor %}"),
        );
    }

    #[Test]
    public function encadenaLosFiltrosDeColeccion(): void
    {
        self::assertSame('Tres Dos ', $this->render("{% for a in coleccion('blog')|orden('fecha desc')|limite(2) %}{{ a.titulo }} {% endfor %}"));
        self::assertSame('Tres Dos Uno ', $this->render("{% for a in coleccion('blog')|invertir %}{{ a.titulo }} {% endfor %}"));
        self::assertSame('Dos ', $this->render("{% for a in coleccion('blog')|donde('etiquetas', 'novela') %}{{ a.titulo }} {% endfor %}"));
        self::assertSame('Dos ', $this->render("{% for a in coleccion('blog')|donde('url', '/blog/dos/') %}{{ a.titulo }} {% endfor %}"));
    }

    #[Test]
    public function sinAnteriorYSiguienteRecibenLaPaginaActual(): void
    {
        $plantilla = "{% set todas = coleccion('blog') %}"
            . "{% for a in todas|sin(pagina) %}{{ a.titulo }} {% endfor %}"
            . "| {{ (todas|anterior(pagina)).titulo }} | {{ (todas|siguiente(pagina)).titulo }}";

        self::assertSame('Uno Tres | Uno | Tres', $this->render($plantilla, ['pagina' => $this->vista('blog/b.md')]));
    }

    #[Test]
    public function elFiltroSlug(): void
    {
        self::assertSame('diez-novelas-para-el-otono', $this->render("{{ 'Diez novelas para el otoño'|slug }}"));
    }

    #[Test]
    public function elContenidoSeConvierteSoloCuandoSePideYNoSeEscapa(): void
    {
        self::assertSame('<p>Cuerpo de dos</p>', $this->render('{{ pagina.contenido }}', ['pagina' => $this->vista('blog/b.md')]));
        self::assertSame('Dos', $this->render('{{ pagina.titulo }}', ['pagina' => $this->vista('blog/b.md')]));
    }

    #[Test]
    public function losCamposQueFaltanSonNulosYSeEscapanLosDemas(): void
    {
        self::assertSame('sin imagen', $this->render("{{ pagina.imagen ?? 'sin imagen' }}", ['pagina' => $this->vista('blog/a.md')]));
        self::assertSame('no', $this->render("{{ pagina.imagen is defined ? 'sí' : 'no' }}", ['pagina' => $this->vista('blog/a.md')]));
        self::assertSame('&lt;b&gt;', $this->render('{{ texto }}', ['texto' => '<b>']));
    }

    #[Test]
    public function unaPaginaNoSePuedeModificarDesdeUnaPlantilla(): void
    {
        $this->expectException(\LogicException::class);

        $this->vista('blog/a.md')['titulo'] = 'Otro';
    }

    #[Test]
    public function traduceLosErroresDeTwig(): void
    {
        $error = $this->errorAlRenderizar("Hola\n{{ coleccion('blog')|ordena('fecha') }}");

        self::assertSame(
            'plantillas/prueba.twig:2: No existe el filtro «ordena». ¿Querías decir «orden»?',
            $error->getMessage(),
        );
        self::assertSame('plantillas/prueba.twig', $error->fichero);
        self::assertSame(2, $error->linea);
    }

    #[Test]
    public function traducePlantillasQueNoExisten(): void
    {
        self::assertSame(
            'plantillas/prueba.twig:1: No se encuentra la plantilla «cabecera.twig»',
            $this->errorAlRenderizar("{% include 'cabecera.twig' %}")->getMessage(),
        );
    }

    #[Test]
    public function losDemasErroresConservanElTextoDeTwig(): void
    {
        self::assertStringStartsWith(
            'plantillas/prueba.twig:1: Error en la plantilla (Twig dice: ',
            $this->errorAlRenderizar('{% if %}')->getMessage(),
        );
    }

    private function vista(string $ruta): VistaDePagina
    {
        return $this->extension->vistaDe($this->paginas[$ruta]);
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function render(string $plantilla, array $variables = []): string
    {
        return $this->twig($plantilla)->render('prueba.twig', $variables);
    }

    private function errorAlRenderizar(string $plantilla): ErrorDeProyecto
    {
        try {
            $this->render($plantilla);
        } catch (Error $error) {
            return ErroresDeTwig::traducir($error, fn (string $nombre) => "plantillas/{$nombre}");
        }

        self::fail('Se esperaba un error de Twig');
    }

    private function twig(string $plantilla): Environment
    {
        $twig = new Environment(new ArrayLoader(['prueba.twig' => $plantilla]), ['autoescape' => 'html']);
        $twig->addExtension($this->extension);

        return $twig;
    }
}
