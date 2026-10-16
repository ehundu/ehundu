<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Imagenes;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use Ehundu\Pruebas\Apoyo\Png;
use Ehundu\Pruebas\Apoyo\Webp;
use Ehundu\Variantes;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Las variantes de ancho de una imagen (formato §2 y §7.3): las encuentra
 * según `imagenes`, lee su ancho real y apunta cada fichero que mira.
 */
final class VariantesPrueba extends TestCase
{
    use CarpetaTemporal;

    /** @var list<string> */
    private array $anotados = [];

    /** @var list<string> */
    private array $avisos = [];

    protected function setUp(): void
    {
        $this->crearSitioMinimo();
    }

    #[Test]
    public function unSoloFormatoDaUnSrcsetConElAnchoRealDeCadaFichero(): void
    {
        $this->webp('img/foto-480.webp', 480, 320);
        $this->webp('img/foto-960.webp', 960, 640);
        $this->webp('img/foto-1600.webp', 1024, 683);

        $variantes = $this->variantes([480, 960, 1600], ['webp'])->buscar('img/foto', $this->avisar(...));

        self::assertSame([
            'src' => '/img/foto-1600.webp',
            'ancho' => 1024,
            'alto' => 683,
            'srcset' => '/img/foto-480.webp 480w, /img/foto-960.webp 960w, /img/foto-1600.webp 1024w',
            'fuentes' => [],
        ], $variantes);
        self::assertSame([], $this->avisos);
    }

    #[Test]
    public function conVariosFormatosElUltimoEsElDeReservaYLosAnterioresSonFuentes(): void
    {
        $this->webp('img/foto-480.webp', 480, 320);
        $this->webp('img/foto-960.webp', 960, 640);
        $this->png('img/foto-480.png', 480, 320);
        $this->png('img/foto-960.png', 960, 640);

        $variantes = $this->variantes([480, 960], ['webp', 'png'])->buscar('img/foto', $this->avisar(...));

        self::assertSame('/img/foto-960.png', $variantes['src']);
        self::assertSame(960, $variantes['ancho']);
        self::assertSame(640, $variantes['alto']);
        self::assertSame('/img/foto-480.png 480w, /img/foto-960.png 960w', $variantes['srcset']);
        self::assertSame(
            [['tipo' => 'image/webp', 'srcset' => '/img/foto-480.webp 480w, /img/foto-960.webp 960w']],
            $variantes['fuentes'],
        );
        self::assertSame([], $this->avisos);
    }

    #[Test]
    public function dosAnchosPedidosQueDanElMismoAnchoRealJuntanSusFicherosEnElMenor(): void
    {
        // Un original de 700 px: quien genera no lo amplía, y los dos ficheros salen igual de anchos.
        $this->webp('img/chica-480.webp', 480, 320);
        $this->webp('img/chica-960.webp', 700, 467);
        $this->webp('img/chica-1600.webp', 700, 467);

        $variantes = $this->variantes([480, 960, 1600], ['webp'])->buscar('img/chica', $this->avisar(...));

        self::assertSame('/img/chica-480.webp 480w, /img/chica-960.webp 700w', $variantes['srcset']);
        self::assertSame('/img/chica-960.webp', $variantes['src']);
    }

    #[Test]
    public function siFaltaUnaVarianteAvisaDeEseFicheroYSigueConLasQueHay(): void
    {
        $this->webp('img/foto-480.webp', 480, 320);
        $this->webp('img/foto-1600.webp', 1600, 1067);

        $variantes = $this->variantes([480, 960, 1600], ['webp'])->buscar('img/foto', $this->avisar(...));

        self::assertSame('/img/foto-480.webp 480w, /img/foto-1600.webp 1600w', $variantes['srcset']);
        self::assertSame(['no existe publico/img/foto-960.webp; la imagen sale sin esa variante'], $this->avisos);
    }

    #[Test]
    public function unFormatoSinNingunaVarianteNoEntraYAvisaDeCadaFichero(): void
    {
        $this->png('img/foto-480.png', 480, 320);

        $variantes = $this->variantes([480, 960], ['avif', 'png'])->buscar('img/foto', $this->avisar(...));

        self::assertSame([], $variantes['fuentes']);
        self::assertSame('/img/foto-480.png 480w', $variantes['srcset']);
        self::assertSame([
            'no existe publico/img/foto-480.avif; la imagen sale sin esa variante',
            'no existe publico/img/foto-960.avif; la imagen sale sin esa variante',
            'no existe publico/img/foto-960.png; la imagen sale sin esa variante',
        ], $this->avisos);
    }

    #[Test]
    public function sinNingunaVarianteDaNullYNoAvisa(): void
    {
        $this->png('img/foto.png', 800, 600);

        self::assertNull($this->variantes([480, 960], ['webp'])->buscar('img/foto', $this->avisar(...)));
        self::assertSame([], $this->avisos);
    }

    #[Test]
    public function unFicheroQueNoEsUnaImagenSeAvisaYNoCuenta(): void
    {
        $this->crearFichero('publico/img/foto-480.webp', 'esto no es una imagen');
        $this->webp('img/foto-960.webp', 960, 640);

        $variantes = $this->variantes([480, 960], ['webp'])->buscar('img/foto', $this->avisar(...));

        self::assertSame('/img/foto-960.webp 960w', $variantes['srcset']);
        self::assertSame(['publico/img/foto-480.webp no es una imagen que se pueda leer; la imagen sale sin esa variante'], $this->avisos);
    }

    #[Test]
    public function unNombreConEspaciosOTildesSeCodificaParaQueElSrcsetSigaSiendoValido(): void
    {
        $this->webp('img/mi foto-480.webp', 480, 320);
        $this->webp('img/mi foto-960.webp', 960, 640);

        $variantes = $this->variantes([480, 960], ['webp'])->buscar('img/mi foto', $this->avisar(...));

        self::assertSame('/img/mi%20foto-480.webp 480w, /img/mi%20foto-960.webp 960w', $variantes['srcset']);
        self::assertSame('/img/mi%20foto-960.webp', $variantes['src']);
    }

    #[Test]
    public function apuntaCadaFicheroQueMiraAunqueNoExista(): void
    {
        $this->webp('img/foto-480.webp', 480, 320);

        $this->variantes([480, 960], ['webp', 'png'])->buscar('img/foto', $this->avisar(...));

        self::assertSame(['img/foto-480.webp', 'img/foto-960.webp', 'img/foto-480.png', 'img/foto-960.png'], $this->anotados);
    }

    #[Test]
    public function sinLaSeccionNoBuscaNiApuntaNada(): void
    {
        $this->webp('img/foto-480.webp', 480, 320);
        $variantes = new Variantes(Proyecto::abrir($this->carpetaTemporal()), new Imagenes(), $this->anotar(...));

        self::assertFalse($variantes->activas());
        self::assertNull($variantes->buscar('img/foto', $this->avisar(...)));
        self::assertSame([], $this->anotados);
    }

    #[Test]
    public function elTroncoEsLaRutaSinLaExtensionDeUnaImagen(): void
    {
        self::assertSame('img/foto', Variantes::troncoDe('img/foto.jpg'));
        self::assertSame('img/foto', Variantes::troncoDe('img/foto.JPEG'));
        self::assertSame('img/foto', Variantes::troncoDe('img/foto'));
        self::assertSame('img/foto.v2', Variantes::troncoDe('img/foto.v2'));
        self::assertSame('img/foto.v2', Variantes::troncoDe('img/foto.v2.webp'));
        self::assertNull(Variantes::troncoDe('svg/logo.svg'));
    }

    #[Test]
    public function soloSeLeenRutasDeDentroDePublico(): void
    {
        self::assertTrue(Variantes::rutaValida('img/foto.jpg'));
        self::assertFalse(Variantes::rutaValida(''));
        self::assertFalse(Variantes::rutaValida('../sitio.yml'));
        self::assertFalse(Variantes::rutaValida('img/../../sitio.yml'));
        self::assertFalse(Variantes::rutaValida('img\\foto.jpg'));
    }

    /**
     * @param list<int>    $anchos
     * @param list<string> $formatos
     */
    private function variantes(array $anchos, array $formatos): Variantes
    {
        return new Variantes(Proyecto::abrir($this->carpetaTemporal()), new Imagenes($anchos, $formatos), $this->anotar(...));
    }

    private function anotar(string $fichero): void
    {
        $this->anotados[] = $fichero;
    }

    private function avisar(string $mensaje): void
    {
        $this->avisos[] = $mensaje;
    }

    private function webp(string $ruta, int $ancho, int $alto): void
    {
        $this->crearFichero("publico/{$ruta}", Webp::de($ancho, $alto));
    }

    private function png(string $ruta, int $ancho, int $alto): void
    {
        $this->crearFichero("publico/{$ruta}", Png::de($ancho, $alto));
    }
}
