<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Pdf;

use Ehundu\ErrorDeProyecto;
use Ehundu\Pdf\GeneradorDePdf;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GeneradorDePdfPrueba extends TestCase
{
    use CarpetaTemporal;

    private const string HTML = '<html><head><meta charset="utf-8"><style>@page { margin: 20mm } h1 { font-size: 20pt }</style></head><body><h1>Menú del día</h1><p>Ñandú a la plancha, 12,50 €</p></body></html>';

    protected function setUp(): void
    {
        $this->crearSitioMinimo();
    }

    #[Test]
    public function generaUnPdfDeUnaHoja(): void
    {
        [$pdf, $avisos] = $this->generador()->generar(self::HTML, 'Menú', null);

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertSame(1, preg_match_all('#/Type\s*/Page\b(?!s)#', $pdf));
        self::assertSame([], $avisos);
    }

    #[Test]
    public function elMismoHtmlDaLosMismosBytes(): void
    {
        $fecha = new \DateTimeImmutable('2026-09-29', new \DateTimeZone('Europe/Madrid'));

        [$uno] = $this->generador()->generar(self::HTML, 'Menú', $fecha);
        [$dos] = $this->generador()->generar(self::HTML, 'Menú', $fecha);

        self::assertSame(hash('sha256', $uno), hash('sha256', $dos));
    }

    #[Test]
    public function otroHtmlDaOtroPdf(): void
    {
        [$uno] = $this->generador()->generar(self::HTML, 'Menú', null);
        [$dos] = $this->generador()->generar(str_replace('Menú', 'Carta', self::HTML), 'Menú', null);

        self::assertNotSame($uno, $dos);
    }

    #[Test]
    public function lasFechasDelDocumentoSonLasDeLaPaginaAMedianocheEnLaZonaDelSitio(): void
    {
        $fecha = new \DateTimeImmutable('2026-09-29 18:30:00', new \DateTimeZone('Europe/Madrid'));

        [$pdf] = $this->generador('Europe/Madrid')->generar(self::HTML, 'Menú', $fecha);

        self::assertStringContainsString("/CreationDate (D:20260929000000+02'00')", $pdf);
        self::assertStringContainsString("/ModDate (D:20260929000000+02'00')", $pdf);
    }

    #[Test]
    public function sinFechaLlevaElUnoDeEneroDeMilNovecientosSetenta(): void
    {
        [$pdf] = $this->generador('UTC')->generar(self::HTML, 'Menú', null);

        self::assertStringContainsString("/CreationDate (D:19700101000000+00'00')", $pdf);
    }

    #[Test]
    public function elTituloEsElDeLaPagina(): void
    {
        [$pdf] = $this->generador()->generar(self::HTML, 'Menú', null);

        // dompdf escribe los textos de los metadatos en UTF-16BE
        self::assertStringContainsString(mb_convert_encoding('Menú', 'UTF-16BE', 'UTF-8'), $pdf);
    }

    #[Test]
    public function leeLasImagenesDePublicoConRutasQueEmpiezanPorBarra(): void
    {
        $this->crearFichero('publico/img/punto.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><circle cx="5" cy="5" r="4" fill="#c00"/></svg>');

        [$con, $avisos] = $this->generador()->generar('<html><body><p><img src="/img/punto.svg" width="20" height="20"></p></body></html>', 'a', null);
        [$sin] = $this->generador()->generar('<html><body><p></p></body></html>', 'a', null);

        self::assertSame([], $avisos);
        self::assertGreaterThan(strlen($sin), strlen($con));
    }

    #[Test]
    public function noLeeNadaDeFueraDePublicoYLoAvisa(): void
    {
        $this->crearFichero('datos/secreto.png', 'no');
        $this->crearFichero('publico/vacio.txt', '');

        [$pdf, $avisos] = $this->generador()->generar(
            '<html><body><p><img src="' . $this->carpetaTemporal() . '/datos/secreto.png"><img src="/../datos/secreto.png"></p></body></html>',
            'a',
            null,
        );

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertNotSame([], $avisos);

        foreach ($avisos as $aviso) {
            self::assertStringNotContainsString($this->carpetaTemporal(), $aviso);
        }
    }

    #[Test]
    public function noCargaNadaRemoto(): void
    {
        [, $avisos] = $this->generador()->generar('<html><body><img src="http://127.0.0.1:9/no.png"></body></html>', 'a', null);

        self::assertNotSame([], $avisos);
    }

    #[Test]
    public function avisaDeLoQueDompdfNoEntiende(): void
    {
        [, $avisos] = $this->generador()->generar('<html><head><style>p { display: flex; gap: 4px }</style></head><body><p>a</p></body></html>', 'a', null);

        self::assertNotSame([], $avisos);
    }

    #[Test]
    public function noEjecutaPhpDeLaPlantilla(): void
    {
        $this->expectNotToPerformAssertions();

        $this->generador()->generar('<html><body><script type="text/php">throw new Exception("no");</script></body></html>', 'a', null);
    }

    #[Test]
    public function noEscribeNadaEnElProyectoNiEnVendor(): void
    {
        $antes = $this->contarFicheros(dirname(__DIR__, 2) . '/vendor/dompdf');

        $this->generador()->generar(self::HTML, 'a', null);

        self::assertSame($antes, $this->contarFicheros(dirname(__DIR__, 2) . '/vendor/dompdf'));
        self::assertSame([], glob($this->carpetaTemporal() . '/salida/*') ?: []);
    }

    #[Test]
    public function siLaHojaSeAcabaPasaALaSiguiente(): void
    {
        $largo = '<html><body>' . str_repeat('<p>Una línea de texto del menú.</p>', 120) . '</body></html>';

        [$pdf] = $this->generador()->generar($largo, 'a', null);

        self::assertGreaterThan(1, preg_match_all('#/Type\s*/Page\b(?!s)#', $pdf));
    }

    #[Test]
    public function laHojaEsA4SalvoQueElCssDigaOtraCosa(): void
    {
        [$a4] = $this->generador()->generar('<html><body><p>a</p></body></html>', 'a', null);
        [$apaisada] = $this->generador()->generar('<html><head><style>@page { size: A4 landscape }</style></head><body><p>a</p></body></html>', 'a', null);

        self::assertMatchesRegularExpression('#/MediaBox\s*\[0(?:\.0+)? 0(?:\.0+)? 595\.\d+ 841\.\d+\]#', $a4);
        self::assertMatchesRegularExpression('#/MediaBox\s*\[0(?:\.0+)? 0(?:\.0+)? 841\.\d+ 595\.\d+\]#', $apaisada);
    }

    private function generador(string $zona = 'Europe/Madrid'): GeneradorDePdf
    {
        return new GeneradorDePdf(Proyecto::abrir($this->carpetaTemporal()), new \DateTimeZone($zona));
    }

    private function contarFicheros(string $carpeta): int
    {
        $n = 0;

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($carpeta, \FilesystemIterator::SKIP_DOTS)) as $fichero) {
            $n += $fichero->isFile() ? 1 : 0;
        }

        return $n;
    }
}
