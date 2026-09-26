<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Sitio;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SitioPrueba extends TestCase
{
    #[Test]
    public function daLaDireccionCompletaDeUnaUrl(): void
    {
        $sitio = new Sitio('Prueba', 'https://www.ejemplo.com', new \DateTimeZone('UTC'), []);

        self::assertSame('https://www.ejemplo.com/', $sitio->absoluta('/'));
        self::assertSame('https://www.ejemplo.com/blog/uno/', $sitio->absoluta('/blog/uno/'));
        self::assertSame('https://www.ejemplo.com/Qui%C3%A9nes%20somos/', $sitio->absoluta('/Quiénes somos/'));
    }
}
