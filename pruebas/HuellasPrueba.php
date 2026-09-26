<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Huellas;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HuellasPrueba extends TestCase
{
    use CarpetaTemporal;

    #[Test]
    public function daUnaHuellaPorFicheroConSuRutaDesdeLaRaiz(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('publico/css/a.css', 'a{}');
        $this->crearFichero('publico/b.js', 'b');

        $huellas = (new Huellas($this->proyecto()))->tomar(['publico', 'sitio.yml', 'no-existe']);

        self::assertSame(['publico/b.js', 'publico/css/a.css', 'sitio.yml'], array_keys($huellas));
    }

    #[Test]
    public function notaUnCambioDelMismoTamanoEnElMismoSegundo(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('publico/a.css', 'a{color:red}');
        $fichero = $this->carpetaTemporal() . '/publico/a.css';
        $fecha = (int) filemtime($fichero);
        $huellas = new Huellas($this->proyecto());

        $antes = $huellas->tomar(['publico']);
        $this->crearFichero('publico/a.css', 'a{color:tan}');
        touch($fichero, $fecha);

        self::assertNotSame($antes, $huellas->tomar(['publico']));
    }

    #[Test]
    public function confiaEnLaFechaDeLoQueLlevaUnRatoSinTocarse(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('publico/a.css', 'a{color:red}');
        $fichero = $this->carpetaTemporal() . '/publico/a.css';
        $hace = time() - 3600;
        touch($fichero, $hace);
        $huellas = new Huellas($this->proyecto());

        $antes = $huellas->tomar(['publico']);

        // Un cambio que deja la fecha como estaba no se nota: así no hay que
        // volver a leer cada vez lo que no se toca.
        $this->crearFichero('publico/a.css', 'a{color:tan}');
        touch($fichero, $hace);
        self::assertSame($antes, $huellas->tomar(['publico']));

        // Otro vigilante, que no lo ha leído antes, sí lo nota.
        self::assertNotSame($antes, (new Huellas($this->proyecto()))->tomar(['publico']));
    }

    #[Test]
    public function losFicherosGrandesSeComparanPorTamanoYFecha(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('publico/video.mp4', str_repeat('x', Huellas::LIMITE));
        $this->crearFichero('publico/foto.jpg', 'x');

        $huellas = (new Huellas($this->proyecto()))->tomar(['publico']);

        self::assertStringEndsWith('|', $huellas['publico/video.mp4']);
        self::assertStringEndsNotWith('|', $huellas['publico/foto.jpg']);
    }

    #[Test]
    public function losOcultosSoloSiSePiden(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('publico/.htaccess', 'Options -Indexes');
        $this->crearFichero('publico/.git/HEAD', 'ref');
        $this->crearFichero('publico/a.css', 'a{}');

        self::assertSame(['publico/a.css'], array_keys((new Huellas($this->proyecto()))->tomar(['publico'])));
        self::assertSame(
            ['publico/.git/HEAD', 'publico/.htaccess', 'publico/a.css'],
            array_keys((new Huellas($this->proyecto(), ocultos: true))->tomar(['publico'])),
        );
    }

    private function proyecto(): Proyecto
    {
        return Proyecto::abrir($this->carpetaTemporal());
    }
}
