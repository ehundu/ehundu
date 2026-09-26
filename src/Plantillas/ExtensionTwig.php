<?php

declare(strict_types=1);

namespace Ehundu\Plantillas;

use Ehundu\Avisos;
use Ehundu\Coleccion;
use Ehundu\Colecciones;
use Ehundu\Dimensiones;
use Ehundu\Fecha;
use Ehundu\Pagina;
use Ehundu\Proyecto;
use Ehundu\Slug;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Lo que Ehundu añade a Twig (formato §5.2, §6, §7 y §9): las funciones
 * `coleccion()`, `svg()`, `dimensiones()`, `activo()`, `css()` y `js()`, y los filtros `slug`, `fecha`, `orden`,
 * `limite`, `invertir`, `sin`, `donde`, `anterior` y `siguiente`.
 *
 * Las plantillas trabajan con vistas de página; los filtros de colección
 * trabajan con páginas. Esta clase traduce entre unas y otras, y usa siempre
 * la misma vista para la misma página.
 */
final class ExtensionTwig extends AbstractExtension
{
    /** @var array<string, VistaDePagina> */
    private array $vistas = [];

    /** @var array<string, string> SVG ya leídos, por ruta dentro de `publico/` */
    private array $svgs = [];

    /**
     * @param \Closure(Pagina): string|null $renderizar da el cuerpo de una página ya en HTML
     * @param Proyecto|null                 $proyecto   de donde lee `svg()`
     * @param \DateTimeZone|null            $zona       la del sitio, para `fecha`; UTC si falta
     */
    public function __construct(
        private readonly Colecciones $colecciones,
        private readonly ?\Closure $renderizar = null,
        private readonly ?Proyecto $proyecto = null,
        private readonly Avisos $avisos = new Avisos(),
        private readonly \DateTimeZone $zona = new \DateTimeZone('UTC'),
        private readonly ?Registros $registros = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('coleccion', function (string $nombre, ?string $idioma = null): array {
                $this->registros?->anotar('colecciones', $nombre);

                return $this->vistas($this->colecciones->coleccion($nombre, $idioma));
            }),
            new TwigFunction('svg', $this->svg(...), ['is_safe' => ['html']]),
            new TwigFunction('dimensiones', $this->dimensiones(...)),
            new TwigFunction('activo', $this->activo(...), ['needs_context' => true]),
            new TwigFunction('css', fn (string ...$rutas) => $this->recurso('css', $rutas), ['is_safe' => ['html']]),
            new TwigFunction('js', fn (string ...$rutas) => $this->recurso('js', $rutas), ['is_safe' => ['html']]),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('slug', fn (mixed $texto) => Slug::de((string) $texto)),
            new TwigFilter('fecha', fn (mixed $valor, string $formato = Fecha::FORMATO) => Fecha::formatear($valor, $formato, $this->zona)),
            new TwigFilter('orden', fn (iterable $lista, string $criterio = Coleccion::ORDEN_POR_DEFECTO) => $this->vistas(
                Coleccion::orden($this->paginas($lista), $criterio),
            )),
            new TwigFilter('limite', fn (iterable $lista, int $cuantas) => $this->vistas(
                Coleccion::limite($this->paginas($lista), $cuantas),
            )),
            new TwigFilter('invertir', fn (iterable $lista) => $this->vistas(
                Coleccion::invertir($this->paginas($lista)),
            )),
            new TwigFilter('sin', fn (iterable $lista, VistaDePagina|string $quitar) => $this->vistas(
                Coleccion::sin($this->paginas($lista), $this->paginaORuta($quitar)),
            )),
            new TwigFilter('donde', fn (iterable $lista, string $campo, mixed $valor) => $this->vistas(
                Coleccion::donde($this->paginas($lista), $campo, $valor),
            )),
            new TwigFilter('anterior', fn (iterable $lista, VistaDePagina|string $actual) => $this->vista(
                Coleccion::anterior($this->paginas($lista), $this->paginaORuta($actual)),
            )),
            new TwigFilter('siguiente', fn (iterable $lista, VistaDePagina|string $actual) => $this->vista(
                Coleccion::siguiente($this->paginas($lista), $this->paginaORuta($actual)),
            )),
        ];
    }

    /**
     * La vista de una página; siempre la misma para la misma página, para
     * que su contenido se convierta una sola vez.
     */
    public function vistaDe(Pagina $pagina): VistaDePagina
    {
        return $this->vistas[$pagina->ruta] ??= new VistaDePagina($pagina, $this->renderizar);
    }

    /**
     * El contenido de un SVG de `publico/`, tal cual, para incrustarlo. Solo
     * se quita la declaración XML y el DOCTYPE, que no caben dentro de HTML.
     * Si no se puede, se avisa y no se inserta nada.
     */
    public function svg(string $ruta): string
    {
        $relativa = ltrim($ruta, '/');
        $this->registros?->anotar('publico', $relativa);
        $fichero = Proyecto::PUBLICO . "/{$relativa}";

        if ($this->proyecto === null) {
            return '';
        }

        if (isset($this->svgs[$relativa])) {
            return $this->svgs[$relativa];
        }

        if (str_contains($relativa, '\\') || preg_match('#(^|/)\.\.?(/|$)#', $relativa) === 1 || !str_ends_with(strtolower($relativa), '.svg')) {
            $this->avisos->registrar("svg('{$ruta}'): solo se insertan ficheros .svg de dentro de publico/; no se inserta nada");

            return '';
        }

        if (!is_file($this->proyecto->ruta($fichero))) {
            $this->avisos->registrar("svg('{$ruta}'): no existe {$fichero}; no se inserta nada");

            return '';
        }

        $svg = $this->proyecto->leerTexto($fichero);
        $svg = (string) preg_replace('/^\s*<\?xml[^>]*\?>\s*/i', '', $svg);
        $svg = (string) preg_replace('/^\s*<!DOCTYPE[^>]*>\s*/i', '', $svg);

        return $this->svgs[$relativa] = $svg;
    }

    /**
     * El ancho y el alto de una imagen de `publico/`, o null si no se pueden
     * leer (un SVG, un fichero que no es imagen). Si el fichero no existe o
     * la ruta sale de `publico/`, se avisa.
     *
     * @return array{ancho: int, alto: int}|null
     */
    public function dimensiones(string $ruta): ?array
    {
        $relativa = ltrim($ruta, '/');
        $this->registros?->anotar('publico', $relativa);
        $fichero = Proyecto::PUBLICO . "/{$relativa}";

        if ($this->proyecto === null) {
            return null;
        }

        if ($relativa === '' || str_contains($relativa, '\\') || preg_match('#(^|/)\.\.?(/|$)#', $relativa) === 1) {
            $this->avisos->registrar("dimensiones('{$ruta}'): solo se leen imágenes de dentro de publico/");

            return null;
        }

        if (!is_file($this->proyecto->ruta($fichero))) {
            $this->avisos->registrar("dimensiones('{$ruta}'): no existe {$fichero}");

            return null;
        }

        return Dimensiones::leer($this->proyecto->ruta($fichero));
    }

    /**
     * Si la página actual es esa URL o está dentro de ella. `/` solo es
     * activa en la portada.
     *
     * @param array<string, mixed> $contexto
     */
    public function activo(array $contexto, string $url): bool
    {
        $actual = $contexto['pagina']['url'] ?? null;

        if (!is_string($actual) || $url === '') {
            return false;
        }

        if ($actual === $url) {
            return true;
        }

        return $url !== '/' && str_starts_with($actual, str_ends_with($url, '/') ? $url : "{$url}/");
    }

    /**
     * `css('a.css')` declara ficheros y no escribe nada; `css()` marca dónde
     * irá todo el CSS de la página. Igual con `js`.
     *
     * @param 'css'|'js'    $tipo
     * @param list<string> $rutas
     */
    private function recurso(string $tipo, array $rutas): string
    {
        if ($rutas === []) {
            return $tipo === 'css' ? Recursos::MARCA_CSS : Recursos::MARCA_JS;
        }

        foreach ($rutas as $ruta) {
            $this->registros?->declarar($tipo, ltrim($ruta, '/'));
        }

        return '';
    }

    /**
     * @param list<Pagina> $paginas
     *
     * @return list<VistaDePagina>
     */
    private function vistas(array $paginas): array
    {
        return array_map($this->vistaDe(...), $paginas);
    }

    private function vista(?Pagina $pagina): ?VistaDePagina
    {
        return $pagina === null ? null : $this->vistaDe($pagina);
    }

    /**
     * @param iterable<mixed> $lista
     *
     * @return list<Pagina>
     */
    private function paginas(iterable $lista): array
    {
        $paginas = [];

        foreach ($lista as $elemento) {
            $paginas[] = match (true) {
                $elemento instanceof VistaDePagina => $elemento->paginaDeOrigen(),
                $elemento instanceof Pagina => $elemento,
                default => throw new \InvalidArgumentException(
                    'Los filtros de colección solo trabajan con páginas, como las que da coleccion()',
                ),
            };
        }

        return $paginas;
    }

    private function paginaORuta(VistaDePagina|string $pagina): Pagina|string
    {
        return $pagina instanceof VistaDePagina ? $pagina->paginaDeOrigen() : $pagina;
    }
}
