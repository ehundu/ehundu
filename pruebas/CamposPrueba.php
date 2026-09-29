<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Avisos;
use Ehundu\Campos;
use Ehundu\Yaml;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CamposPrueba extends TestCase
{
    private Avisos $avisos;

    protected function setUp(): void
    {
        $this->avisos = new Avisos();
    }

    #[Test]
    public function pasaLosAliasInglesesASuNombreEnEspanol(): void
    {
        $campos = $this->normalizar(
            "title: Hola\nsubtitle: Qué tal\ndescription: Saludo\ndate: 2025-03-18\ntags: [blog]\nlayout: articulo\npermalink: /hola/",
        );

        self::assertSame(
            ['titulo', 'subtitulo', 'descripcion', 'fecha', 'etiquetas', 'plantilla', 'url'],
            array_keys($campos),
        );
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function siEstanLosDosNombresGanaElEspanolYAvisa(): void
    {
        $campos = $this->normalizar("title: Hello\ntitulo: Hola");

        self::assertSame(['titulo' => 'Hola'], $campos);
        self::assertSame(['contenido/a.md:2: «title» y «titulo» son el mismo campo; se usa «titulo»'], $this->avisos());
    }

    #[Test]
    public function dejaLosCamposDesconocidosComoVienen(): void
    {
        $campos = $this->normalizar("titluo: Hola\nimgmain: blog/foto.jpg\ncoleccion: servicios\nenlace: /contacto/");

        self::assertSame(
            ['titluo' => 'Hola', 'imgmain' => 'blog/foto.jpg', 'coleccion' => 'servicios', 'enlace' => '/contacto/'],
            $campos,
        );
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function losCamposEnBlancoPasanSinAviso(): void
    {
        self::assertSame(['imagen' => null, 'fecha' => null], $this->normalizar("imagen:\nfecha:"));
        self::assertSame([], $this->avisos());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function valoresDeSiNo(): iterable
    {
        yield 'sí' => ['sí', true];
        yield 'si' => ['si', true];
        yield 'Sí' => ['Sí', true];
        yield 'SÍ' => ['SÍ', true];
        yield 'no' => ['no', false];
        yield 'No' => ['No', false];
        yield 'true' => ['true', true];
        yield 'false' => ['false', false];
    }

    #[Test]
    #[DataProvider('valoresDeSiNo')]
    public function traduceLosCamposDeSiNo(string $escrito, bool $esperado): void
    {
        self::assertSame(['borrador' => $esperado], $this->normalizar("borrador: {$escrito}"));
        self::assertSame([], $this->avisos());
    }

    #[Test]
    #[DataProvider('valoresDeSiNo')]
    public function pdfTambienEsUnCampoDeSiNo(string $escrito, bool $esperado): void
    {
        self::assertSame(['pdf' => $esperado], $this->normalizar("pdf: {$escrito}"));
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function unPdfQueNoEsSiNoAvisaYSeDescarta(): void
    {
        self::assertSame([], $this->normalizar('pdf: quizá'));
        self::assertSame(['contenido/a.md:2: «pdf» tiene que ser sí o no'], $this->avisos());
    }

    #[Test]
    #[DataProvider('valoresDeSiNo')]
    public function listadaTambienEsUnCampoDeSiNo(string $escrito, bool $esperado): void
    {
        self::assertSame(['listada' => $esperado], $this->normalizar("listada: {$escrito}"));
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function avisaDeUnSiNoQueNoVale(): void
    {
        self::assertSame([], $this->normalizar('borrador: quizá'));
        self::assertSame(['contenido/a.md:2: «borrador» tiene que ser sí o no'], $this->avisos());
    }

    #[Test]
    public function leeLasFechasConYSinComillas(): void
    {
        foreach (['fecha: 2025-03-18', 'fecha: "2025-03-18"', "fecha: '2025-03-18'"] as $yaml) {
            $fecha = $this->normalizar($yaml)['fecha'];

            self::assertInstanceOf(\DateTimeImmutable::class, $fecha, $yaml);
            self::assertSame('2025-03-18 00:00:00 UTC', $fecha->format('Y-m-d H:i:s e'), $yaml);
        }

        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function lasFechasSonElDiaEnLaZonaDelSitio(): void
    {
        $madrid = new \DateTimeZone('Europe/Madrid');

        foreach (['fecha: 2025-03-18', 'fecha: "2025-03-18"'] as $yaml) {
            $fecha = Campos::normalizar(Yaml::leerCampos($yaml, 'contenido/a.md', 2), $yaml, 2, 'contenido/a.md', $this->avisos, $madrid)['fecha'];

            self::assertSame('2025-03-18 00:00:00 Europe/Madrid', $fecha->format('Y-m-d H:i:s e'), $yaml);
        }
    }

    #[Test]
    public function avisaDeUnaFechaQueYamlNoPuedeLeerConOSinComillas(): void
    {
        // Un mes 13 o un día 32 sin comillas: YAML no llega a leerlos como fecha
        self::assertSame([], $this->normalizar('fecha: 2026-13-01'));
        self::assertSame([], $this->normalizar('publicar: 2026-01-32'));
        self::assertSame([], $this->normalizar('fecha: "2026-00-10"'));

        self::assertSame([
            'contenido/a.md:2: «fecha» no es una fecha posible: 2026-13-01',
            'contenido/a.md:2: «publicar» no es una fecha posible: 2026-01-32',
            'contenido/a.md:2: «fecha» no es una fecha posible: 2026-00-10',
        ], $this->avisos());
    }

    #[Test]
    public function avisaDeUnaFechaImposibleConOSinComillas(): void
    {
        self::assertSame([], $this->normalizar('fecha: 2025-02-31'));
        self::assertSame([], $this->normalizar('publicar: "2025-02-30"'));

        self::assertSame([
            'contenido/a.md:2: «fecha» no es una fecha posible: 2025-02-31',
            'contenido/a.md:2: «publicar» no es una fecha posible: 2025-02-30',
        ], $this->avisos());
    }

    #[Test]
    public function elAvisoUsaElNombreDelCampoTalComoSeEscribio(): void
    {
        self::assertSame(['titulo' => 'Hola'], $this->normalizar("title: Hola\ndate: mañana"));
        self::assertSame(['contenido/a.md:3: «date» tiene que ser una fecha AAAA-MM-DD'], $this->avisos());
    }

    #[Test]
    public function aceptaOrdenesNumericos(): void
    {
        self::assertSame(['orden' => 3], $this->normalizar('orden: 3'));
        self::assertSame(['orden' => 2.5], $this->normalizar('orden: "2.5"'));
        self::assertSame([], $this->normalizar('orden: primero'));

        self::assertSame(['contenido/a.md:2: «orden» tiene que ser un número'], $this->avisos());
    }

    #[Test]
    public function losNumerosEnCamposDeTextoSeQuedanComoTexto(): void
    {
        self::assertSame(['titulo' => '2024'], $this->normalizar('titulo: 2024'));
    }

    #[Test]
    public function avisaDeUnCampoDeTextoQueNoEsTexto(): void
    {
        self::assertSame([], $this->normalizar('imagen: [a.jpg, b.jpg]'));
        self::assertSame(['contenido/a.md:2: «imagen» tiene que ser un texto'], $this->avisos());
    }

    #[Test]
    public function lasEtiquetasSonTextosSinRepetir(): void
    {
        self::assertSame(['etiquetas' => ['a', 'b', '3']], $this->normalizar('etiquetas: [a, b, a, 3]'));
    }

    #[Test]
    public function unaEtiquetaSueltaEsUnaListaDeUna(): void
    {
        self::assertSame(['etiquetas' => ['servicio']], $this->normalizar('tags: servicio'));
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function laEtiquetaTodoSeQuitaConAviso(): void
    {
        self::assertSame(['etiquetas' => ['blog']], $this->normalizar('etiquetas: [todo, blog]'));
        self::assertSame(
            ['contenido/a.md:2: «todo» es el nombre de la colección con todas las páginas; no se puede usar como etiqueta y se quita'],
            $this->avisos(),
        );
    }

    #[Test]
    public function avisaDeEtiquetasQueNoSonTextos(): void
    {
        self::assertSame([], $this->normalizar("tags:\n  blog: sí"));
        self::assertSame([], $this->normalizar('etiquetas: [blog, [a, b]]'));

        self::assertSame([
            'contenido/a.md:2: «tags» tiene que ser un texto o una lista de textos, por ejemplo [blog]',
            'contenido/a.md:2: «etiquetas» tiene que ser un texto o una lista de textos, por ejemplo [blog]',
        ], $this->avisos());
    }

    #[Test]
    public function laPlantillaEsUnNombreOFalse(): void
    {
        self::assertSame(['plantilla' => 'articulo'], $this->normalizar('plantilla: articulo'));
        self::assertSame(['plantilla' => false], $this->normalizar('plantilla: false'));
        self::assertSame(['plantilla' => false], $this->normalizar('layout: ""'));
        self::assertSame([], $this->normalizar('plantilla: true'));

        self::assertSame(['contenido/a.md:2: «plantilla» tiene que ser el nombre de una plantilla o false'], $this->avisos());
    }

    #[Test]
    public function laUrlEsUnTextoOFalse(): void
    {
        self::assertSame(['url' => '/hola/'], $this->normalizar('url: /hola/'));
        self::assertSame(['url' => false], $this->normalizar('permalink: false'));
        self::assertSame([], $this->normalizar('url: true'));

        self::assertSame(['contenido/a.md:2: «url» tiene que ser un texto o false'], $this->avisos());
    }

    /**
     * @return array<array-key, mixed>
     */
    private function normalizar(string $yaml): array
    {
        return Campos::normalizar(Yaml::leerCampos($yaml, 'contenido/a.md', 2), $yaml, 2, 'contenido/a.md', $this->avisos);
    }

    /**
     * @return list<string>
     */
    private function avisos(): array
    {
        return array_map(strval(...), $this->avisos->todos());
    }
}
