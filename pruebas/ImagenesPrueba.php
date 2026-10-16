<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Avisos;
use Ehundu\Imagenes;
use Ehundu\Lector;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * La sección `imagenes` de `sitio.yml` (formato §2): los anchos y los formatos
 * de las variantes de cada imagen, y el `sizes` por defecto.
 */
final class ImagenesPrueba extends TestCase
{
    use CarpetaTemporal;

    private Avisos $avisos;

    protected function setUp(): void
    {
        $this->avisos = new Avisos();
    }

    #[Test]
    public function sinLaSeccionElSitioNoDeclaraVariantes(): void
    {
        $imagenes = Imagenes::leer(null, null, $this->avisos);

        self::assertFalse($imagenes->activa());
        self::assertSame([], $imagenes->anchos);
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function conSoloLosAnchosSeUsanWebpYElSizesDeCienViewports(): void
    {
        $imagenes = Imagenes::leer(['anchos' => [1600, 480, 960, 480]], 3, $this->avisos);

        self::assertTrue($imagenes->activa());
        self::assertSame([480, 960, 1600], $imagenes->anchos);
        self::assertSame(['webp'], $imagenes->formatos);
        self::assertSame('100vw', $imagenes->sizes);
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function seLeenTodosSusCampos(): void
    {
        $imagenes = Imagenes::leer(
            ['anchos' => [480], 'formatos' => ['AVIF', ' webp ', 'avif'], 'sizes' => ' (min-width: 60rem) 50rem, 100vw '],
            3,
            $this->avisos,
        );

        self::assertSame(['avif', 'webp'], $imagenes->formatos);
        self::assertSame('(min-width: 60rem) 50rem, 100vw', $imagenes->sizes);
        self::assertSame('image/avif', Imagenes::tipoDe('avif'));
        self::assertSame('image/jpeg', Imagenes::tipoDe('jpg'));
        self::assertSame('image/jpeg', Imagenes::tipoDe('jpeg'));
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function sinAnchosLaSeccionSeIgnoraEntera(): void
    {
        foreach ([[], ['formatos' => ['avif']], ['anchos' => []], ['anchos' => 480], ['anchos' => ['480']], ['anchos' => [0, -5, 2.5]]] as $valor) {
            $avisos = new Avisos();
            $imagenes = Imagenes::leer($valor, 4, $avisos);

            self::assertFalse($imagenes->activa(), var_export($valor, true));
            self::assertSame(['webp'], $imagenes->formatos);
            self::assertNotSame([], $avisos->todos(), var_export($valor, true));
        }
    }

    #[Test]
    public function unAnchoQueNoValeSeAvisaYSeIgnoranLosDemas(): void
    {
        $imagenes = Imagenes::leer(['anchos' => [480, 'ancho', 0, 960]], 5, $this->avisos);

        self::assertSame([480, 960], $imagenes->anchos);
        self::assertSame([
            'sitio.yml:5: «imagenes»: «ancho» no es un ancho válido (un número entero mayor que cero); se ignora',
            'sitio.yml:5: «imagenes»: «0» no es un ancho válido (un número entero mayor que cero); se ignora',
        ], $this->avisos());
    }

    #[Test]
    public function loQueNoValeEnLosFormatosYElSizesSeAvisaYSeIgnora(): void
    {
        $imagenes = Imagenes::leer(['anchos' => [480], 'formatos' => ['gif', 'webp', 7], 'sizes' => ['100vw'], 'otro' => 1], 2, $this->avisos);

        self::assertSame(['webp'], $imagenes->formatos);
        self::assertSame('100vw', $imagenes->sizes);
        self::assertSame([
            'sitio.yml:2: «imagenes» no admite «otro»; se ignora',
            'sitio.yml:2: «imagenes»: «formatos» no admite «gif» (valen avif, webp, jpg, jpeg, png); se ignora',
            'sitio.yml:2: «imagenes»: «formatos» no admite «7» (valen avif, webp, jpg, jpeg, png); se ignora',
            'sitio.yml:2: «imagenes»: «sizes» tiene que ser un texto, por ejemplo "(min-width: 60rem) 50rem, 100vw"; se usa 100vw',
        ], $this->avisos());
    }

    #[Test]
    public function sinNingunFormatoValidoSeUsaWebp(): void
    {
        $imagenes = Imagenes::leer(['anchos' => [480], 'formatos' => ['gif']], 2, $this->avisos);

        self::assertSame(['webp'], $imagenes->formatos);
        self::assertContains('sitio.yml:2: «imagenes»: «formatos» no tiene ningún formato válido; se usa webp', $this->avisos());

        $avisos = new Avisos();
        self::assertSame(['webp'], Imagenes::leer(['anchos' => [480], 'formatos' => 'avif'], 2, $avisos)->formatos);
        self::assertCount(1, $avisos->todos());
    }

    #[Test]
    public function unaSeccionQueNoEsUnMapaSeIgnora(): void
    {
        $imagenes = Imagenes::leer([480, 960], 7, $this->avisos);

        self::assertFalse($imagenes->activa());
        self::assertSame(
            ['sitio.yml:7: «imagenes» tiene que ser un mapa con «anchos», «formatos» y «sizes»; se ignora'],
            $this->avisos(),
        );
    }

    #[Test]
    public function elLectorLaTomaDeSitioYml(): void
    {
        $this->crearFichero(
            'sitio.yml',
            "nombre: Prueba\nurl: https://ejemplo.com\nimagenes:\n  anchos: [480, 960]\n  formatos: [avif, webp]\n  sizes: \"(min-width: 60rem) 50rem, 100vw\"\n",
        );

        $sitio = (new Lector())->leerSitio(Proyecto::abrir($this->carpetaTemporal()));

        self::assertSame([480, 960], $sitio->imagenes->anchos);
        self::assertSame(['avif', 'webp'], $sitio->imagenes->formatos);
        self::assertSame('(min-width: 60rem) 50rem, 100vw', $sitio->imagenes->sizes);
    }

    #[Test]
    public function sinLaSeccionElLectorDejaElSitioSinVariantes(): void
    {
        $this->crearSitioMinimo();

        self::assertFalse((new Lector())->leerSitio(Proyecto::abrir($this->carpetaTemporal()))->imagenes->activa());
    }

    /**
     * @return list<string>
     */
    private function avisos(): array
    {
        return array_map(strval(...), $this->avisos->todos());
    }
}
