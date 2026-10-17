<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Aviso;
use Ehundu\Informe;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InformePrueba extends TestCase
{
    #[Test]
    public function resumeLoCompilado(): void
    {
        self::assertSame('Compilado: 57 páginas y 154 ficheros en 0,80 s, 2 avisos.', (new Informe(57, [new Aviso('a'), new Aviso('b')], 0.8, 154))->resumen());
        self::assertSame('Compilado: 1 página y 1 fichero en 0,05 s, 1 aviso.', (new Informe(1, [new Aviso('a')], 0.05, 1))->resumen());
    }

    #[Test]
    public function lasPaginasRehechasSonTodasSiNoSeDiceOtraCosa(): void
    {
        self::assertSame(57, (new Informe(57, [], 0.8))->rehechas, 'Una compilación completa rehace todas');
        self::assertSame(0, (new Informe(0, [], 0.8))->rehechas);
        self::assertSame(3, (new Informe(57, [], 0.8, rehechas: 3))->rehechas);
        self::assertSame(0, (new Informe(57, [], 0.8, rehechas: 0))->rehechas, 'Cero es cero: no se confunde con que falte');
    }

    #[Test]
    public function diceCuantasPaginasSeHanRehechoSiNoHanSidoTodas(): void
    {
        self::assertSame('Compilado: 57 páginas (3 rehechas) y 154 ficheros en 0,04 s.', (new Informe(57, [], 0.04, 154, 3))->resumen());
        self::assertSame('Compilado: 57 páginas (1 rehecha) en 0,01 s.', (new Informe(57, [], 0.01, rehechas: 1))->resumen());
        self::assertSame('Compilado: 57 páginas en 0,30 s.', (new Informe(57, [], 0.3, rehechas: 57))->resumen());
    }
}
