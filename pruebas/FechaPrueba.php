<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\ErrorDeProyecto;
use Ehundu\Fecha;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FechaPrueba extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function formatos(): iterable
    {
        yield 'por defecto' => [Fecha::FORMATO, '18 de marzo de 2025'];
        yield 'día con cero, mes y año' => ['d F Y', '18 marzo 2025'];
        yield 'día de la semana' => ['l j \d\e F', 'martes 18 de marzo'];
        yield 'abreviaturas' => ['D, d M Y', 'mar, 18 mar 2025'];
        yield 'números' => ['Y-m-d', '2025-03-18'];
        yield 'texto con eñe' => ['\A\ñ\o Y', 'Año 2025'];
    }

    #[Test]
    #[DataProvider('formatos')]
    public function escribeLaFechaEnEspanol(string $formato, string $esperado): void
    {
        self::assertSame($esperado, Fecha::formatear($this->dia('2025-03-18'), $formato, $this->madrid()));
    }

    #[Test]
    public function escribeLaFechaEnLaZonaDelSitio(): void
    {
        $medianocheEnMadrid = $this->dia('2025-03-18');

        self::assertSame('18/03/2025 00:00', Fecha::formatear($medianocheEnMadrid, 'd/m/Y H:i', $this->madrid()));
        self::assertSame('17/03/2025 19:00', Fecha::formatear($medianocheEnMadrid, 'd/m/Y H:i', new \DateTimeZone('America/New_York')));
    }

    #[Test]
    public function aceptaTextosYNada(): void
    {
        self::assertSame('1 de enero de 2026', Fecha::formatear('2026-01-01', Fecha::FORMATO, $this->madrid()));
        self::assertSame('', Fecha::formatear(null, Fecha::FORMATO, $this->madrid()));
    }

    #[Test]
    public function rechazaLoQueNoEsUnaFecha(): void
    {
        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('fecha: «pasado mañana por la tarde» no es una fecha');

        Fecha::formatear('pasado mañana por la tarde', Fecha::FORMATO, $this->madrid());
    }

    private function dia(string $dia): \DateTimeImmutable
    {
        return new \DateTimeImmutable("{$dia} 00:00:00", $this->madrid());
    }

    private function madrid(): \DateTimeZone
    {
        return new \DateTimeZone('Europe/Madrid');
    }
}
