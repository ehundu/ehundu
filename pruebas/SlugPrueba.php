<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Slug;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SlugPrueba extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function casos(): iterable
    {
        yield 'palabras' => ['Hola mundo', 'hola-mundo'];
        yield 'tildes' => ['Cómo elegir un buen libro: guía', 'como-elegir-un-buen-libro-guia'];
        yield 'eñe' => ['Libros para niños y niñas', 'libros-para-ninos-y-ninas'];
        yield 'mayúsculas con tilde' => ['ÁRBOL Ñandú', 'arbol-nandu'];
        yield 'diéresis' => ['Colegio bilingüe', 'colegio-bilingue'];
        yield 'y comercial' => ['Pan & chocolate', 'pan-y-chocolate'];
        yield 'punto volado' => ['Col·legi', 'collegi'];
        yield 'signos de interrogación' => ['¿Qué es? ¡Ya!', 'que-es-ya'];
        yield 'apóstrofo' => ["L'Hospitalet", 'l-hospitalet'];
        yield 'ordinal' => ['El 2º piso', 'el-2-piso'];
        yield 'símbolo' => ['Precio: 10€', 'precio-10'];
        yield 'guiones en los extremos' => ['  --Espacios--  ', 'espacios'];
        yield 'ligaduras' => ['Straße, Façade, œuvre', 'strasse-facade-oeuvre'];
        yield 'otro alfabeto' => ['Ελληνικά', ''];
    }

    #[Test]
    #[DataProvider('casos')]
    public function convierteUnTextoEnUnTramoDeUrl(string $texto, string $esperado): void
    {
        self::assertSame($esperado, Slug::de($texto));
    }
}
