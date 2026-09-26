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

    private function md5(string $texto): string
    {
        return md5($texto);
    }
}
