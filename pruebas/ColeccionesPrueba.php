<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Colecciones;
use Ehundu\Idiomas;
use Ehundu\Pagina;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ColeccionesPrueba extends TestCase
{
    #[Test]
    public function cadaEtiquetaDaUnaColeccion(): void
    {
        $colecciones = $this->colecciones(
            $this->pagina('blog/a.md', ['etiquetas' => ['blog', 'novela']]),
            $this->pagina('blog/b.md', ['etiquetas' => ['blog']]),
            $this->pagina('contacto.md'),
        );

        self::assertSame(['blog/a.md', 'blog/b.md'], $this->rutas($colecciones->coleccion('blog')));
        self::assertSame(['blog/a.md'], $this->rutas($colecciones->coleccion('novela')));
    }

    #[Test]
    public function todoReuneLasPaginasConUrl(): void
    {
        $colecciones = $this->colecciones(
            $this->pagina('blog/a.md', ['etiquetas' => ['blog']]),
            $this->pagina('contacto.md'),
            $this->pagina('destacado.md', ['etiquetas' => ['portada']], url: false),
        );

        self::assertSame(['blog/a.md', 'contacto.md'], $this->rutas($colecciones->coleccion('todo')));
    }

    #[Test]
    public function losFragmentosEntranEnSusEtiquetasPeroNoEnTodo(): void
    {
        $colecciones = $this->colecciones($this->pagina('destacado.md', ['etiquetas' => ['portada']], url: false));

        self::assertSame(['destacado.md'], $this->rutas($colecciones->coleccion('portada')));
        self::assertSame([], $colecciones->coleccion('todo'));
    }

    #[Test]
    public function losBorradoresYLasPaginasFuturasNoEntran(): void
    {
        $colecciones = $this->colecciones(
            $this->pagina('a.md', ['etiquetas' => ['blog'], 'borrador' => true]),
            $this->pagina('b.md', ['etiquetas' => ['blog'], 'publicar' => new \DateTimeImmutable('2030-01-01')]),
            $this->pagina('c.md', ['etiquetas' => ['blog'], 'publicar' => new \DateTimeImmutable('2020-01-01')]),
        );

        self::assertSame(['c.md'], $this->rutas($colecciones->coleccion('blog')));
        self::assertSame(['c.md'], $this->rutas($colecciones->coleccion('todo')));
    }

    #[Test]
    public function unaPaginaNoListadaNoEntraEnNinguna(): void
    {
        $colecciones = $this->colecciones($this->pagina('aviso-legal.md', ['etiquetas' => ['legal'], 'listada' => false]));

        self::assertSame([], $colecciones->coleccion('legal'));
        self::assertSame([], $colecciones->coleccion('todo'));
    }

    #[Test]
    public function unaColeccionQueNoExisteEstaVacia(): void
    {
        self::assertSame([], $this->colecciones()->coleccion('recetas'));
    }

    #[Test]
    public function vanPorFechaYAIgualFechaPorRuta(): void
    {
        $colecciones = $this->colecciones(
            $this->pagina('blog/c.md', ['etiquetas' => ['blog'], 'fecha' => new \DateTimeImmutable('2025-03-15')]),
            $this->pagina('blog/sin-fecha-b.md', ['etiquetas' => ['blog']]),
            $this->pagina('blog/a.md', ['etiquetas' => ['blog'], 'fecha' => new \DateTimeImmutable('2025-03-15')]),
            $this->pagina('blog/antiguo.md', ['etiquetas' => ['blog'], 'fecha' => new \DateTimeImmutable('2024-01-01')]),
            $this->pagina('blog/sin-fecha-a.md', ['etiquetas' => ['blog']]),
        );

        self::assertSame(
            ['blog/antiguo.md', 'blog/a.md', 'blog/c.md', 'blog/sin-fecha-a.md', 'blog/sin-fecha-b.md'],
            $this->rutas($colecciones->coleccion('blog')),
        );
    }

    #[Test]
    public function unaColeccionSePuedePedirEnUnSoloIdioma(): void
    {
        $colecciones = $this->colecciones(
            $this->pagina('blog/a.md', ['etiquetas' => ['blog']]),
            $this->pagina('blog/a.eu.md', ['etiquetas' => ['blog']], idioma: 'eu'),
            $this->pagina('blog/b.md', ['etiquetas' => ['blog']]),
        );

        self::assertSame(['blog/a.eu.md', 'blog/a.md', 'blog/b.md'], $this->rutas($colecciones->coleccion('blog')));
        self::assertSame(['blog/a.eu.md', 'blog/a.md', 'blog/b.md'], $this->rutas($colecciones->coleccion('blog', 'todos')));
        self::assertSame(['blog/a.md', 'blog/b.md'], $this->rutas($colecciones->coleccion('blog', 'es')));
        self::assertSame(['blog/a.eu.md'], $this->rutas($colecciones->coleccion('blog', 'eu')));
        self::assertSame(['blog/a.eu.md'], $this->rutas($colecciones->coleccion('todo', 'eu')));
        self::assertSame([], $colecciones->coleccion('blog', 'en'));
    }

    #[Test]
    public function lasTraduccionesSonLasVersionesPublicadasEnElOrdenDeLosIdiomas(): void
    {
        $idiomas = new Idiomas([['codigo' => 'es', 'nombre' => 'Castellano'], ['codigo' => 'eu', 'nombre' => 'Euskara'], ['codigo' => 'en', 'nombre' => 'English']], true);
        $es = $this->pagina('aviso-legal.md', ['listada' => false]);
        $en = $this->pagina('aviso-legal.en.md', idioma: 'en');
        $eu = $this->pagina('aviso-legal.eu.md', idioma: 'eu');
        $borrador = $this->pagina('contacto.eu.md', ['borrador' => true], idioma: 'eu');
        $contacto = $this->pagina('contacto.md');

        $colecciones = new Colecciones([$en, $es, $eu, $borrador, $contacto], new \DateTimeImmutable('2026-09-25'), idiomas: $idiomas);

        self::assertSame(['es' => $es, 'eu' => $eu, 'en' => $en], $colecciones->traducciones($en));
        self::assertSame(['es' => $contacto], $colecciones->traducciones($contacto));
        self::assertSame(['es' => $contacto], $colecciones->traducciones($borrador));
    }

    #[Test]
    public function laEtiquetaTodoNoDuplicaLaColeccionImplicita(): void
    {
        $colecciones = $this->colecciones($this->pagina('a.md', ['etiquetas' => ['todo']]));

        self::assertSame(['a.md'], $this->rutas($colecciones->coleccion('todo')));
    }

    private function colecciones(Pagina ...$paginas): Colecciones
    {
        return new Colecciones(array_values($paginas), new \DateTimeImmutable('2026-09-25'));
    }

    /**
     * @param array<array-key, mixed> $campos
     */
    private function pagina(string $ruta, array $campos = [], string|false|null $url = null, string $idioma = 'es'): Pagina
    {
        return new Pagina($ruta, 'md', $campos, $url ?? '/' . substr($ruta, 0, -3) . '/', '', 1, $idioma);
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
