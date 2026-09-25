<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Avisos;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AvisosPrueba extends TestCase
{
    #[Test]
    public function unAvisoRepetidoSaleUnaSolaVez(): void
    {
        $avisos = new Avisos();
        $avisos->registrar('No existe publico/svg/a.svg');
        $avisos->registrar('Otro aviso', 'contenido/a.md', 3);
        $avisos->registrar('No existe publico/svg/a.svg');
        $avisos->registrar('Otro aviso', 'contenido/b.md', 3);

        self::assertSame(
            ['No existe publico/svg/a.svg', 'contenido/a.md:3: Otro aviso', 'contenido/b.md:3: Otro aviso'],
            array_map(strval(...), $avisos->todos()),
        );
    }
}
