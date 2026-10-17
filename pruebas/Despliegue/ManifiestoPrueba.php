<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Despliegue;

use Ehundu\Despliegue\Manifiesto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ManifiestoPrueba extends TestCase
{
    #[Test]
    public function guardaRutaMd5YTamanoYSeVuelveALeer(): void
    {
        $manifiesto = new Manifiesto();
        $manifiesto->anotar('index.html', md5('hola'), 4);
        $manifiesto->anotar('css/a.css', md5('a{}'), 3);

        $json = $manifiesto->json();

        self::assertSame(<<<JSON
            {
                "ehundu": 1,
                "ficheros": {
                    "css/a.css": {
                        "md5": "{$this->md5('a{}')}",
                        "tamano": 3
                    },
                    "index.html": {
                        "md5": "{$this->md5('hola')}",
                        "tamano": 4
                    }
                }
            }

            JSON, $json);
        self::assertEquals($manifiesto->ficheros(), Manifiesto::leer($json)->ficheros());
    }

    #[Test]
    public function guardaLaUrlDelSitioDelanteDeLosFicheros(): void
    {
        $manifiesto = new Manifiesto(['index.html' => ['md5' => md5('hola'), 'tamano' => 4]], 'https://www.ejemplo.com');

        $json = $manifiesto->json();

        self::assertStringStartsWith("{\n    \"ehundu\": 1,\n    \"url\": \"https://www.ejemplo.com\",\n    \"ficheros\": {", $json);
        self::assertSame('https://www.ejemplo.com', Manifiesto::leer($json)->url);
    }

    #[Test]
    public function unManifiestoSinUrlDeEhundu01SeLee(): void
    {
        $manifiesto = Manifiesto::leer('{"ehundu": 1, "ficheros": {"a.html": {"md5": "x", "tamano": 1}}}');

        self::assertNull($manifiesto->url);
        self::assertSame(['a.html'], array_keys($manifiesto->ficheros()));
    }

    #[Test]
    public function unaUrlQueNoEsUnTextoNoSeLee(): void
    {
        $this->expectExceptionMessage('tiene una «url» que no se entiende');

        Manifiesto::leer('{"ehundu": 1, "url": 3, "ficheros": {}}');
    }

    #[Test]
    public function unManifiestoVacioSigueSiendoUnObjeto(): void
    {
        self::assertSame([], Manifiesto::leer((new Manifiesto())->json())->ficheros());
        self::assertStringContainsString('"ficheros": {}', (new Manifiesto())->json());
    }

    #[Test]
    public function loQueNoEsUnManifiestoNoSeLee(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        Manifiesto::leer('{"ficheros": []}');
    }

    #[Test]
    public function unaEntradaRotaNoSeLee(): void
    {
        $this->expectExceptionMessage('la entrada de «a.html» no se entiende');

        Manifiesto::leer('{"ehundu": 1, "ficheros": {"a.html": {"md5": 3}}}');
    }

    /**
     * El manifiesto vive en el destino y dice qué borrar: una ruta que sale del sitio no se lee.
     *
     * @return iterable<string, array{string}>
     */
    public static function rutasQueSalenDelSitio(): iterable
    {
        yield 'un tramo ..' => ['../fuera.html'];
        yield 'un .. en medio' => ['css/../../fuera.html'];
        yield 'un .. al final' => ['css/..'];
        yield 'una ruta absoluta' => ['/etc/passwd'];
        yield 'una unidad de Windows' => ['C:/Windows/sistema.ini'];
        yield 'barras invertidas' => ['css\\a.css'];
        yield '.. con barra invertida' => ['..\\fuera.html'];
        yield 'un byte nulo' => ["a.html\0.txt"];
        yield 'un tramo vacío' => ['css//a.css'];
        yield 'un tramo .' => ['./a.html'];
        yield 'una ruta vacía' => [''];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('rutasQueSalenDelSitio')]
    public function unaRutaQueSaleDelSitioNoSeLee(string $ruta): void
    {
        $json = json_encode(['ehundu' => 1, 'ficheros' => [$ruta => ['md5' => md5('x'), 'tamano' => 1]]], JSON_THROW_ON_ERROR);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('no es una ruta de dentro del sitio');

        Manifiesto::leer($json);
    }

    #[Test]
    public function lasRutasNormalesSeLeenTambienConPuntosEspaciosYTildes(): void
    {
        $rutas = ['index.html', 'css/a.css', '.well-known/security.txt', '.htaccess', 'img/foto.v2.jpg', 'fotos/la casa.jpg', 'niños/ñandú.html', 'a..b/c.txt', 'C:foto.jpg', 'dir/C:'];
        $ficheros = array_fill_keys($rutas, ['md5' => md5('x'), 'tamano' => 1]);

        $leido = Manifiesto::leer(json_encode(['ehundu' => 1, 'ficheros' => $ficheros], JSON_THROW_ON_ERROR));

        self::assertSame($rutas, array_keys($leido->ficheros()));
    }

    #[Test]
    public function elDesplieguePasaPorElErrorDelManifiestoDeSiempre(): void
    {
        // Lo que se lee en el destino llega a `Desplegador` como UnexpectedValueException y este lo cuenta como un manifiesto roto
        try {
            Manifiesto::leer('{"ehundu": 1, "ficheros": {"../x": {"md5": "a", "tamano": 1}}}');
            self::fail('Tenía que lanzar');
        } catch (\UnexpectedValueException $error) {
            self::assertSame('la ruta «../x» no es una ruta de dentro del sitio', $error->getMessage());
        }
    }

    private function md5(string $texto): string
    {
        return md5($texto);
    }
}
