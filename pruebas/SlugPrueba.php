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
        yield 'rumano con coma debajo y con cedilla' => ['Brașov, Ceaușescu, Târgu Mureș, Peleș, Carturești; Brasov, Cărţi', 'brasov-ceausescu-targu-mures-peles-carturesti-brasov-carti'];
        yield 'islandés, noruego y turco' => ['Þingvellir, Kerið, Bygdøy, Kadıköy', 'thingvellir-kerid-bygdoy-kadikoy'];
        yield 'otro alfabeto' => ['Ελληνικά', ''];
        yield 'letras descompuestas' => ["lo ma\u{0301}s querido, cin\u{0303}a, pingu\u{0308}ino", 'lo-mas-querido-cina-pinguino'];
    }

    #[Test]
    #[DataProvider('casos')]
    public function convierteUnTextoEnUnTramoDeUrl(string $texto, string $esperado): void
    {
        self::assertSame($esperado, Slug::de($texto));
    }

    /**
     * La tabla de letras es parte del contrato y no cambia dentro de una versión mayor (formato §5.2, decisión
     * 134): la del documento y la del código tienen que ser la misma, letra a letra.
     */
    #[Test]
    public function laTablaDelContratoEsLaDelCodigo(): void
    {
        $contrato = $this->tablaDelContrato();
        $codigo = $this->tablaDelCodigo();

        self::assertNotSame([], $contrato, 'No encuentro la tabla en el §5.2 de formato.md');
        self::assertSame(array_keys($codigo), array_keys($contrato), 'Las mismas letras de salida, en el mismo orden');

        foreach ($codigo as $sale => $letras) {
            self::assertSame($letras, $contrato[$sale], "Las letras que dan «{$sale}»");
        }
    }

    #[Test]
    public function cadaLetraDeLaTablaDaLoMismoEnMayusculaYEnMinuscula(): void
    {
        foreach ($this->tablaDelCodigo() as $sale => $letras) {
            foreach (mb_str_split($letras) as $letra) {
                if ($letra === 'ŉ') {
                    // Su mayúscula es «ʼN», dos letras: es la única que no
                    continue;
                }

                self::assertSame($sale, Slug::de($letra), "«{$letra}» da «{$sale}»");
                self::assertSame($sale, Slug::de(mb_strtoupper($letra)), 'Su mayúscula ' . mb_strtoupper($letra));
            }
        }
    }

    /**
     * Lo que sale y las letras que lo dan, tal como lo tiene `Slug`.
     *
     * @return array<string, string>
     */
    private function tablaDelCodigo(): array
    {
        $clase = new \ReflectionClass(Slug::class);
        $filas = $clase->getConstant('LETRAS');

        foreach ($clase->getConstant('LIGADURAS') as $letra => $sale) {
            $filas[$sale] = ($filas[$sale] ?? '') . $letra;
        }

        return $filas;
    }

    /**
     * Las filas de la tabla del §5.2 de `formato.md`: lo que sale y las letras que lo dan.
     *
     * @return array<string, string>
     */
    private function tablaDelContrato(): array
    {
        $texto = (string) file_get_contents(dirname(__DIR__) . '/docs/formato.md');
        $inicio = strpos($texto, 'La tabla de letras es esta');

        if ($inicio === false) {
            return [];
        }

        $filas = [];

        foreach (explode("\n", substr($texto, $inicio)) as $linea) {
            if (preg_match('/^    (\S+)\s+(\S+)$/u', $linea, $partes) === 1) {
                $filas[$partes[1]] = $partes[2];
            } elseif ($filas !== []) {
                break;
            }
        }

        return $filas;
    }
}
