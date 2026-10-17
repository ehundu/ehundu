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
    public function unaClaseDeCssSonNombresSeparadosPorEspacios(): void
    {
        self::assertSame('ancla', Campos::claseDeCss('ancla'));
        self::assertSame('a b_c d-e', Campos::claseDeCss(' a b_c d-e '));
        self::assertSame('_x', Campos::claseDeCss('_x'));

        foreach ([null, '', 'con comillas"', 'dos  espacios', '1clase', '-guion', ['ancla'], 3] as $malo) {
            self::assertNull(Campos::claseDeCss($malo), var_export($malo, true));
        }
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

    /**
     * Los nombres que el motor escribe siempre en `pagina` (formato §7.2, decisión 133).
     *
     * @return iterable<string, array{string}>
     */
    public static function nombresDelMotor(): iterable
    {
        foreach (['ruta', 'contenido', 'markdown', 'encabezados', 'traducciones'] as $nombre) {
            yield $nombre => [$nombre];
        }
    }

    #[Test]
    #[DataProvider('nombresDelMotor')]
    public function unCampoConUnNombreQueElMotorSiempreEscribeSeAvisaYSeIgnora(string $nombre): void
    {
        $campos = $this->normalizar("titulo: Inicio\n{$nombre}: texto propio");

        self::assertSame(['titulo' => 'Inicio'], $campos);
        self::assertSame(
            ["contenido/a.md:3: «{$nombre}» es un nombre que el motor escribe siempre en la página (formato §7.2) y taparía este campo; se ignora"],
            $this->avisos(),
        );
    }

    #[Test]
    public function losNombresDelMotorQueNoSiempreSeEscribenSeDejanPasar(): void
    {
        // `grupo`, `paginas` y `paginacion` solo los escribe el motor en lo que genera `agrupar` o `paginar`
        $yaml = "grupo: Rojos\npaginas: una lista\npaginacion: algo\nnombre: Ana";

        self::assertSame(['grupo' => 'Rojos', 'paginas' => 'una lista', 'paginacion' => 'algo', 'nombre' => 'Ana'], $this->normalizar($yaml));
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function componentesEstaReservadoAvisaPeroSigueSiendoUnCampo(): void
    {
        $campos = $this->normalizar("titulo: Inicio\ncomponentes: [uno, dos]");

        self::assertSame(['titulo' => 'Inicio', 'componentes' => ['uno', 'dos']], $campos);
        self::assertSame(
            ['contenido/a.md:3: «componentes» está reservado para el catálogo de componentes (formato §13) y un día lo usará el motor; usa otro nombre para este campo'],
            $this->avisos(),
        );
    }

    #[Test]
    public function componentesEnBlancoNoAvisa(): void
    {
        $this->normalizar("titulo: Inicio\ncomponentes:");

        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function unCampoEnBlancoConUnNombreDelMotorNoAvisa(): void
    {
        // Como cualquier campo en blanco, pasa sin aviso y sin valor: el motor lo cubre igual
        self::assertSame(['titulo' => 'Inicio', 'markdown' => null], $this->normalizar("titulo: Inicio\nmarkdown:"));
        self::assertSame([], $this->avisos());
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

    /**
     * Una fecha es un día (formato §4, decisión 129): con hora o con zona, la hora no cuenta, se toma el día y se avisa.
     *
     * @return iterable<string, array{string}>
     */
    public static function fechasConHora(): iterable
    {
        yield 'con segundos' => ['fecha: 2026-10-01 18:30:00'];
        yield 'con segundos y zona' => ['fecha: 2026-10-01T18:30:00+02:00'];
        yield 'en UTC con Z' => ['fecha: 2026-10-01T18:30:00Z'];
        yield 'a medianoche pero con zona' => ['fecha: 2026-10-01T00:00:00+02:00'];
        yield 'sin segundos (YAML lo lee como texto)' => ['fecha: 2026-10-01 18:30'];
        yield 'entre comillas' => ['fecha: "2026-10-01 18:30:00"'];
        yield 'con T y sin segundos' => ['fecha: "2026-10-01T18:30"'];
        yield 'publicar con segundos' => ['publicar: 2026-10-01 09:00:00'];
        yield 'publicar sin segundos' => ['publicar: 2026-10-01 09:00'];
        yield 'el alias date' => ['date: 2026-10-01 18:30:00'];
    }

    #[Test]
    #[DataProvider('fechasConHora')]
    public function unaFechaConHoraSeTomaSoloElDiaYSeAvisa(string $yaml): void
    {
        $zona = new \DateTimeZone('Europe/Madrid');
        $campo = $this->nombreDelCampo($yaml);

        $campos = Campos::normalizar(Yaml::leerCampos($yaml, 'contenido/a.md', 2), $yaml, 2, 'contenido/a.md', $this->avisos, $zona);

        self::assertInstanceOf(\DateTimeImmutable::class, $campos[$campo], $yaml);
        self::assertSame('2026-10-01 00:00:00 Europe/Madrid', $campos[$campo]->format('Y-m-d H:i:s e'), $yaml);
        self::assertSame(
            ["contenido/a.md:2: «{$this->escritoEn($yaml)}» es un día, AAAA-MM-DD: la hora no cuenta y se toma solo el día"],
            $this->avisos(),
            $yaml,
        );
    }

    #[Test]
    public function unDiaSinHoraNoAvisaDeNada(): void
    {
        foreach (['fecha: 2026-10-01', 'fecha: "2026-10-01"', 'publicar: 2026-10-01', 'fecha: 2026-10-01 00:00:00'] as $yaml) {
            $campos = $this->normalizar($yaml);

            self::assertSame('2026-10-01', $campos[$this->nombreDelCampo($yaml)]->format('Y-m-d'), $yaml);
        }

        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function unaHoraConUnDiaImposibleSigueSiendoUnaFechaImposible(): void
    {
        self::assertSame([], $this->normalizar('fecha: "2026-02-31 09:00"'));
        self::assertSame(['contenido/a.md:2: «fecha» no es una fecha posible: 2026-02-31'], $this->avisos());
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
    public function publicarEnSuFechaEsUnSiONo(): void
    {
        self::assertSame(['publicarEnSuFecha' => true], $this->normalizar('publicarEnSuFecha: sí'));
        self::assertSame(['publicarEnSuFecha' => false], $this->normalizar('publicarEnSuFecha: no'));
        self::assertSame(['publicarEnSuFecha' => true], $this->normalizar('publicarEnSuFecha: true'));
        self::assertSame([], $this->avisos());

        self::assertSame([], $this->normalizar('publicarEnSuFecha: pronto'));
        self::assertSame(['contenido/a.md:2: «publicarEnSuFecha» tiene que ser sí o no'], $this->avisos());
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

    /** El nombre que se le da al campo en el resultado: `date` es `fecha`. */
    private function nombreDelCampo(string $yaml): string
    {
        $escrito = $this->escritoEn($yaml);

        return Campos::ALIAS[$escrito] ?? $escrito;
    }

    /** El nombre del campo tal como está escrito en el YAML. */
    private function escritoEn(string $yaml): string
    {
        return explode(':', $yaml, 2)[0];
    }

    /**
     * @return list<string>
     */
    private function avisos(): array
    {
        return array_map(strval(...), $this->avisos->todos());
    }
}
