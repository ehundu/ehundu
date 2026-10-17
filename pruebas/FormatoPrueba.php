<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Avisos;
use Ehundu\Constructor;
use Ehundu\ErrorDeProyecto;
use Ehundu\Formato;
use Ehundu\Lector;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * La versión del formato que declara un proyecto (formato §2 y §12): `formato: 1`
 * en `sitio.yml`.
 */
final class FormatoPrueba extends TestCase
{
    use CarpetaTemporal;

    #[Test]
    public function sinLaClaveElFormatoEsElUnoYNoAvisa(): void
    {
        self::assertSame(1, Formato::leer(null, 'sitio.yml', null));
    }

    #[Test]
    public function elFormatoQueEntiendeEsElUno(): void
    {
        self::assertSame(1, Formato::VERSION);
        self::assertSame(1, Formato::leer(1, 'sitio.yml', 3));
    }

    #[Test]
    public function unValorQueNoEsUnEnteroMayorQueCeroDetieneLaCompilacion(): void
    {
        // Un «2» entre comillas, por ejemplo, de un proyecto del formato 2 escrito con descuido, no puede pasar por un 1
        foreach ([0, -1, 1.0, 1.5, '1', '2', 'uno', true, [1]] as $valor) {
            try {
                Formato::leer($valor, 'sitio.yml', 4);
                self::fail('Tenía que detenerse con ' . var_export($valor, true));
            } catch (ErrorDeProyecto $error) {
                self::assertSame(
                    'sitio.yml:4: «formato» tiene que ser un número entero mayor que cero, la versión del formato (ahora, 1)',
                    $error->getMessage(),
                    var_export($valor, true),
                );
            }
        }
    }

    #[Test]
    public function unFormatoMayorQueElQueEntiendeDetieneLaCompilacionConUnErrorClaro(): void
    {
        try {
            Formato::leer(2, 'sitio.yml', 5);
            self::fail('Se esperaba un ErrorDeProyecto');
        } catch (ErrorDeProyecto $error) {
            self::assertSame('Este proyecto es del formato 2 y esta versión de Ehundu entiende hasta el 1; actualiza Ehundu', $error->descripcion);
            self::assertSame('sitio.yml', $error->fichero);
            self::assertSame(5, $error->linea);
        }
    }

    #[Test]
    public function elLectorLeeElFormatoDeSitioYml(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\nformato: 1\n");

        $sitio = (new Lector())->leerSitio(Proyecto::abrir($this->carpetaTemporal()));

        self::assertSame(1, $sitio->formato);
        self::assertSame(1, $sitio->campos['formato']);
    }

    #[Test]
    public function sinLaClaveElSitioEsDelFormatoUno(): void
    {
        $this->crearSitioMinimo();

        self::assertSame(1, (new Lector())->leerSitio(Proyecto::abrir($this->carpetaTemporal()))->formato);
    }

    #[Test]
    public function unSitioDeUnFormatoMayorNoSeLeeNiSeCompila(): void
    {
        // El error sale antes que cualquier otro: aquí falta hasta la url
        $this->crearFichero('sitio.yml', "nombre: Prueba\nformato: 2\n");
        $this->crearFichero('contenido/.gitkeep', '');
        $proyecto = Proyecto::abrir($this->carpetaTemporal());

        foreach ([
            fn () => (new Lector())->leerSitio($proyecto),
            fn () => (new Lector())->leer($proyecto),
            fn () => (new Constructor())->construir($proyecto),
        ] as $accion) {
            try {
                $accion();
                self::fail('Se esperaba un ErrorDeProyecto');
            } catch (ErrorDeProyecto $error) {
                self::assertStringStartsWith('Este proyecto es del formato 2', $error->descripcion);
                self::assertSame(2, $error->linea);
            }
        }
    }
}
