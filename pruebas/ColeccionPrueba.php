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
