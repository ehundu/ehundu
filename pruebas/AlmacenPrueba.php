<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Incremental\Almacen;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Las cuentas de memoria del almacén de la compilación incremental (formato §10.5): cuánto es un `memory_limit` y
 * si lo que se guardó cabe en él. Guardar y cargar se prueban en `IncrementalPersistidaPrueba`.
 */
final class AlmacenPrueba extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: int|null}>
     */
    public static function limites(): iterable
    {
        yield '128M' => ['128M', 134_217_728];
        yield 'en minúscula' => ['512m', 536_870_912];
        yield 'gigas' => ['1G', 1_073_741_824];
        yield 'kilos' => ['1024K', 1_048_576];
        yield 'bytes' => ['268435456', 268_435_456];
        yield 'con espacios' => [' 256M ', 268_435_456];
        yield 'con la B' => ['64MB', 67_108_864];
        yield 'sin límite' => ['-1', null];
        yield 'cero' => ['0', null];
        yield 'vacío' => ['', null];
        yield 'que no se entiende' => ['mucha', null];
        yield 'con decimales' => ['1.5G', null];
    }

    #[Test]
    #[DataProvider('limites')]
    public function entiendeLosValoresDeMemoryLimit(string $valor, ?int $bytes): void
    {
        self::assertSame($bytes, Almacen::bytesDe($valor));
    }

    #[Test]
    public function pideTresVecesLoQueMideElFicheroMasDieciseisMegas(): void
    {
        self::assertSame(30_000_000 + 16_777_216, Almacen::necesaria(10_000_000));
        self::assertSame(16_777_216, Almacen::necesaria(0));
    }

    #[Test]
    public function cabeSiLoQueQuedaDeLimiteDaParaLoQuePide(): void
    {
        $mega = 1_048_576;

        // 10 MB piden 30 más 16, y con 30 ya en uso hacen falta 76
        self::assertTrue(Almacen::cabe(10 * $mega, 100 * $mega, 30 * $mega));
        self::assertTrue(Almacen::cabe(10 * $mega, 76 * $mega, 30 * $mega), 'Justo');
        self::assertFalse(Almacen::cabe(10 * $mega, 75 * $mega, 30 * $mega));
        self::assertTrue(Almacen::cabe(1000 * $mega, null, 30 * $mega), 'Sin límite, siempre');
    }

    #[Test]
    public function elMensajeDiceCuantoOcupaCuantoPideCuantoHayYAQueSubirlo(): void
    {
        $mega = 1_048_576;

        $mensaje = Almacen::noCabe(97 * $mega, 128 * $mega);

        self::assertStringContainsString('ocupa 97 MB', $mensaje);
        self::assertStringContainsString('unos 307 MB', $mensaje);
        self::assertStringContainsString('el memory_limit de PHP es de 128 MB', $mensaje);
        self::assertStringContainsString('Sube el memory_limit a 320 MB o más', $mensaje, 'A un múltiplo de 64 que dé para lo que pide');
    }

    #[Test]
    public function elLimiteDeUnaMemoriaQueNoMideNadaSeDiceConUnMegaAlMenos(): void
    {
        self::assertStringContainsString('ocupa 1 MB', Almacen::noCabe(10, 64 * 1_048_576));
    }
}
