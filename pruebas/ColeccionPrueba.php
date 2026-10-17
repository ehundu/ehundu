<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Coleccion;
use Ehundu\ErrorDeProyecto;
use Ehundu\Pagina;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ColeccionPrueba extends TestCase
{
    #[Test]
    public function sinCriterioOrdenaPorFechaDeLaMasRecienteALaMasAntigua(): void
    {
        $paginas = [
            $this->pagina('a', ['fecha' => $this->fecha('2025-01-01')]),
            $this->pagina('b', ['fecha' => $this->fecha('2025-03-01')]),
            $this->pagina('c', ['fecha' => $this->fecha('2025-02-01')]),
        ];

        self::assertSame(['b', 'c', 'a'], $this->rutas(Coleccion::orden($paginas)));
    }

    #[Test]
    public function losEmpatesConservanElOrdenQueTenian(): void
    {
        $mismaFecha = ['fecha' => $this->fecha('2025-03-15')];
        $paginas = [$this->pagina('a', $mismaFecha), $this->pagina('b', $mismaFecha), $this->pagina('c', $mismaFecha)];

        self::assertSame(['a', 'b', 'c'], $this->rutas(Coleccion::orden($paginas, 'fecha desc')));
        self::assertSame(['c', 'b', 'a'], $this->rutas(Coleccion::invertir($paginas)));
    }

    #[Test]
    public function unCampoSinDireccionEsAscendente(): void
    {
        $paginas = [$this->pagina('a', ['orden' => 3]), $this->pagina('b', ['orden' => 1]), $this->pagina('c', ['orden' => 2])];

        self::assertSame(['b', 'c', 'a'], $this->rutas(Coleccion::orden($paginas, 'orden')));
        self::assertSame(['a', 'c', 'b'], $this->rutas(Coleccion::orden($paginas, 'orden desc')));
    }

    #[Test]
    public function losQueNoTienenElCampoVanAlFinalEnLasDosDirecciones(): void
    {
        $paginas = [$this->pagina('a'), $this->pagina('b', ['orden' => 2]), $this->pagina('c', ['orden' => 1])];

        self::assertSame(['c', 'b', 'a'], $this->rutas(Coleccion::orden($paginas, 'orden asc')));
        self::assertSame(['b', 'c', 'a'], $this->rutas(Coleccion::orden($paginas, 'orden desc')));
    }

    #[Test]
    public function losNumerosSeComparanComoNumeros(): void
    {
        $paginas = [$this->pagina('a', ['orden' => 10]), $this->pagina('b', ['orden' => 9]), $this->pagina('c', ['orden' => 2.5])];

        self::assertSame(['c', 'b', 'a'], $this->rutas(Coleccion::orden($paginas, 'orden')));
    }

    #[Test]
    public function losTextosSeOrdenanComoEnElDiccionario(): void
    {
        $titulos = ['zamora', "A\u{0301}vila", "n\u{0303}andú", 'Nube', 'oso', 'burgos'];
        $paginas = array_map(fn (string $titulo) => $this->pagina($titulo, ['titulo' => $titulo]), $titulos);

        self::assertSame(
            ["A\u{0301}vila", 'burgos', 'Nube', "n\u{0303}andú", 'oso', 'zamora'],
            $this->rutas(Coleccion::orden($paginas, 'titulo')),
        );
    }

    #[Test]
    public function losSignosDeAperturaDeInterrogacionYExclamacionNoCuentanAlOrdenar(): void
    {
        $titulos = ['Zamora', '¿Sigue siendo Andorra el paraíso?', '¡Viva Madrid!', 'Burgos', 'Andorra', '¿Cómo llegar?', 'Cádiz'];
        $paginas = array_map(fn (string $titulo) => $this->pagina($titulo, ['titulo' => $titulo]), $titulos);

        self::assertSame(
            ['Andorra', 'Burgos', 'Cádiz', '¿Cómo llegar?', '¿Sigue siendo Andorra el paraíso?', '¡Viva Madrid!', 'Zamora'],
            $this->rutas(Coleccion::orden($paginas, 'titulo')),
        );
        self::assertSame(
            ['Zamora', '¡Viva Madrid!', '¿Sigue siendo Andorra el paraíso?', '¿Cómo llegar?', 'Cádiz', 'Burgos', 'Andorra'],
            $this->rutas(Coleccion::orden($paginas, 'titulo desc')),
        );
    }

    #[Test]
    public function conOSinSignoDeAperturaDosTextosIgualesEmpatan(): void
    {
        $paginas = [
            $this->pagina('a', ['titulo' => 'Madrid: ¿merece la pena?']),
            $this->pagina('b', ['titulo' => 'Madrid: merece la pena?']),
            $this->pagina('c', ['titulo' => 'Madrid: ¿merece la pena?']),
        ];

        self::assertSame(['a', 'b', 'c'], $this->rutas(Coleccion::orden($paginas, 'titulo')));
        self::assertSame(['a', 'b', 'c'], $this->rutas(Coleccion::orden($paginas, 'titulo desc')));
    }

    #[Test]
    public function ordenaPorVariosCamposYAceptaLosAlias(): void
    {
        $paginas = [
            $this->pagina('a', ['orden' => 2, 'titulo' => 'Beta']),
            $this->pagina('b', ['orden' => 1, 'titulo' => 'Zeta']),
            $this->pagina('c', ['orden' => 2, 'titulo' => 'Alfa']),
        ];

        self::assertSame(['b', 'c', 'a'], $this->rutas(Coleccion::orden($paginas, 'orden, title asc')));
    }

    #[Test]
    public function rechazaUnCriterioQueNoEntiende(): void
    {
        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage("orden('fecha arriba'): cada criterio es un campo seguido, si acaso, de asc o desc");

        Coleccion::orden([], 'fecha arriba');
    }

    #[Test]
    public function limiteSeQuedaConLasPrimeras(): void
    {
        $paginas = $this->paginas('a', 'b', 'c');

        self::assertSame(['a', 'b'], $this->rutas(Coleccion::limite($paginas, 2)));
        self::assertSame(['a', 'b', 'c'], $this->rutas(Coleccion::limite($paginas, 10)));
        self::assertSame([], Coleccion::limite($paginas, 0));
        self::assertSame([], Coleccion::limite($paginas, -1));
    }

    #[Test]
    public function invertirDaLaVueltaALaLista(): void
    {
        self::assertSame(['c', 'b', 'a'], $this->rutas(Coleccion::invertir($this->paginas('a', 'b', 'c'))));
    }

    #[Test]
    public function sinQuitaUnaPaginaPorSuRuta(): void
    {
        $paginas = $this->paginas('a', 'b', 'c');

        self::assertSame(['a', 'c'], $this->rutas(Coleccion::sin($paginas, $paginas[1])));
        self::assertSame(['a', 'b'], $this->rutas(Coleccion::sin($paginas, 'c')));
        self::assertSame(['a', 'b', 'c'], $this->rutas(Coleccion::sin($paginas, 'otra')));
    }

    #[Test]
    public function dondeFiltraPorIgualdad(): void
    {
        $paginas = [
            $this->pagina('a', ['seccion' => 'libros', 'orden' => 1, 'destacado' => true]),
            $this->pagina('b', ['seccion' => 'discos', 'orden' => 2, 'destacado' => false]),
            $this->pagina('c'),
        ];

        self::assertSame(['a'], $this->rutas(Coleccion::donde($paginas, 'seccion', 'libros')));
        self::assertSame(['b'], $this->rutas(Coleccion::donde($paginas, 'orden', '2')));
        self::assertSame(['a'], $this->rutas(Coleccion::donde($paginas, 'destacado', true)));
        self::assertSame([], $this->rutas(Coleccion::donde($paginas, 'destacado', 'sí')));
    }

    #[Test]
    public function dondeConUnaListaBuscaQueLaContenga(): void
    {
        $paginas = [
            $this->pagina('a', ['etiquetas' => ['blog', 'novela']]),
            $this->pagina('b', ['etiquetas' => ['blog', 'poesia']]),
        ];

        self::assertSame(['a'], $this->rutas(Coleccion::donde($paginas, 'etiquetas', 'novela')));
        self::assertSame(['b'], $this->rutas(Coleccion::donde($paginas, 'tags', 'poesia')));
    }

    #[Test]
    public function dondeComparaUnaFechaConSuDia(): void
    {
        $paginas = [$this->pagina('a', ['fecha' => $this->fecha('2025-03-15')]), $this->pagina('b', ['fecha' => $this->fecha('2025-03-16')])];

        self::assertSame(['a'], $this->rutas(Coleccion::donde($paginas, 'fecha', '2025-03-15')));
    }

    #[Test]
    public function dondeVeTambienLaUrlYLaRuta(): void
    {
        $paginas = $this->conUrlYRuta();

        self::assertSame(['contacto.md'], $this->rutas(Coleccion::donde($paginas, 'url', '/contacto/')));
        self::assertSame(['blog/hola.md'], $this->rutas(Coleccion::donde($paginas, 'url', '/blog/hola/')));
        self::assertSame(['blog/hola.md'], $this->rutas(Coleccion::donde($paginas, 'permalink', '/blog/hola/')));
        self::assertSame([], $this->rutas(Coleccion::donde($paginas, 'url', '/blog/{{ titulo|slug }}/')));
        self::assertSame(['pie.md'], $this->rutas(Coleccion::donde($paginas, 'ruta', 'pie.md')));
    }

    #[Test]
    public function ordenVeTambienLaUrlYLaRuta(): void
    {
        $paginas = $this->conUrlYRuta();

        self::assertSame(['contacto.md', 'blog/hola.md', 'pie.md'], $this->rutas(Coleccion::orden($paginas, 'url desc')));
        self::assertSame(['blog/hola.md', 'contacto.md', 'pie.md'], $this->rutas(Coleccion::orden($paginas, 'ruta')));
    }

    #[Test]
    public function anteriorYSiguienteDanLasPaginasDeAlLado(): void
    {
        $paginas = $this->paginas('a', 'b', 'c');

        self::assertSame('a', Coleccion::anterior($paginas, $paginas[1])?->ruta);
        self::assertSame('c', Coleccion::siguiente($paginas, 'b')?->ruta);
        self::assertNull(Coleccion::anterior($paginas, 'a'));
        self::assertNull(Coleccion::siguiente($paginas, 'c'));
        self::assertNull(Coleccion::anterior($paginas, 'otra'));
        self::assertNull(Coleccion::siguiente($paginas, 'otra'));
    }

    /**
     * @param array<array-key, mixed> $campos
     */
    /**
     * Ordenar guarda la clave de texto de cada valor mientras dura la llamada, en vez de calcularla en cada
     * comparación (a 640 páginas son 12.000 claves para 640 valores): tiene que dar exactamente el mismo orden que
     * comparar los pares con `Coleccion::comparar`, con textos con tildes, ñ, «¿» y mayúsculas, números, texto que
     * parece número, fechas, listas, valores que faltan y empates.
     *
     * @return iterable<string, array{string}>
     */
    public static function criterios(): iterable
    {
        foreach (['titulo', 'titulo desc', 'orden', 'orden desc', 'fecha desc', 'fecha asc', 'etiquetas', 'ruta', 'url', 'descripcion', 'titulo asc, orden desc', 'orden, titulo desc', 'activa, titulo'] as $criterio) {
            yield $criterio => [$criterio];
        }
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('criterios')]
    public function ordenaIgualQueCompararCadaParDeValores(string $criterio): void
    {
        $titulos = ['Ñandú', 'nube', 'Zorro', '¿Cómo llegar?', 'Árbol', 'arbol', 'ÁRBOL', 'Éxito', 'exito', 'Uno', '10', '9', '2 gatos', 'Nina', 'niño', 'Niño', 'ñu', 'Ñu', 'zeta', '¡Hola!', 'Cañón', 'Canon', 'canción', ''];
        $ordenes = [3, 1.5, '2', 10, '10', null, true, false, 'dos', 7, 7.0, '7'];
        $paginas = [];

        foreach ($titulos as $i => $titulo) {
            foreach ([0, 1] as $copia) {
                $campos = ['titulo' => $titulo];

                if ($i % 5 !== 4) {
                    $campos['orden'] = $ordenes[($i + $copia) % count($ordenes)];
                }

                if ($i % 3 !== 0) {
                    $campos['fecha'] = $this->fecha('2025-0' . (1 + ($i + $copia) % 9) . '-1' . ($i % 10));
                }

                if ($i % 4 !== 3) {
                    $campos['etiquetas'] = $copia === 0 ? ['blog', 'ñoño'] : ['Blog'];
                }

                if ($i % 6 === 1) {
                    $campos['descripcion'] = $titulo . ' descrita';
                }

                $campos['activa'] = $i % 2 === 0;
                $paginas[] = $this->pagina("p{$i}-{$copia}", $campos);
            }
        }

        $claves = [];

        foreach (explode(',', $criterio) as $parte) {
            $palabras = preg_split('/\s+/', trim($parte));
            $claves[] = [$palabras[0], ($palabras[1] ?? 'asc') === 'desc'];
        }

        $esperado = $paginas;
        usort($esperado, function (Pagina $a, Pagina $b) use ($claves): int {
            foreach ($claves as [$campo, $descendente]) {
                $resultado = Coleccion::comparar($a->valor($campo), $b->valor($campo), $descendente);

                if ($resultado !== 0) {
                    return $resultado;
                }
            }

            return 0;
        });

        self::assertSame($this->rutas($esperado), $this->rutas(Coleccion::orden($paginas, $criterio)));
    }

    private function pagina(string $ruta, array $campos = []): Pagina
    {
        return new Pagina($ruta, 'md', $campos, "/{$ruta}/", '', 1);
    }

    /**
     * @return list<Pagina>
     */
    private function paginas(string ...$rutas): array
    {
        return array_map(fn (string $ruta) => $this->pagina($ruta), array_values($rutas));
    }

    /**
     * Una página con la URL de un patrón, otra con la que sale de su ruta y
     * un fragmento, que no tiene URL.
     *
     * @return list<Pagina>
     */
    private function conUrlYRuta(): array
    {
        return [
            new Pagina('pie.md', 'md', [], false, '', 1),
            new Pagina('blog/hola.md', 'md', ['titulo' => 'Hola', 'url' => '/blog/{{ titulo|slug }}/'], '/blog/hola/', '', 1),
            new Pagina('contacto.md', 'md', [], '/contacto/', '', 1),
        ];
    }

    private function fecha(string $dia): \DateTimeImmutable
    {
        return new \DateTimeImmutable($dia, new \DateTimeZone('UTC'));
    }

    /**
     * @param list<Pagina> $paginas
     *
     * @return list<string>
     */
    private function rutas(array $paginas): array
    {
        return array_map(fn (Pagina $pagina) => $pagina->ruta, $paginas);
    }
}
