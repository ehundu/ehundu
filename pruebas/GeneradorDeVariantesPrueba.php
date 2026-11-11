<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\ErrorDeProyecto;
use Ehundu\GeneradorDeVariantes;
use Ehundu\Imagenes;
use Ehundu\InformeDeVariantes;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use Ehundu\Pruebas\Apoyo\ImagenGd;
use Ehundu\Variantes;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Generar las variantes de ancho de las imágenes (formato §9.2): lo que hace `ehundu imagenes`.
 */
final class GeneradorDeVariantesPrueba extends TestCase
{
    use CarpetaTemporal;

    protected function setUp(): void
    {
        if (!ImagenGd::disponible()) {
            self::markTestSkipped('Hace falta la extensión GD con JPEG, PNG y WebP');
        }

        $this->crearSitioMinimo();
    }

    #[Test]
    public function generaUnFicheroPorCadaAnchoYCadaFormatoSinAmpliarNunca(): void
    {
        $this->crearFichero('publico/img/foto.jpg', ImagenGd::jpeg(1000, 500));

        $informe = $this->generar([480, 960, 1600], ['webp', 'jpg']);

        self::assertSame(1, $informe->imagenes);
        self::assertSame(
            ['img/foto-480.webp', 'img/foto-960.webp', 'img/foto-1600.webp', 'img/foto-480.jpg', 'img/foto-960.jpg', 'img/foto-1600.jpg'],
            $informe->generadas,
        );
        self::assertSame(0, $informe->existentes);
        self::assertSame([], $informe->avisos);

        // El nombre lleva el ancho pedido; el fichero, el del original si es más estrecho (§2)
        self::assertSame([480, 240], $this->medidas('img/foto-480.webp'));
        self::assertSame([960, 480], $this->medidas('img/foto-960.jpg'));
        self::assertSame([1000, 500], $this->medidas('img/foto-1600.webp'));
        self::assertSame([1000, 500], $this->medidas('img/foto-1600.jpg'));
        self::assertFileDoesNotExist($this->carpetaTemporal() . '/publico/img/foto-480.webp.parte');
    }

    #[Test]
    public function loQueGeneraLoEncuentraVariantesSinAvisar(): void
    {
        $this->crearFichero('publico/img/foto.jpg', ImagenGd::jpeg(2000, 1000));
        $imagenes = new Imagenes([480, 960, 1600], ['webp', 'jpg']);

        $this->generar([480, 960, 1600], ['webp', 'jpg']);

        $avisos = [];
        $variantes = (new Variantes(Proyecto::abrir($this->carpetaTemporal()), $imagenes, static fn (string $ruta) => null))
            ->buscar('img/foto', static function (string $aviso) use (&$avisos): void {
                $avisos[] = $aviso;
            });

        self::assertNotNull($variantes);
        self::assertSame([], $avisos);
        self::assertSame('/img/foto-480.jpg 480w, /img/foto-960.jpg 960w, /img/foto-1600.jpg 1600w', $variantes['srcset']);
        self::assertSame('/img/foto-480.webp 480w, /img/foto-960.webp 960w, /img/foto-1600.webp 1600w', $variantes['fuentes'][0]['srcset']);
    }

    #[Test]
    public function noRehaceLoQueYaExisteSalvoConForzar(): void
    {
        $this->crearFichero('publico/img/foto.jpg', ImagenGd::jpeg(1000, 500));
        $this->crearFichero('publico/img/foto-480.webp', 'a medias');

        $informe = $this->generar([480, 960], ['webp']);

        self::assertSame(['img/foto-960.webp'], $informe->generadas);
        self::assertSame(1, $informe->existentes);
        self::assertSame('a medias', $this->leerFichero('publico/img/foto-480.webp'));

        $informe = $this->generar([480, 960], ['webp'], forzar: true);

        self::assertSame(['img/foto-480.webp', 'img/foto-960.webp'], $informe->generadas);
        self::assertSame(0, $informe->existentes);
        self::assertSame([480, 240], $this->medidas('img/foto-480.webp'));
    }

    #[Test]
    public function repetirLaOrdenNoHaceVariantesDeLasVariantes(): void
    {
        $this->crearFichero('publico/img/foto.jpg', ImagenGd::jpeg(1000, 500));

        $this->generar([480, 960], ['webp', 'jpg']);
        $segunda = $this->generar([480, 960], ['webp', 'jpg']);

        self::assertSame(1, $segunda->imagenes);
        self::assertSame([], $segunda->generadas);
        self::assertSame(4, $segunda->existentes);
        self::assertFileDoesNotExist($this->carpetaTemporal() . '/publico/img/foto-480-480.webp');
        self::assertFileDoesNotExist($this->carpetaTemporal() . '/publico/img/foto-480-480.jpg');
    }

    #[Test]
    public function unNombreQueAcabaEnUnAnchoQueElSitioNoDeclaraSiEsUnaImagen(): void
    {
        $this->crearFichero('publico/img/banner-300.jpg', ImagenGd::jpeg(1000, 500));
        // jpg no es uno de los formatos: el nombre acaba en un ancho declarado, pero no es una variante
        $this->crearFichero('publico/img/otra-480.jpg', ImagenGd::jpeg(1000, 500));

        $informe = $this->generar([480], ['webp']);

        self::assertSame(2, $informe->imagenes);
        self::assertSame(['img/banner-300-480.webp', 'img/otra-480-480.webp'], $informe->generadas);
    }

    #[Test]
    public function giraLaImagenSegunLaOrientacionDeLaCamara(): void
    {
        // Guardada de 1200 × 800 con la orientación 6: se ve de 800 × 1200
        $this->crearFichero('publico/img/vertical.jpg', ImagenGd::jpeg(1200, 800, 6));

        $this->generar([480, 960], ['webp']);

        self::assertSame([480, 720], $this->medidas('img/vertical-480.webp'));
        // Sin ampliar: el ancho visto es 800
        self::assertSame([800, 1200], $this->medidas('img/vertical-960.webp'));
    }

    #[Test]
    public function laTransparenciaSeConservaEnWebpYPngYEnJpegSaleSobreBlanco(): void
    {
        $this->crearFichero('publico/img/logo.png', ImagenGd::png(800, 400, transparente: true));

        $this->generar([480], ['webp', 'jpg', 'png']);

        $webp = imagecreatefromwebp($this->carpetaTemporal() . '/publico/img/logo-480.webp');
        self::assertSame(127, (imagecolorat($webp, 470, 100) >> 24) & 0x7F, 'la mitad derecha sigue transparente en WebP');
        self::assertSame(0, (imagecolorat($webp, 10, 100) >> 24) & 0x7F, 'la izquierda sigue opaca');

        $jpg = imagecreatefromjpeg($this->carpetaTemporal() . '/publico/img/logo-480.jpg');
        $pixel = imagecolorat($jpg, 470, 100);
        self::assertSame([255, 255, 255], [($pixel >> 16) & 255, ($pixel >> 8) & 255, $pixel & 255], 'lo transparente sale blanco en JPEG');

        // El PNG transparente lleva canal alfa (tipo de color 6)
        self::assertSame(6, ord($this->leerFichero('publico/img/logo-480.png')[25]));
    }

    #[Test]
    public function unPngOpacoSaleSinCanalAlfa(): void
    {
        $this->crearFichero('publico/img/plano.png', ImagenGd::png(800, 400));

        $this->generar([480], ['png']);

        // Tipo de color 2: RGB, sin alfa
        self::assertSame(2, ord($this->leerFichero('publico/img/plano-480.png')[25]));
        self::assertSame([480, 240], $this->medidas('img/plano-480.png'));
    }

    #[Test]
    public function tambienGeneraAvifSiGdLoSabeEscribir(): void
    {
        if (!ImagenGd::sabeAvif()) {
            self::markTestSkipped('Esta instalación de GD no escribe AVIF');
        }

        $this->crearFichero('publico/img/foto.jpg', ImagenGd::jpeg(1000, 500));

        $informe = $this->generar([480], ['avif', 'webp']);

        self::assertSame(['img/foto-480.avif', 'img/foto-480.webp'], $informe->generadas);
        self::assertSame([480, 240], $this->medidas('img/foto-480.avif'));
    }

    #[Test]
    public function unaImagenQueNoSePuedeLeerSeAvisaYSigueConLasDemas(): void
    {
        $this->crearFichero('publico/img/malo.jpg', 'esto no es una imagen');
        $this->crearFichero('publico/img/bueno.jpg', ImagenGd::jpeg(1000, 500));

        $informe = $this->generar([480], ['webp']);

        self::assertSame(['img/bueno-480.webp'], $informe->generadas);
        self::assertCount(1, $informe->avisos);
        self::assertStringStartsWith('publico/img/malo.jpg: ', (string) $informe->avisos[0]);
    }

    #[Test]
    public function noTocaLoQueNoEsJpegPngNiWebpNiLoQueSeIgnora(): void
    {
        $this->crearFichero('publico/img/animada.gif', 'GIF89a');
        $this->crearFichero('publico/img/dibujo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        $this->crearFichero('publico/img/Thumbs.db', 'x');
        $this->crearFichero('publico/.git/objeto.jpg', ImagenGd::jpeg(1000, 500));
        $this->crearFichero('publico/css/estilos.css', 'body {}');

        $informe = $this->generar([480], ['webp']);

        self::assertSame(0, $informe->imagenes);
        self::assertSame([], $informe->generadas);
        self::assertSame([], $informe->avisos);
    }

    #[Test]
    public function dosImagenesConElMismoNombreSinExtensionAvisanYSeQuedaLaPrimera(): void
    {
        $this->crearFichero('publico/img/foto.jpg', ImagenGd::jpeg(1000, 500));
        $this->crearFichero('publico/img/foto.png', ImagenGd::png(600, 300));

        $informe = $this->generar([480], ['webp']);

        self::assertSame(1, $informe->imagenes);
        self::assertCount(1, $informe->avisos);
        self::assertStringContainsString('publico/img/foto.png', (string) $informe->avisos[0]);
        self::assertSame([480, 240], $this->medidas('img/foto-480.webp'));
    }

    #[Test]
    public function recorreTodasLasCarpetasDePublico(): void
    {
        $this->crearFichero('publico/img/a.jpg', ImagenGd::jpeg(1000, 500));
        $this->crearFichero('publico/galeria/2026/b.png', ImagenGd::png(1000, 500));

        $informe = $this->generar([480], ['webp']);

        self::assertSame(['galeria/2026/b-480.webp', 'img/a-480.webp'], $informe->generadas);
    }

    #[Test]
    public function conOriginalesLeeDeSuCarpetaYEscribeEnPublicoSinCopiarElOriginal(): void
    {
        $this->crearFichero('originales/img/foto.png', ImagenGd::png(1000, 500));
        // Allí todo es original, aunque el nombre parezca una variante
        $this->crearFichero('originales/img/otra-480.png', ImagenGd::png(1000, 500));

        $informe = $this->generar([480], ['webp'], originales: 'originales');

        self::assertSame(2, $informe->imagenes);
        self::assertSame(['img/foto-480.webp', 'img/otra-480-480.webp'], $informe->generadas);
        self::assertFileDoesNotExist($this->carpetaTemporal() . '/publico/img/foto.png');
    }

    #[Test]
    public function losOriginalesTienenQueExistirYNoPuedenEstarEnPublicoNiEnSalida(): void
    {
        $this->crearFichero('publico/img/a.jpg', ImagenGd::jpeg(1000, 500));
        $this->crearFichero('salida/img/b.jpg', ImagenGd::jpeg(1000, 500));

        foreach (['no-existe', 'publico', 'publico/img', 'salida', '../fuera'] as $carpeta) {
            try {
                $this->generar([480], ['webp'], originales: $carpeta);
                self::fail("«{$carpeta}» debería rechazarse");
            } catch (ErrorDeProyecto $error) {
                self::assertNotSame('', $error->getMessage(), $carpeta);
            }
        }

        self::assertFileDoesNotExist($this->carpetaTemporal() . '/publico/img/a-480.webp');
    }

    #[Test]
    public function sinLaSeccionImagenesSeDetieneConUnErrorYNoEscribeNada(): void
    {
        $this->crearFichero('publico/img/foto.jpg', ImagenGd::jpeg(1000, 500));

        try {
            (new GeneradorDeVariantes(Proyecto::abrir($this->carpetaTemporal()), new Imagenes()))->generar();
            self::fail('Sin imagenes tiene que dar un error');
        } catch (ErrorDeProyecto $error) {
            self::assertStringContainsString('«imagenes»', $error->getMessage());
            self::assertSame('sitio.yml', $error->fichero);
        }

        self::assertSame(['foto.jpg'], array_map('basename', glob($this->carpetaTemporal() . '/publico/img/*') ?: []));
    }

    #[Test]
    public function abrirLeeLaSeccionImagenesDeSitioYml(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\nimagenes:\n  anchos: [320, 640]\n  formatos: [webp]\n");
        $this->crearFichero('publico/img/foto.jpg', ImagenGd::jpeg(1000, 500));

        $informe = GeneradorDeVariantes::abrir(Proyecto::abrir($this->carpetaTemporal()))->generar();

        self::assertSame(['img/foto-320.webp', 'img/foto-640.webp'], $informe->generadas);
    }

    #[Test]
    public function abrirDaLosAvisosDeSitioYmlJuntoConLosDeLaGeneracion(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\nimagenes:\n  anchos: [320]\n  formatos: [webp, bmp]\n");
        $this->crearFichero('publico/img/foto.jpg', ImagenGd::jpeg(1000, 500));

        $informe = GeneradorDeVariantes::abrir(Proyecto::abrir($this->carpetaTemporal()))->generar();

        self::assertCount(1, $informe->avisos);
        self::assertStringContainsString('bmp', (string) $informe->avisos[0]);
    }

    #[Test]
    public function elResumenCuentaLoHecho(): void
    {
        $informe = new InformeDeVariantes(2, ['a', 'b', 'c'], 2, [], 0.5);
        self::assertSame('Variantes: 2 imágenes, 3 ficheros generados (2 ya existían) en 0,50 s.', $informe->resumen());

        $informe = new InformeDeVariantes(1, ['a'], 1, [], 1.234);
        self::assertSame('Variantes: 1 imagen, 1 fichero generado (1 ya existía) en 1,23 s.', $informe->resumen());

        $informe = new InformeDeVariantes(0, [], 0, [new \Ehundu\Aviso('x')], 0.0);
        self::assertSame('Variantes: 0 imágenes, 0 ficheros generados en 0,00 s, 1 aviso.', $informe->resumen());
    }

    #[Test]
    public function avisaDeCadaFicheroQueEscribeSiSePide(): void
    {
        $this->crearFichero('publico/img/foto.jpg', ImagenGd::jpeg(1000, 500));
        $escritos = [];

        (new GeneradorDeVariantes(Proyecto::abrir($this->carpetaTemporal()), new Imagenes([480, 960], ['webp'])))
            ->generar(alGenerar: static function (string $ruta) use (&$escritos): void {
                $escritos[] = $ruta;
            });

        self::assertSame(['img/foto-480.webp', 'img/foto-960.webp'], $escritos);
    }

    /**
     * @param list<int>    $anchos
     * @param list<string> $formatos
     */
    private function generar(array $anchos, array $formatos, bool $forzar = false, ?string $originales = null): InformeDeVariantes
    {
        return (new GeneradorDeVariantes(Proyecto::abrir($this->carpetaTemporal()), new Imagenes($anchos, $formatos)))
            ->generar($forzar, $originales);
    }

    /**
     * @return array{int, int} el ancho y el alto de un fichero de `publico/`
     */
    private function medidas(string $ruta): array
    {
        $medidas = getimagesize($this->carpetaTemporal() . "/publico/{$ruta}");
        self::assertIsArray($medidas, "publico/{$ruta} no es una imagen");

        return [$medidas[0], $medidas[1]];
    }
}
