<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Aviso;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AvisoPrueba extends TestCase
{
    #[Test]
    public function indicaFicheroYLinea(): void
    {
        $aviso = new Aviso('La fecha no es válida: «31-02-2025»', 'contenido/index.md', 3);

        self::assertSame('contenido/index.md:3: La fecha no es válida: «31-02-2025»', (string) $aviso);
    }

    #[Test]
    public function indicaSoloElFicheroSiNoHayLinea(): void
    {
        $aviso = new Aviso('Atajo desconocido: galeria', 'contenido/index.md');

        self::assertSame('contenido/index.md: Atajo desconocido: galeria', (string) $aviso);
    }

    #[Test]
    public function esSoloElMensajeSiNoHayFichero(): void
    {
        self::assertSame('No hay ninguna página', (string) new Aviso('No hay ninguna página'));
    }
}
