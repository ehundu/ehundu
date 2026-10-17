<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\CompiladorIncrementalEnProceso;
use Ehundu\CompiladorPorLotesEnProceso;
use Ehundu\Despliegue\Desplegador;
use Ehundu\Presupuesto;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use Ehundu\Pruebas\Apoyo\DestinoEnMemoria;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * El presupuesto de tiempo de `avanzar` y del despliegue: un número enorme
 * quiere decir «sin límite», no «una sola tarea».
 */
final class PresupuestoPrueba extends TestCase
{
    use CarpetaTemporal;

    private const string AHORA = '2026-01-01 12:00:00';
    private const int SEGUNDO = 1_000_000_000;

    /**
     * @return iterable<string, array{float, int}> los segundos y cuántos nanosegundos después del comienzo acaba
     */
    public static function presupuestos(): iterable
    {
        yield 'unos segundos' => [5.0, 5 * self::SEGUNDO];
        yield 'medio segundo' => [0.5, 500_000_000];
        yield 'cero' => [0.0, 0];
        yield 'negativo' => [-3.0, 0];
        yield 'NAN' => [NAN, 0];
        yield 'justo un día' => [86400.0, 86400 * self::SEGUNDO];
        yield 'más de un día' => [86401.0, 86400 * self::SEGUNDO];
        yield 'diez mil millones de segundos' => [1e10, 86400 * self::SEGUNDO];
        yield 'INF' => [INF, 86400 * self::SEGUNDO];
        yield 'PHP_INT_MAX' => [(float) PHP_INT_MAX, 86400 * self::SEGUNDO];
        yield 'menos infinito' => [-INF, 0];
    }

    #[Test]
    #[DataProvider('presupuestos')]
    public function elLimiteEsElComienzoMasLoQueDura(float $segundos, int $nanosegundos): void
    {
        self::assertSame(1_000 + $nanosegundos, Presupuesto::limite(1_000, $segundos));
    }

    #[Test]
    public function unPresupuestoEnormeNoHaceSaltarAvisosDePhp(): void
    {
        $avisos = [];
        set_error_handler(function (int $nivel, string $mensaje) use (&$avisos): bool {
            $avisos[] = $mensaje;

            return true;
        });

        try {
            Presupuesto::limite(0, INF);
            Presupuesto::limite(0, NAN);
            Presupuesto::limite(0, (float) PHP_INT_MAX);
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $avisos);
    }

    #[Test]
    #[DataProvider('presupuestosSinLimite')]
    public function laIncrementalCompletaEnUnaLlamadaConUnPresupuestoSinLimite(float $segundos): void
    {
        $this->crearSitio();

        $lote = (new CompiladorIncrementalEnProceso(new \DateTimeImmutable(self::AHORA)))
            ->avanzar(Proyecto::abrir($this->carpetaTemporal()), $segundos);

        self::assertTrue($lote->completo(), "Con {$segundos} s tenía que acabar en una llamada: {$lote->hechas} de {$lote->total}");
    }

    #[Test]
    #[DataProvider('presupuestosSinLimite')]
    public function losLotesCompletanEnUnaLlamadaConUnPresupuestoSinLimite(float $segundos): void
    {
        $this->crearSitio();

        $lote = (new CompiladorPorLotesEnProceso(new \DateTimeImmutable(self::AHORA)))
            ->avanzar(Proyecto::abrir($this->carpetaTemporal()), $segundos);

        self::assertTrue($lote->completo(), "Con {$segundos} s tenía que acabar en una llamada: {$lote->hechas} de {$lote->total}");
    }

    #[Test]
    #[DataProvider('presupuestosSinLimite')]
    public function elDespliegueSubeTodoEnUnaLlamadaConUnPresupuestoSinLimite(float $segundos): void
    {
        $this->crearSitio();
        $destino = new DestinoEnMemoria();

        $informe = (new Desplegador(new \Ehundu\CompiladorEnProceso(), fn () => $destino))
            ->desplegar(Proyecto::abrir($this->carpetaTemporal()), false, false, segundos: $segundos);

        self::assertTrue($informe->completo, "Con {$segundos} s tenía que subirlo todo en una llamada");
        self::assertSame(0, $informe->pendientes);
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function presupuestosSinLimite(): iterable
    {
        yield 'un día' => [86400.0];
        yield 'PHP_INT_MAX' => [(float) PHP_INT_MAX];
        yield 'INF' => [INF];
        yield 'diez mil millones de segundos' => [1e10];
    }

    #[Test]
    #[DataProvider('presupuestosSinTiempo')]
    public function unPresupuestoNegativoOQueNoEsUnNumeroHaceUnaTareaPorLlamada(float $segundos): void
    {
        $this->crearSitio();

        $lote = (new CompiladorIncrementalEnProceso(new \DateTimeImmutable(self::AHORA)))
            ->avanzar(Proyecto::abrir($this->carpetaTemporal()), $segundos);

        self::assertFalse($lote->completo());
        self::assertSame(1, $lote->hechas);
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function presupuestosSinTiempo(): iterable
    {
        yield 'cero' => [0.0];
        yield 'negativo' => [-5.0];
        yield 'NAN' => [NAN];
    }

    private function crearSitio(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\ndespliegue:\n  destino: carpeta\n  ruta: /publicado\n");
        $this->crearPlantillaMinima();
        $this->crearFichero('contenido/index.md', "---\ntitulo: Inicio\n---\nHola");
        $this->crearFichero('contenido/otra.md', "---\ntitulo: Otra\n---\nOtra");
        $this->crearFichero('contenido/tercera.md', "---\ntitulo: Tercera\n---\nTercera");
        $this->crearFichero('publico/css/a.css', 'a{}');
    }
}
