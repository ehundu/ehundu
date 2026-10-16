<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Construccion;
use Ehundu\Constructor;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `paginar` (formato §6.4) en la construcción incremental: la serie se decide
 * de nuevo en cada construcción, y cada página se rehace cuando cambia lo que
 * la define o su trozo. Después de cada cambio se compara con una
 * construcción completa.
 */
final class PaginarIncrementalPrueba extends TestCase
{
    use CarpetaTemporal;

    private const string AHORA = '2026-01-01 12:00:00';

    private const string LISTA = '{% set p = pagina.paginacion %}[{{ p.actual }}/{{ p.total }}]{% for a in pagina.paginas %}{{ a.titulo }},{% endfor %}';

    #[Test]
    public function cadaCambioRehaceSoloLoQueLeAfectaYDaLoMismoQueUnaConstruccionCompleta(): void
    {
        $this->crearSitio();
        $constructor = new Constructor(incremental: true);
        $ahora = new \DateTimeImmutable(self::AHORA);

        $pasos = [
            // Siete artículos, la portada, la lista y sus dos páginas.
            'primera construcción' => [fn () => null, 11],
            'nada cambia' => [fn () => null, 0],
            'cambia el cuerpo de un artículo, que la lista no enseña: solo él' => [
                fn () => $this->poner('contenido/blog/cuatro.md', $this->articulo('Cuatro', 4, 'Cuatro, cambiado.')),
                1,
            ],
            'cambia el título de un artículo: él, su página de la lista y la portada, que recorre la colección' => [
                fn () => $this->poner('contenido/blog/cuatro.md', $this->articulo('Cuatro bis', 4, 'Cuatro, cambiado.')),
                3,
            ],
            'aparece un artículo, que desplaza a todos: él, las tres páginas de la lista y la portada' => [
                fn () => $this->poner('contenido/blog/ocho.md', $this->articulo('Ocho', 8)),
                5,
            ],
            'aparecen dos más y hace falta una cuarta página: los dos, las cuatro de la lista y la portada' => [
                function (): void {
                    $this->poner('contenido/blog/nueve.md', $this->articulo('Nueve', 9));
                    $this->poner('contenido/blog/diez.md', $this->articulo('Diez', 10));
                },
                7,
            ],
            'desaparecen y la cuarta página desaparece con ellos' => [
                function (): void {
                    $this->quitar('contenido/blog/nueve.md');
                    $this->quitar('contenido/blog/diez.md');
                },
                4,
            ],
            'cambia la plantilla de la lista: solo sus páginas' => [
                fn () => $this->poner('plantillas/lista.twig', '{% set p = pagina.paginacion %}<{{ p.actual }}/{{ p.total }}>{% for a in pagina.paginas %}{{ a.titulo }},{% endfor %}'),
                3,
            ],
            'un artículo pasa a borrador: él deja de salir, y la lista y la portada cambian' => [
                fn () => $this->poner('contenido/blog/tres.md', $this->articulo('Tres', 3, 'Tres.', "borrador: sí\n")),
                4,
            ],
            'cambia el número de artículos por página: la lista y la portada, que la hereda en la colección' => [
                fn () => $this->poner('contenido/blog/index.md', $this->lista("  porPagina: 4\n")),
                3,
            ],
            'cambia la dirección de las que se generan' => [
                fn () => $this->poner('contenido/blog/index.md', $this->lista("  porPagina: 4\n  url: \"/blog/{{ numero }}/\"\n")),
                3,
            ],
            'la lista deja de partirse: la página que quedó y la portada' => [
                fn () => $this->poner('contenido/blog/index.md', "---\ntitulo: Blog\nplantilla: lista\nurl: /blog/\n---\nLista.\n"),
                2,
            ],
            'y vuelve a partirse' => [
                fn () => $this->poner('contenido/blog/index.md', $this->lista("  porPagina: 4\n")),
                3,
            ],
        ];

        foreach ($pasos as $paso => [$cambio, $rehechas]) {
            $cambio();
            $construccion = $this->comprobar($constructor, $ahora, $paso);

            self::assertSame($rehechas, $construccion?->rehechas, "Páginas rehechas: {$paso}");
        }
    }

    #[Test]
    public function unaEntradaProgramadaEntraEnLaSerieCuandoLlegaSuFecha(): void
    {
        $this->crearSitio();
        $this->poner('contenido/blog/futuro.md', $this->articulo('Futuro', 11, 'Aún no.', "publicar: 2030-01-01\n"));
        $constructor = new Constructor(incremental: true);

        $antes = $this->comprobar($constructor, new \DateTimeImmutable(self::AHORA), 'antes de su fecha');
        self::assertStringContainsString('[1/3]Siete,Seis,Cinco,', $antes->escritos['blog/index.html'] ?? '');

        $despues = $this->comprobar($constructor, new \DateTimeImmutable('2031-01-01'), 'cuando llega');
        self::assertStringContainsString('[1/3]Futuro,Siete,Seis,', $despues->escritos['blog/index.html'] ?? '');
        self::assertStringContainsString('[3/3]Dos,Uno,', $despues->escritos['blog/pagina/3/index.html'] ?? '');
    }

    /**
     * Las dos construcciones tienen que dar lo mismo: los mismos ficheros con
     * el mismo contenido y los mismos avisos.
     */
    private function comprobar(Constructor $incremental, \DateTimeImmutable $ahora, string $paso): ?Construccion
    {
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $completa = (new Constructor())->construir($proyecto, $ahora);
        $parcial = $incremental->construir($proyecto, $ahora);

        self::assertSame(array_keys($completa->escritos), array_keys($parcial->escritos), $paso);

        foreach ($completa->escritos as $fichero => $contenido) {
            self::assertSame($contenido, $parcial->escritos[$fichero], "{$paso}: {$fichero}");
        }

        self::assertSame($completa->copias, $parcial->copias, $paso);
        self::assertSame($completa->paginas, $parcial->paginas, $paso);
        self::assertSame(array_map(strval(...), $completa->avisos), array_map(strval(...), $parcial->avisos), $paso);

        return $parcial;
    }

    /**
     * Siete artículos, uno al mes, la portada, que enseña los dos últimos, y
     * la lista del blog, que los reparte de tres en tres. Salen once páginas.
     */
    private function crearSitio(): void
    {
        $this->poner('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\n");
        $this->poner('plantillas/pagina.twig', '{{ pagina.contenido }}');
        $this->poner('plantillas/lista.twig', self::LISTA);
        $this->poner(
            'contenido/index.twig',
            "---\ntitulo: Inicio\nplantilla: false\n---\n{% for a in coleccion('blog')|orden('fecha desc')|limite(2) %}{{ a.titulo }},{% endfor %}",
        );
        $this->poner('contenido/blog/_datos.yml', "etiquetas: [blog]\nurl: \"/blog/{{ titulo|slug }}/\"\n");

        foreach (['Uno', 'Dos', 'Tres', 'Cuatro', 'Cinco', 'Seis', 'Siete'] as $i => $titulo) {
            $this->poner('contenido/blog/' . strtolower($titulo) . '.md', $this->articulo($titulo, $i + 1));
        }

        $this->poner('contenido/blog/index.md', $this->lista());
    }

    private function articulo(string $titulo, int $mes, string $cuerpo = '', string $extra = ''): string
    {
        return sprintf("---\ntitulo: %s\nfecha: 2024-%02d-01\n%s---\n%s\n", $titulo, $mes, $extra, $cuerpo === '' ? "{$titulo}." : $cuerpo);
    }

    private function lista(string $porPagina = "  porPagina: 3\n"): string
    {
        return "---\ntitulo: Blog\nplantilla: lista\nurl: /blog/\npaginar:\n  coleccion: blog\n{$porPagina}  orden: fecha desc\n---\nLista.\n";
    }

    private function poner(string $ruta, string $contenido): void
    {
        $this->crearFichero($ruta, $contenido);
    }

    private function quitar(string $ruta): void
    {
        unlink($this->carpetaTemporal() . "/{$ruta}");
    }
}
