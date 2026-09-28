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

    /**
     * @return iterable<string, array{string, string|null, string}>
     */
    public static function formatosEnOtrosIdiomas(): iterable
    {
        yield 'castellano, por defecto' => ['es', null, '18 de marzo de 2025'];
        yield 'euskera, por defecto' => ['eu', null, '2025eko martxoaren 18a'];
        yield 'euskera, con letras' => ['eu', 'l j F Y', 'asteartea 18 martxoa 2025'];
        yield 'euskera, abreviaturas' => ['eu', 'D, j M', 'ar, 18 mar'];
        yield 'euskera, el mes en genitivo' => ['eu', 'F\r\e\n j\a', 'martxoaren 18a'];
        yield 'inglés, por defecto' => ['en', null, '18 March 2025'];
        yield 'inglés, con letras' => ['en', 'l j F Y', 'Tuesday 18 March 2025'];
        yield 'alemán, por defecto' => ['de', null, '18. März 2025'];
        yield 'alemán, con letras' => ['de', 'l, j. F Y', 'Dienstag, 18. März 2025'];
        yield 'alemán, abreviaturas' => ['de', 'D, j. M', 'Di, 18. Mär'];
        yield 'un idioma que no trae el motor' => ['fr', null, '18 March 2025'];
    }

    #[Test]
    #[DataProvider('formatosEnOtrosIdiomas')]
    public function escribeLaFechaEnElIdiomaDeLaPagina(string $idioma, ?string $formato, string $esperado): void
    {
        self::assertSame($esperado, Fecha::formatear($this->dia('2025-03-18'), $formato, $this->madrid(), $idioma));
    }

    /**
     * El sufijo del año depende de cómo se lee su final en euskera.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function anosEnEuskera(): iterable
    {
        yield 'bost' => ['2025-01-05', '2025eko urtarrilaren 5a'];
        yield 'sei' => ['2026-09-27', '2026ko irailaren 27a'];
        yield 'bat' => ['2021-02-01', '2021eko otsailaren 1a'];
        yield 'hamar' => ['2010-12-31', '2010eko abenduaren 31a'];
        yield 'hamaika' => ['2011-04-10', '2011ko apirilaren 10a'];
        yield 'hamabost' => ['2015-05-15', '2015eko maiatzaren 15a'];
        yield 'hogei' => ['2020-06-20', '2020ko ekainaren 20a'];
        yield 'hogeita bat' => ['2041-07-02', '2041eko uztailaren 2a'];
        yield 'hogeita hamar' => ['2030-08-30', '2030eko abuztuaren 30a'];
        yield 'hogeita hamaika' => ['2031-10-11', '2031ko urriaren 11a'];
        yield 'mila' => ['2000-11-03', '2000ko azaroaren 3a'];
        yield 'ehun' => ['1900-03-18', '1900eko martxoaren 18a'];
    }

    #[Test]
    #[DataProvider('anosEnEuskera')]
    public function elAnoEnEuskeraLlevaSuSufijo(string $dia, string $esperado): void
    {
        self::assertSame($esperado, Fecha::formatear($this->dia($dia), null, $this->madrid(), 'eu'));
    }

    #[Test]
    public function sabeQueIdiomasTrae(): void
    {
        self::assertSame([true, true, true, true, false], array_map(Fecha::conoce(...), ['es', 'eu', 'en', 'de', 'fr']));
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
