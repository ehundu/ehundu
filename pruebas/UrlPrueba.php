<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Avisos;
use Ehundu\ErrorDeProyecto;
use Ehundu\Pagina;
use Ehundu\Url;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UrlPrueba extends TestCase
{
    private Avisos $avisos;

    protected function setUp(): void
    {
        $this->avisos = new Avisos();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rutas(): iterable
    {
        yield 'página' => ['blog/uno.md', '/blog/uno/'];
        yield 'plantilla' => ['contacto.twig', '/contacto/'];
        yield 'index de la raíz' => ['index.md', '/'];
        yield 'index de una carpeta' => ['blog/index.md', '/blog/'];
        yield 'guion bajo' => ['sobre_nosotros/equipo.md', '/sobre_nosotros/equipo/'];
    }

    #[Test]
    #[DataProvider('rutas')]
    public function sinUrlSaleDeLaRuta(string $ruta, string $esperada): void
    {
        self::assertSame($esperada, $this->resolver([], $ruta));
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function avisaSiLaRutaDaUnaMalaUrl(): void
    {
        self::assertSame('/Quiénes somos/', $this->resolver([], 'Quiénes somos.md'));
        self::assertSame([
            'contenido/a.md: La URL que sale de la ruta tiene espacios, mayúsculas u otros caracteres especiales: '
            . '/Quiénes somos/. Conviene renombrar el fichero o darle un «url»',
        ], $this->avisos());
    }

    #[Test]
    public function unaUrlExplicitaSustituyeALaDeLaRuta(): void
    {
        self::assertSame('/contacto/', $this->resolver(['url' => '/contacto/']));
        self::assertSame('/404.html', $this->resolver(['url' => '/404.html']));
        self::assertSame('/robots.txt', $this->resolver(['url' => '/robots.txt']));
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function unaUrlFalseEsUnFragmento(): void
    {
        self::assertFalse($this->resolver(['url' => false]));
    }

    #[Test]
    public function unaUrlEnBlancoDejaLaDeLaRuta(): void
    {
        self::assertSame('/blog/uno/', $this->resolver(['url' => '']));
        self::assertSame('/blog/uno/', $this->resolver(['url' => null]));
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function completaUnaUrlQueNoEmpiezaPorBarra(): void
    {
        self::assertSame('/contacto/', $this->resolver(['url' => 'contacto/']));
        self::assertSame(['contenido/a.md: «url» tiene que empezar por /; se toma como /contacto/'], $this->avisos());
    }

    #[Test]
    public function tomaComoCarpetaUnaUrlSinBarraNiExtension(): void
    {
        self::assertSame('/contacto/', $this->resolver(['url' => '/contacto']));
        self::assertSame(
            ['contenido/a.md: «url» no acaba en / ni en un fichero con extensión; se toma como /contacto/'],
            $this->avisos(),
        );
    }

    #[Test]
    public function unaUrlNoPuedeSalirDeLaCarpetaDeSalida(): void
    {
        self::assertSame('/blog/uno/', $this->resolver(['url' => '/../../fuera/']));
        self::assertSame('/blog/uno/', $this->resolver(['url' => '/a:b/']));

        self::assertSame([
            'contenido/a.md: La URL /../../fuera/ no vale como ruta de fichero; se usa la que sale de la ruta',
            'contenido/a.md: La URL /a:b/ no vale como ruta de fichero; se usa la que sale de la ruta',
        ], $this->avisos());
    }

    #[Test]
    public function aplicaUnPatronConSlug(): void
    {
        $campos = ['titulo' => 'Diez novelas para el otoño', 'url' => '/blog/{{ titulo|slug }}/'];

        self::assertSame('/blog/diez-novelas-para-el-otono/', $this->resolver($campos));
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function elPatronAceptaLosAliasIngleses(): void
    {
        $campos = ['titulo' => 'Diez novelas', 'url' => '/{{ title | slugify }}/'];

        self::assertSame('/diez-novelas/', $this->resolver($campos));
    }

    #[Test]
    public function elPatronPuedePonerUnCampoSinFiltro(): void
    {
        $campos = ['seccion' => 'libros', 'orden' => 3, 'url' => '/{{ seccion }}/{{orden}}/'];

        self::assertSame('/libros/3/', $this->resolver($campos));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function patronesQueNoValen(): iterable
    {
        yield 'campo que falta' => [
            ['url' => '/blog/{{ titulo|slug }}/'],
            '«url» usa «titulo», que la página no tiene o no es un texto',
        ];
        yield 'campo que no es texto' => [
            ['etiquetas' => ['a'], 'url' => '/{{ etiquetas }}/'],
            '«url» usa «etiquetas», que la página no tiene o no es un texto',
        ];
        yield 'filtro desconocido' => [
            ['titulo' => 'Hola', 'url' => '/{{ titulo|upper }}/'],
            '«url» no conoce el filtro «upper»; solo existe «slug»',
        ];
        yield 'expresión' => [
            ['titulo' => 'Hola', 'url' => '/{{ titulo ~ "x" }}/'],
            '«url» solo admite {{ campo }} y {{ campo|slug }}, no {{ titulo ~ "x" }}',
        ];
        yield 'etiqueta de Twig' => [
            ['url' => '/{% if a %}a{% endif %}/'],
            '«url» no admite etiquetas {% %}',
        ];
        yield 'slug vacío' => [
            ['titulo' => '¿?', 'url' => '/{{ titulo|slug }}/'],
            '«url» pasa «titulo» por slug y sale vacío',
        ];
        yield 'llaves sin cerrar' => [
            ['titulo' => 'Hola', 'url' => '/{{ titulo/'],
            '«url» tiene unas llaves sin cerrar',
        ];
    }

    /**
     * @param array<string, mixed> $campos
     */
    #[Test]
    #[DataProvider('patronesQueNoValen')]
    public function unPatronQueNoValeAvisaYDejaLaUrlDeLaRuta(array $campos, string $problema): void
    {
        self::assertSame('/blog/uno/', $this->resolver($campos));
        self::assertSame(["contenido/a.md: {$problema}; se usa la URL que sale de la ruta"], $this->avisos());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function ficheros(): iterable
    {
        yield 'raíz' => ['/', 'index.html'];
        yield 'carpeta' => ['/blog/', 'blog/index.html'];
        yield 'subcarpeta' => ['/blog/uno/', 'blog/uno/index.html'];
        yield 'fichero' => ['/404.html', '404.html'];
        yield 'fichero en carpeta' => ['/a/mapa.xml', 'a/mapa.xml'];
    }

    #[Test]
    #[DataProvider('ficheros')]
    public function cadaUrlVaAUnFicheroDeSalida(string $url, string $fichero): void
    {
        self::assertSame($fichero, Url::fichero($url));
    }

    #[Test]
    public function dosPaginasConLaMismaUrlDetienenElBuild(): void
    {
        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage(
            'contenido/b.md: Dos páginas van al mismo fichero de salida, contacto/index.html: '
            . 'contenido/a.md (/contacto/) y contenido/b.md (/contacto/)',
        );

        Url::comprobarColisiones([$this->pagina('a.md', '/contacto/'), $this->pagina('b.md', '/contacto/')], $this->ahora());
    }

    #[Test]
    public function lasMayusculasNoEvitanUnaColision(): void
    {
        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('Dos páginas van al mismo fichero de salida, blog/index.html');

        Url::comprobarColisiones([$this->pagina('a.md', '/Blog/'), $this->pagina('b.md', '/blog/')], $this->ahora());
    }

    #[Test]
    public function losFragmentosBorradoresYPaginasFuturasNoChocan(): void
    {
        Url::comprobarColisiones([
            $this->pagina('a.md', '/contacto/'),
            $this->pagina('b.md', '/contacto/', ['borrador' => true]),
            $this->pagina('c.md', '/contacto/', ['publicar' => new \DateTimeImmutable('2030-01-01', new \DateTimeZone('UTC'))]),
            $this->pagina('d.md', false),
            $this->pagina('e.md', false),
        ], $this->ahora());

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function direccionesDePdf(): iterable
    {
        yield 'una carpeta' => ['/menu-del-dia/', 'menu-del-dia.pdf'];
        yield 'una carpeta anidada' => ['/blog/uno/', 'blog/uno.pdf'];
        yield 'la portada' => ['/', 'index.pdf'];
        yield 'la raíz de un idioma' => ['/eu/', 'eu.pdf'];
        yield 'un html' => ['/informes/anual.html', 'informes/anual.pdf'];
        yield 'un html en mayúsculas' => ['/informes/ANUAL.HTML', 'informes/ANUAL.pdf'];
        yield 'el 404' => ['/404.html', '404.pdf'];
        yield 'un fichero de texto' => ['/robots.txt', null];
        yield 'un xml' => ['/feed.xml', null];
    }

    #[Test]
    #[DataProvider('direccionesDePdf')]
    public function elPdfSaleEnLaDireccionDeLaPaginaConPdfEnLugarDeLaBarraFinal(string $url, ?string $esperado): void
    {
        self::assertSame($esperado, Url::ficheroPdf($url));
    }

    /**
     * @param array<array-key, mixed> $campos
     */
    private function resolver(array $campos, string $ruta = 'blog/uno.md'): string|false
    {
        return Url::resolver($ruta, $campos, 'contenido/a.md', $this->avisos);
    }

    /**
     * @param array<array-key, mixed> $campos
     */
    private function pagina(string $ruta, string|false $url, array $campos = []): Pagina
    {
        return new Pagina($ruta, 'md', $campos, $url, '', 1);
    }

    private function ahora(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-25 12:00:00', new \DateTimeZone('UTC'));
    }

    /**
     * @return list<string>
     */
    private function avisos(): array
    {
        return array_map(strval(...), $this->avisos->todos());
    }
}
