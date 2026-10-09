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
 * Las páginas que genera `agrupar` (formato §6.3) en la compilación
 * incremental: una etiqueta nueva crea su página, la última página de una
 * etiqueta la quita, y cada página de grupo se rehace solo cuando cambia algo
 * de lo que muestra. Después de cada cambio, la construcción incremental tiene
 * que dar lo mismo que una completa.
 */
final class AgruparIncrementalPrueba extends TestCase
{
    use CarpetaTemporal;

    private const string AHORA = '2026-01-01 12:00:00';

    /** @var array<string, string> el proyecto tal como debe quedar en disco */
    private array $ficheros = [];

    #[Test]
    public function cadaCambioRehaceSoloLasPaginasDeGrupoQueLeAfectan(): void
    {
        $this->crearSitio();
        $constructor = new Constructor(incremental: true);
        $ahora = new \DateTimeImmutable(self::AHORA);

        $pasos = [
            'primera construcción: tres artículos, el índice y tres etiquetas' => [fn () => null, 7],
            'nada cambia' => [fn () => null, 0],
            'cambia el cuerpo de un artículo: solo él, porque las etiquetas muestran títulos' => [
                fn () => $this->cambiar('contenido/blog/dos.md', 'Segundo.', 'Segundo, revisado.'),
                1,
            ],
            'cambia el título de un artículo: él, la etiqueta que lo lista y el índice, que lee todos los grupos' => [
                fn () => $this->cambiar('contenido/blog/dos.md', 'titulo: Dos', 'titulo: Segundo'),
                3,
            ],
            'un artículo gana una etiqueta que existe: él, las dos etiquetas que lo listan y el índice' => [
                fn () => $this->cambiar('contenido/blog/tres.md', 'etiquetas: [cultura]', 'etiquetas: [cultura, oficio]'),
                4,
            ],
            'aparece un artículo con una etiqueta nueva: él, su etiqueta y el índice' => [
                fn () => $this->poner('contenido/blog/cuatro.md', "---\ntitulo: Cuatro\nfecha: 2024-04-01\netiquetas: [viajes]\n---\nCuarto.\n"),
                3,
            ],
            'desaparece su único artículo: sale la etiqueta y se rehace el índice' => [
                fn () => $this->quitar('contenido/blog/cuatro.md'),
                1,
            ],
            'cambia la plantilla de las etiquetas: las tres' => [
                fn () => $this->cambiar('plantillas/tema.twig', '<ul>', '<ul class="lista">'),
                3,
            ],
            'cambia el cuerpo de la plantilla de grupos: todas las que genera' => [
                fn () => $this->cambiar('contenido/tema.md', 'Cuerpo.', 'Cuerpo, revisado.'),
                3,
            ],
            'un artículo pasa a borrador: sale de sus etiquetas y se rehacen ellas y el índice' => [
                fn () => $this->cambiar('contenido/blog/uno.md', 'titulo: Uno', "titulo: Uno\nborrador: sí"),
                3,
            ],
            'y vuelve a publicarse' => [
                fn () => $this->cambiar('contenido/blog/uno.md', "\nborrador: sí", ''),
                4,
            ],
            'la plantilla de grupos deja de excluir `blog`: aparece su etiqueta, con todos los artículos' => [
                fn () => $this->cambiar('contenido/tema.md', 'excluir: [blog]', 'excluir: []'),
                2,
            ],
            'la plantilla cambia de URL: todas las etiquetas, y el índice, que las enlaza' => [
                fn () => $this->cambiar('contenido/tema.md', '/tema/{{ grupo|slug }}/', '/gaia/{{ grupo|slug }}/'),
                5,
            ],
            'aparece un artículo programado: aún no sale' => [
                fn () => $this->poner('contenido/blog/futuro.md', "---\ntitulo: Futuro\nfecha: 2024-05-01\npublicar: 2030-01-01\netiquetas: [novedades]\n---\nAún no.\n"),
                0,
            ],
        ];

        foreach ($pasos as $paso => [$cambio, $rehechas]) {
            $cambio();
            $construccion = $this->comprobar($constructor, $ahora, $paso);

            self::assertSame($rehechas, $construccion?->rehechas, "Páginas rehechas: {$paso}");
        }

        // Él, su etiqueta, la etiqueta `blog` (que ya no se excluye y lo gana como miembro) y el índice.
        $construccion = $this->comprobar($constructor, new \DateTimeImmutable('2031-01-01'), 'llega una fecha de publicación');
        self::assertSame(4, $construccion?->rehechas);
        self::assertArrayHasKey('gaia/novedades/index.html', $construccion->escritos);
    }

    #[Test]
    public function unaSecuenciaDeCambiosAlAzarDaLoMismoQueUnaConstruccionCompleta(): void
    {
        $this->crearSitio();
        $constructor = new Constructor(incremental: true);
        $ahora = new \DateTimeImmutable(self::AHORA);
        $aleatorio = new \Random\Randomizer(new \Random\Engine\Mt19937(20261009));
        $cambios = $this->cambiosAlAzar($aleatorio);
        $this->comprobar($constructor, $ahora, 'primera construcción');

        for ($paso = 1; $paso <= 120; $paso++) {
            $nombre = $aleatorio->pickArrayKeys($cambios, 1)[0];
            $cambios[$nombre]();
            $this->comprobar($constructor, $ahora, "paso {$paso}: {$nombre}");
        }
    }

    #[Test]
    public function enDosIdiomasCadaIdiomaRehaceSusEtiquetas(): void
    {
        $this->crearSitioEnDosIdiomas();
        $constructor = new Constructor(incremental: true);
        $ahora = new \DateTimeImmutable(self::AHORA);

        $pasos = [
            'primera construcción' => [fn () => null, 6],
            'nada cambia' => [fn () => null, 0],
            'cambia el título de un artículo en euskera: él y su etiqueta, no las del castellano' => [
                fn () => $this->cambiar('contenido/blog/bat.eu.md', 'titulo: Bat', 'titulo: Lehena'),
                2,
            ],
            'aparece una etiqueta nueva en euskera: el artículo y su etiqueta' => [
                fn () => $this->poner('contenido/blog/bi.eu.md', "---\ntitulo: Bi\nfecha: 2024-02-01\netiquetas: [bidaiak]\n---\nBi.\n"),
                2,
            ],
            'una etiqueta en castellano cambia de URL con la plantilla: solo las del castellano' => [
                fn () => $this->cambiar('contenido/tema.md', '/tema/{{ grupo|slug }}/', '/temas/{{ grupo|slug }}/'),
                1,
            ],
        ];

        foreach ($pasos as $paso => [$cambio, $rehechas]) {
            $cambio();
            $construccion = $this->comprobar($constructor, $ahora, $paso);

            self::assertSame($rehechas, $construccion?->rehechas, "Páginas rehechas: {$paso}");
        }
    }

    /**
     * Construye con el constructor incremental y con uno nuevo, y comprueba
     * que dan lo mismo: los mismos ficheros con el mismo contenido y en el
     * mismo orden, las mismas copias y los mismos avisos; o el mismo error.
     */
    private function comprobar(Constructor $incremental, \DateTimeImmutable $ahora, string $paso): ?Construccion
    {
        $proyecto = Proyecto::abrir($this->carpetaTemporal());

        try {
            $completa = (new Constructor())->construir($proyecto, $ahora);
        } catch (\Throwable $error) {
            $completa = $error;
        }

        try {
            $parcial = $incremental->construir($proyecto, $ahora);
        } catch (\Throwable $error) {
            $parcial = $error;
        }

        if ($completa instanceof \Throwable) {
            self::assertInstanceOf(\Throwable::class, $parcial, "Tenía que fallar: {$paso}");
            self::assertSame($completa->getMessage(), $parcial->getMessage(), $paso);

            return null;
        }

        self::assertInstanceOf(Construccion::class, $parcial, $parcial instanceof \Throwable ? "{$paso}: {$parcial->getMessage()}" : $paso);
        self::assertSame(array_keys($completa->escritos), array_keys($parcial->escritos), $paso);

        foreach ($completa->escritos as $fichero => $contenido) {
            self::assertSame($contenido, $parcial->escritos[$fichero], "{$paso}: {$fichero}");
        }

        self::assertSame($completa->copias, $parcial->copias, $paso);
        self::assertSame($completa->paginas, $parcial->paginas, $paso);
        self::assertSame(array_map('strval', $completa->avisos), array_map('strval', $parcial->avisos), $paso);

        return $parcial;
    }

    /**
     * Tres artículos con sus etiquetas, un índice de las etiquetas y la página
     * que las genera. Salen siete páginas: los artículos, el índice y tres
     * etiquetas.
     */
    private function crearSitio(): void
    {
        $this->ficheros = [
            'sitio.yml' => "nombre: Prueba\nurl: https://ejemplo.com\n",
            'plantillas/pagina.twig' => '{{ pagina.contenido }}',
            'plantillas/tema.twig' => '<h1>{{ pagina.titulo }}</h1><ul>{% for articulo in pagina.paginas %}<li>{{ articulo.titulo }}</li>{% endfor %}</ul>',
            'contenido/blog/_datos.yml' => "etiquetas: [blog]\nurl: \"/blog/{{ titulo|slug }}/\"\n",
            'contenido/blog/uno.md' => "---\ntitulo: Uno\nfecha: 2024-01-01\netiquetas: [ideas, oficio]\n---\nPrimero.\n",
            'contenido/blog/dos.md' => "---\ntitulo: Dos\nfecha: 2024-02-01\netiquetas: [ideas]\n---\nSegundo.\n",
            'contenido/blog/tres.md' => "---\ntitulo: Tres\nfecha: 2024-03-01\netiquetas: [cultura]\n---\nTercero.\n",
            'contenido/temas.twig' => "---\ntitulo: Temas\nurl: /temas/\n---\n{% for tema in coleccion('temas')|orden('grupo') %}<a href=\"{{ tema.url }}\">{{ tema.grupo }}</a>={{ tema.paginas|length }};{% endfor %}",
            'contenido/tema.md' => "---\nplantilla: tema\nagrupar:\n  coleccion: blog\n  campo: etiquetas\n  excluir: [blog]\nurl: \"/tema/{{ grupo|slug }}/\"\netiquetas: [temas]\n---\nCuerpo.\n",
        ];

        foreach ($this->ficheros as $ruta => $contenido) {
            $this->crearFichero($ruta, $contenido);
        }
    }

    private function crearSitioEnDosIdiomas(): void
    {
        $this->ficheros = [
            'sitio.yml' => "nombre: Prueba\nurl: https://ejemplo.com\nidiomas:\n  - codigo: es\n  - codigo: eu\n",
            'plantillas/pagina.twig' => '{{ pagina.contenido }}',
            'plantillas/tema.twig' => '<h1>{{ pagina.titulo }}</h1><ul>{% for articulo in pagina.paginas %}<li>{{ articulo.titulo }}</li>{% endfor %}</ul>',
            'contenido/index.md' => "---\ntitulo: Inicio\n---\nInicio.\n",
            'contenido/index.eu.md' => "---\ntitulo: Hasiera\n---\nHasiera.\n",
            'contenido/blog/_datos.yml' => "etiquetas: [blog]\nurl: \"/blog/{{ titulo|slug }}/\"\n",
            'contenido/blog/uno.md' => "---\ntitulo: Uno\nfecha: 2024-01-01\netiquetas: [ideas]\n---\nPrimero.\n",
            'contenido/blog/bat.eu.md' => "---\ntitulo: Bat\nfecha: 2024-01-01\netiquetas: [ideiak]\n---\nLehena.\n",
            'contenido/tema.md' => "---\nplantilla: tema\nagrupar:\n  coleccion: blog\n  campo: etiquetas\n  excluir: [blog]\nurl: \"/tema/{{ grupo|slug }}/\"\n---\nCuerpo.\n",
            'contenido/tema.eu.md' => "---\nplantilla: tema\nagrupar:\n  coleccion: blog\n  campo: etiquetas\n  excluir: [blog]\nurl: \"/gaia/{{ grupo|slug }}/\"\n---\nGorputza.\n",
        ];

        foreach ($this->ficheros as $ruta => $contenido) {
            $this->crearFichero($ruta, $contenido);
        }
    }

    /**
     * Cambios con los que se hace la secuencia al azar. Algunos no cambian
     * nada cuando no pueden aplicarse, y está bien: también hay que probar
     * construcciones sin cambios.
     *
     * @return array<string, \Closure(): void>
     */
    private function cambiosAlAzar(\Random\Randomizer $aleatorio): array
    {
        $palabra = fn () => $aleatorio->getBytesFromString('abcdefghijklmnopqrstuvwxyz', 5);
        $etiquetas = ['ideas', 'Ideas', 'oficio', 'cultura', 'Cultura pop', 'cultura-pop', 'viajes', '2026', 'blog', '!!!'];
        $articulos = fn () => array_values(array_filter(
            array_keys($this->ficheros),
            fn (string $ruta) => str_starts_with($ruta, 'contenido/blog/') && str_ends_with($ruta, '.md'),
        ));
        $uno = function (array $lista) use ($aleatorio): string {
            return $lista[$aleatorio->getInt(0, count($lista) - 1)];
        };
        $nuevos = 0;

        return [
            'cuerpo de un artículo' => function () use ($articulos, $uno, $palabra): void {
                $ruta = $uno($articulos());
                $this->poner($ruta, $this->ficheros[$ruta] . $palabra() . "\n");
            },
            'título de un artículo' => function () use ($articulos, $uno, $palabra): void {
                $ruta = $uno($articulos());
                $this->poner($ruta, (string) preg_replace('/^titulo: .*$/m', 'titulo: ' . $palabra(), $this->ficheros[$ruta], 1));
            },
            'etiquetas de un artículo' => function () use ($articulos, $uno, $aleatorio, $etiquetas): void {
                $ruta = $uno($articulos());
                $elegidas = array_map(fn () => $etiquetas[$aleatorio->getInt(0, count($etiquetas) - 1)], range(0, $aleatorio->getInt(0, 3)));
                $lista = 'etiquetas: [' . implode(', ', array_map(fn (string $e) => "'{$e}'", array_unique($elegidas))) . ']';
                $this->poner($ruta, (string) preg_replace('/^etiquetas: .*$/m', $lista, $this->ficheros[$ruta], 1));
            },
            'artículo nuevo' => function () use (&$nuevos, $palabra, $aleatorio, $etiquetas): void {
                $nuevos++;
                $etiqueta = $etiquetas[$aleatorio->getInt(0, count($etiquetas) - 1)];
                $this->poner("contenido/blog/nuevo-{$nuevos}.md", "---\ntitulo: Nuevo {$nuevos}\nfecha: 2024-01-" . sprintf('%02d', $nuevos % 28 + 1) . "\netiquetas: ['{$etiqueta}']\n---\n" . $palabra() . "\n");
            },
            'artículo borrado' => function () use ($articulos, $uno): void {
                $ruta = $uno($articulos());

                if (count($articulos()) > 1) {
                    $this->quitar($ruta);
                }
            },
            'borrador sí o no' => function () use ($articulos, $uno): void {
                $ruta = $uno($articulos());
                $texto = $this->ficheros[$ruta];
                $this->poner($ruta, str_contains($texto, "borrador: sí\n")
                    ? str_replace("borrador: sí\n", '', $texto)
                    : (string) preg_replace('/^---\n/', "---\nborrador: sí\n", $texto));
            },
            'programado sí o no' => function () use ($articulos, $uno): void {
                $ruta = $uno($articulos());
                $texto = $this->ficheros[$ruta];
                $this->poner($ruta, str_contains($texto, "publicar: 2030-01-01\n")
                    ? str_replace("publicar: 2030-01-01\n", '', $texto)
                    : (string) preg_replace('/^---\n/', "---\npublicar: 2030-01-01\n", $texto));
            },
            'URL de un artículo' => function () use ($articulos, $uno, $palabra): void {
                $ruta = $uno($articulos());
                $texto = $this->ficheros[$ruta];
                $this->poner($ruta, str_contains($texto, "\nurl: ")
                    ? (string) preg_replace('/^url: .*\n/m', '', $texto)
                    : (string) preg_replace('/^---\n/', "---\nurl: /{$palabra()}/\n", $texto));
            },
            'plantilla de las etiquetas' => fn () => $this->poner('plantillas/tema.twig', $this->ficheros['plantillas/tema.twig'] . '{# ' . $palabra() . ' #}'),
            'cuerpo de la plantilla de grupos' => fn () => $this->poner('contenido/tema.md', $this->ficheros['contenido/tema.md'] . $palabra() . "\n"),
            'excluir `blog` sí o no' => fn () => $this->poner('contenido/tema.md', str_contains($this->ficheros['contenido/tema.md'], 'excluir: [blog]')
                ? str_replace('excluir: [blog]', 'excluir: []', $this->ficheros['contenido/tema.md'])
                : str_replace('excluir: []', 'excluir: [blog]', $this->ficheros['contenido/tema.md'])),
            'URL de la plantilla de grupos' => fn () => $this->poner('contenido/tema.md', str_contains($this->ficheros['contenido/tema.md'], '/tema/')
                ? str_replace('/tema/', '/gaia/', $this->ficheros['contenido/tema.md'])
                : str_replace('/gaia/', '/tema/', $this->ficheros['contenido/tema.md'])),
            'título propio de la plantilla de grupos' => fn () => $this->poner('contenido/tema.md', str_contains($this->ficheros['contenido/tema.md'], "titulo: Tema\n")
                ? str_replace("titulo: Tema\n", '', $this->ficheros['contenido/tema.md'])
                : (string) preg_replace('/^---\n/', "---\ntitulo: Tema\n", $this->ficheros['contenido/tema.md'])),
            'plantilla de grupos rota o arreglada' => fn () => $this->poner('contenido/tema.md', str_contains($this->ficheros['contenido/tema.md'], 'coleccion: blog')
                ? str_replace('coleccion: blog', 'coleccion: todo', $this->ficheros['contenido/tema.md'])
                : str_replace('coleccion: todo', 'coleccion: blog', $this->ficheros['contenido/tema.md'])),
            'nada' => fn () => null,
        ];
    }

    private function cambiar(string $ruta, string $antes, string $despues): void
    {
        self::assertStringContainsString($antes, $this->ficheros[$ruta], "No se puede cambiar {$ruta}");
        $this->poner($ruta, str_replace($antes, $despues, $this->ficheros[$ruta]));
    }

    private function poner(string $ruta, string $contenido): void
    {
        $this->ficheros[$ruta] = $contenido;
        $this->crearFichero($ruta, $contenido);
    }

    private function quitar(string $ruta): void
    {
        unset($this->ficheros[$ruta]);
        unlink($this->carpetaTemporal() . "/{$ruta}");
    }
}
