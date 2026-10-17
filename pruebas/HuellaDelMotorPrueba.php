<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\CompiladorIncrementalEnProceso;
use Ehundu\Incremental\Almacen;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Cuándo una memoria guardada por la incremental deja de valer (formato §10.5): la huella del motor, que tiene que
 * cambiar con todo lo que puede cambiar el HTML, y las huellas de los ficheros del proyecto, que se aprovechan solo
 * si el fichero llevaba un rato sin tocarse cuando se tomaron.
 */
final class HuellaDelMotorPrueba extends TestCase
{
    use CarpetaTemporal;

    private const string AHORA = '2026-01-01 12:00:00';

    /** @var array<string, string> */
    private const array CODIGO = ['/src/A.php' => 'aaa', '/src/B.php' => 'bbb', '/recursos/c.twig' => 'ccc'];

    /** @var array<string, string> */
    private const array PAQUETES = ['twig/twig' => 'v3.0.0@abc', 'league/commonmark' => '2.10.0@def', 'symfony/yaml' => 'v8.1.9@ghi'];

    #[Test]
    public function laHuellaCambiaConElContenidoDeUnFicheroDelCodigo(): void
    {
        $base = Almacen::componerHuella(self::CODIGO, self::PAQUETES, '8.4');

        self::assertSame($base, Almacen::componerHuella(self::CODIGO, self::PAQUETES, '8.4'));
        self::assertNotSame($base, Almacen::componerHuella(['/src/A.php' => 'aab'] + self::CODIGO, self::PAQUETES, '8.4'));
        self::assertNotSame($base, Almacen::componerHuella(self::CODIGO + ['/src/D.php' => 'ddd'], self::PAQUETES, '8.4'));
        self::assertNotSame($base, Almacen::componerHuella(array_slice(self::CODIGO, 1), self::PAQUETES, '8.4'));
    }

    #[Test]
    public function laHuellaCambiaConLaVersionDeUnaDependencia(): void
    {
        $base = Almacen::componerHuella(self::CODIGO, self::PAQUETES, '8.4');

        foreach (array_keys(self::PAQUETES) as $paquete) {
            $paquetes = [$paquete => 'v9.9.9@zzz'] + self::PAQUETES;

            self::assertNotSame($base, Almacen::componerHuella(self::CODIGO, $paquetes, '8.4'), "Cambiar {$paquete}");
        }

        self::assertNotSame($base, Almacen::componerHuella(self::CODIGO, self::PAQUETES + ['dompdf/dompdf' => 'v3.0.0@jkl'], '8.4'), 'Una dependencia nueva');
        self::assertNotSame($base, Almacen::componerHuella(self::CODIGO, array_slice(self::PAQUETES, 1), '8.4'), 'Una dependencia que desaparece');
    }

    #[Test]
    public function laHuellaCambiaConLaVersionMenorDePhp(): void
    {
        self::assertNotSame(
            Almacen::componerHuella(self::CODIGO, self::PAQUETES, '8.4'),
            Almacen::componerHuella(self::CODIGO, self::PAQUETES, '8.5'),
        );
    }

    #[Test]
    public function elOrdenEnQueSeDanLosFicherosYLosPaquetesNoCambiaLaHuella(): void
    {
        self::assertSame(
            Almacen::componerHuella(self::CODIGO, self::PAQUETES, '8.4'),
            Almacen::componerHuella(array_reverse(self::CODIGO, true), array_reverse(self::PAQUETES, true), '8.4'),
        );
    }

    #[Test]
    public function laHuellaDeEsteMotorSeCalculaConLoQueTieneInstalado(): void
    {
        $paquetes = Almacen::paquetesInstalados();

        foreach (['twig/twig', 'league/commonmark', 'symfony/yaml'] as $paquete) {
            self::assertArrayHasKey($paquete, $paquetes, "Tiene que contar {$paquete}");
            self::assertMatchesRegularExpression('/^\S+@\S*$/', $paquetes[$paquete]);
        }

        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', Almacen::huellaDelMotor());
        self::assertSame(Almacen::huellaDelMotor(), Almacen::huellaDelMotor());
    }

    #[Test]
    public function unaCompilacionLargaNoDaPorEstableUnFicheroTocadoJustoAntesDeTomarSusHuellas(): void
    {
        // Las huellas se toman al empezar y la memoria se guarda al terminar, que puede ser medio minuto después.
        // Un fichero tocado en el segundo en que se tomaron no era estable entonces, y no tiene que serlo en la
        // siguiente compilación aunque, comparado con el momento de guardar, parezca llevar un rato quieto.
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\n");
        $this->crearFichero('plantillas/pagina.twig', '{{ pagina.contenido }}-A');
        $this->crearFichero('contenido/index.md', "---\ntitulo: Inicio\n---\nHola");
        $plantilla = $this->carpetaTemporal() . '/plantillas/pagina.twig';
        touch($plantilla, time());
        $proyecto = Proyecto::abrir($this->carpetaTemporal());

        $this->compilador()->compilar($proyecto);
        self::assertStringContainsString('-A', $this->leerFichero('salida/index.html'));
        $this->retrasarElGuardadoDeLaMemoria($proyecto, 30);

        // Dentro de ese mismo segundo, con el mismo tamaño y la misma fecha
        $fecha = (int) filemtime($plantilla);
        file_put_contents($plantilla, '{{ pagina.contenido }}-B');
        touch($plantilla, $fecha);
        clearstatcache();

        $informe = $this->compilador()->compilar(Proyecto::abrir($this->carpetaTemporal()));

        self::assertStringContainsString('-B', $this->leerFichero('salida/index.html'), 'Tenía que notar el cambio');
        self::assertSame(1, $informe->rehechas);
    }

    /**
     * Hace como si la compilación que guardó la memoria hubiera durado `$segundos`: pone en su cabecera un momento
     * de guardado posterior. El resto del fichero, igual.
     */
    private function retrasarElGuardadoDeLaMemoria(Proyecto $proyecto, int $segundos): void
    {
        $fichero = $proyecto->ruta(Almacen::FICHERO);
        $contenido = (string) file_get_contents($fichero);
        $fin = strpos($contenido, "\n");
        self::assertIsInt($fin);
        $partes = explode(' ', substr($contenido, 0, $fin));
        self::assertCount(5, $partes, 'La cabecera de la memoria tiene cinco partes');
        $partes[3] = (string) (time() + $segundos);
        file_put_contents($fichero, implode(' ', $partes) . substr($contenido, $fin));
    }

    private function compilador(): CompiladorIncrementalEnProceso
    {
        return new CompiladorIncrementalEnProceso(new \DateTimeImmutable(self::AHORA));
    }
}
