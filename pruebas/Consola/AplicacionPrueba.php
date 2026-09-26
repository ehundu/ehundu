<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Consola;

use Ehundu\Aviso;
use Ehundu\Compilador;
use Ehundu\CompiladorEnProceso;
use Ehundu\Consola\Aplicacion;
use Ehundu\Informe;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AplicacionPrueba extends TestCase
{
    use CarpetaTemporal;

    /** @var resource */
    private $salida;

    /** @var resource */
    private $errores;

    protected function setUp(): void
    {
        $this->salida = fopen('php://memory', 'w+');
        $this->errores = fopen('php://memory', 'w+');
    }

    #[Test]
    public function sinArgumentosMuestraLaAyudaComoError(): void
    {
        $codigo = $this->aplicacion()->ejecutar([]);

        self::assertSame(Aplicacion::USO_INCORRECTO, $codigo);
        self::assertStringContainsString('ehundu compilar [carpeta]', $this->leer($this->errores));
        self::assertSame('', $this->leer($this->salida));
    }

    #[Test]
    public function muestraLaAyudaCuandoSePide(): void
    {
        $codigo = $this->aplicacion()->ejecutar(['--ayuda']);

        self::assertSame(Aplicacion::EXITO, $codigo);
        self::assertStringContainsString('ehundu compilar [carpeta]', $this->leer($this->salida));
    }

    #[Test]
    public function rechazaUnaOrdenDesconocida(): void
    {
        $codigo = $this->aplicacion()->ejecutar(['construir']);

        self::assertSame(Aplicacion::USO_INCORRECTO, $codigo);
        self::assertStringStartsWith('Orden desconocida: construir', $this->leer($this->errores));
    }

    #[Test]
    public function rechazaUnaOpcionDesconocida(): void
    {
        $codigo = $this->aplicacion()->ejecutar(['compilar', '--rapido']);

        self::assertSame(Aplicacion::USO_INCORRECTO, $codigo);
        self::assertSame("Opción desconocida: --rapido\n", $this->leer($this->errores));
    }

    #[Test]
    public function rechazaMasDeUnaCarpeta(): void
    {
        $codigo = $this->aplicacion()->ejecutar(['compilar', 'uno', 'dos']);

        self::assertSame(Aplicacion::USO_INCORRECTO, $codigo);
        self::assertSame("«compilar» admite una sola carpeta; sobra: dos\n", $this->leer($this->errores));
    }

    #[Test]
    public function servirArrancaLaPrevisualizacionConSusOpciones(): void
    {
        $this->crearSitioMinimo();
        $llamadas = [];
        $aplicacion = new Aplicacion(new CompiladorEnProceso(), $this->salida, $this->errores, function (Proyecto $proyecto, int $puerto, bool $conBorradores, bool $completo) use (&$llamadas): int {
            $llamadas[] = [$proyecto->raiz, $puerto, $conBorradores, $completo];

            return Aplicacion::EXITO;
        });

        self::assertSame(Aplicacion::EXITO, $aplicacion->ejecutar(['servir', $this->carpetaTemporal()]));
        self::assertSame(Aplicacion::EXITO, $aplicacion->ejecutar(['servir', '--puerto=8123', '--borradores', $this->carpetaTemporal()]));
        self::assertSame(Aplicacion::EXITO, $aplicacion->ejecutar(['servir', '--completo', $this->carpetaTemporal()]));
        self::assertSame([
            [$this->carpetaTemporal(), 8000, false, false],
            [$this->carpetaTemporal(), 8123, true, false],
            [$this->carpetaTemporal(), 8000, false, true],
        ], $llamadas);
    }

    #[Test]
    public function servirRechazaUnPuertoQueNoVale(): void
    {
        $codigo = $this->aplicacion()->ejecutar(['servir', '--puerto=70000']);

        self::assertSame(Aplicacion::USO_INCORRECTO, $codigo);
        self::assertSame("El puerto tiene que ser un número entre 1 y 65535: --puerto=70000\n", $this->leer($this->errores));
    }

    #[Test]
    public function lasOpcionesDeServirNoValenParaCompilar(): void
    {
        $codigo = $this->aplicacion()->ejecutar(['compilar', '--borradores']);

        self::assertSame(Aplicacion::USO_INCORRECTO, $codigo);
        self::assertSame("Opción desconocida: --borradores\n", $this->leer($this->errores));
    }

    #[Test]
    public function compilaLaCarpetaIndicada(): void
    {
        $this->crearSitioMinimo();

        $codigo = $this->aplicacion()->ejecutar(['compilar', $this->carpetaTemporal()]);

        self::assertSame(Aplicacion::EXITO, $codigo);
        self::assertMatchesRegularExpression('/^Compilado: 0 páginas en \d+,\d\d s\.\n$/', $this->leer($this->salida));
        self::assertSame('', $this->leer($this->errores));
        self::assertDirectoryExists($this->carpetaTemporal() . '/salida');
    }

    #[Test]
    public function informaDeUnaCarpetaQueNoExiste(): void
    {
        $ruta = $this->carpetaTemporal() . '/no-existe';

        $codigo = $this->aplicacion()->ejecutar(['compilar', $ruta]);

        self::assertSame(Aplicacion::FALLO, $codigo);
        self::assertSame("Error: No existe la carpeta del proyecto: {$ruta}\n", $this->leer($this->errores));
        self::assertSame('', $this->leer($this->salida));
    }

    #[Test]
    public function informaDeUnErrorEnElProyecto(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/index.md', "---\ntitulo: Novedades: otoño\n---\n");

        $codigo = $this->aplicacion()->ejecutar(['compilar', $this->carpetaTemporal()]);

        self::assertSame(Aplicacion::FALLO, $codigo);
        self::assertStringStartsWith('Error: contenido/index.md:2: El YAML no es válido', $this->leer($this->errores));
    }

    #[Test]
    public function escribeLosAvisosYLosCuentaEnElResumen(): void
    {
        $compilador = new class implements Compilador {
            public function compilar(Proyecto $proyecto): Informe
            {
                return new Informe(1, [
                    new Aviso('La fecha no es válida: «31-02-2025»', 'contenido/index.md', 3),
                    new Aviso('Atajo desconocido: galeria', 'contenido/blog/uno.md'),
                ], 0.5);
            }
        };

        $codigo = $this->aplicacion($compilador)->ejecutar(['compilar', $this->carpetaTemporal()]);

        self::assertSame(Aplicacion::EXITO, $codigo);
        self::assertSame(
            "Aviso: contenido/index.md:3: La fecha no es válida: «31-02-2025»\n"
            . "Aviso: contenido/blog/uno.md: Atajo desconocido: galeria\n",
            $this->leer($this->errores),
        );
        self::assertSame("Compilado: 1 página en 0,50 s, 2 avisos.\n", $this->leer($this->salida));
    }

    #[Test]
    public function despliegaYCuentaLoQueSube(): void
    {
        $publicado = $this->carpetaTemporal() . '-publicado';
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\ndespliegue:\n  destino: carpeta\n  ruta: {$publicado}\n");
        $this->crearPlantillaMinima();
        $this->crearFichero('contenido/index.md', "---\ntitulo: Hola\n---\nHola");

        try {
            $simulado = $this->aplicacion()->ejecutar(['desplegar', '--simular', $this->carpetaTemporal()]);
            $simulacion = $this->leer($this->salida);
            ftruncate($this->salida, 0);
            rewind($this->salida);

            $codigo = $this->aplicacion()->ejecutar(['desplegar', $this->carpetaTemporal()]);
            $salida = $this->leer($this->salida);

            self::assertSame(Aplicacion::EXITO, $simulado);
            self::assertMatchesRegularExpression(
                "/^Compilado: 1 página en \\d+,\\d\\d s\\.\n  subiría   index\\.html\n  subiría   sitemap\\.xml\n"
                    . "Simulación en la carpeta .+: se subirían 2 ficheros \\(\\d+ B\\)\\. No se ha tocado nada\\.\n$/u",
                $simulacion,
            );

            self::assertSame(Aplicacion::EXITO, $codigo);
            self::assertMatchesRegularExpression(
                "/^Compilado: 1 página en \\d+,\\d\\d s\\.\n  subido    index\\.html\n  subido    sitemap\\.xml\n"
                    . "Desplegado en la carpeta .+: 2 ficheros \\(\\d+ B\\) subidos en \\d+,\\d\\d s\\.\n$/u",
                $salida,
            );
            self::assertFileExists("{$publicado}/index.html");
        } finally {
            foreach (['index.html', 'sitemap.xml', '.ehundu.json'] as $fichero) {
                @unlink("{$publicado}/{$fichero}");
            }

            @rmdir($publicado);
        }
    }

    #[Test]
    public function losAvisosDelDespliegueSalenAunqueLuegoFalle(): void
    {
        // Un campo de más avisa; que el destino esté dentro del proyecto detiene el despliegue.
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\ndespliegue:\n  destino: carpeta\n  ruta: dentro\n  cubo: x\n");
        $this->crearPlantillaMinima();
        $this->crearFichero('contenido/index.md', "---\ntitulo: Hola\n---\nHola");

        $codigo = $this->aplicacion()->ejecutar(['desplegar', $this->carpetaTemporal()]);

        self::assertSame(Aplicacion::FALLO, $codigo);
        self::assertStringStartsWith(
            "Aviso: sitio.yml:6: «cubo» no se usa con el destino carpeta; se ignora\n"
                . 'Error: sitio.yml: La carpeta de despliegue no puede estar dentro del proyecto: dentro.',
            $this->leer($this->errores),
        );
    }

    #[Test]
    public function unErrorDeDespliegueSaleComoError(): void
    {
        $this->crearSitioMinimo();

        $codigo = $this->aplicacion()->ejecutar(['desplegar', $this->carpetaTemporal()]);

        self::assertSame(Aplicacion::FALLO, $codigo);
        self::assertSame("Error: sitio.yml: Falta la sección «despliegue», que dice adónde se publica el sitio\n", $this->leer($this->errores));
    }

    #[Test]
    public function lasOpcionesDeDesplegarSoloValenParaDesplegar(): void
    {
        $codigo = $this->aplicacion()->ejecutar(['compilar', '--simular']);

        self::assertSame(Aplicacion::USO_INCORRECTO, $codigo);
        self::assertSame("Opción desconocida: --simular\n", $this->leer($this->errores));
    }

    private function aplicacion(?Compilador $compilador = null): Aplicacion
    {
        return new Aplicacion($compilador ?? new CompiladorEnProceso(), $this->salida, $this->errores);
    }

    /**
     * @param resource $flujo
     */
    private function leer($flujo): string
    {
        rewind($flujo);

        return stream_get_contents($flujo);
    }
}
