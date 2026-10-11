<?php

declare(strict_types=1);

namespace Ehundu\Plantillas;

use Ehundu\Avisos;
use Ehundu\Coleccion;
use Ehundu\Colecciones;
use Ehundu\Dimensiones;
use Ehundu\Fecha;
use Ehundu\Idiomas;
use Ehundu\Pagina;
use Ehundu\Proyecto;
use Ehundu\Slug;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Lo que Ehundu añade a Twig (formato §5.2, §6, §7, §9 y §15): las funciones
 * `coleccion()`, `svg()`, `dimensiones()`, `activo()`, `css()` y `js()`, y los filtros `slug`, `fecha`, `markdown`,
 * `orden`, `limite`, `invertir`, `sin`, `donde`, `anterior` y `siguiente`.
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

    /** @var array<string, string|null> huellas ya calculadas, por ruta dentro de `publico/` */
    private array $versiones = [];

    /**
     * @param \Closure(Pagina): string|null $renderizar da el cuerpo de una página ya en HTML
     * @param Proyecto|null                 $proyecto   de donde lee `svg()`
     * @param \DateTimeZone|null            $zona       la del sitio, para `fecha`; UTC si falta
     * @param Idiomas                       $idiomas    los del sitio
     * @param \Closure(Pagina): list<array{nivel: int, texto: string, id: string}>|null $encabezados da los encabezados del
     *                                                  cuerpo de una página que han recibido `id`, y apunta que
     *                                                  quien los pide depende de ese cuerpo
     * @param \Closure(string, string, bool): string|null $convertirMarkdown convierte a HTML un texto en Markdown (de línea, o
     *                                                  de bloque si el tercer argumento es cierto), con el idioma de la
     *                                                  página; sin ella, el filtro `markdown` escapa el texto
     */
    public function __construct(
        private readonly Colecciones $colecciones,
        private readonly ?\Closure $renderizar = null,
        private readonly ?Proyecto $proyecto = null,
        private readonly Avisos $avisos = new Avisos(),
        private readonly \DateTimeZone $zona = new \DateTimeZone('UTC'),
        private readonly ?Registros $registros = null,
        private readonly Idiomas $idiomas = new Idiomas(),
        private readonly ?\Closure $encabezados = null,
        private readonly ?\Closure $convertirMarkdown = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('coleccion', $this->coleccion(...), ['needs_context' => true]),
            new TwigFunction('svg', $this->svg(...), ['is_safe' => ['html']]),
            new TwigFunction('dimensiones', $this->dimensiones(...)),
            new TwigFunction('activo', $this->activo(...), ['needs_context' => true]),
            new TwigFunction('css', fn (string ...$rutas) => $this->recurso('css', $rutas), ['is_safe' => ['html']]),
            new TwigFunction('js', fn (string ...$rutas) => $this->recurso('js', $rutas), ['is_safe' => ['html']]),
            new TwigFunction('css_fichero', fn (string ...$rutas) => $this->recursoEnFichero('css', $rutas), ['is_safe' => ['html']]),
            new TwigFunction('js_fichero', fn (string ...$rutas) => $this->recursoEnFichero('js', $rutas), ['is_safe' => ['html']]),
            new TwigFunction('version', $this->version(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('slug', fn (mixed $texto) => Slug::de((string) $texto)),
            new TwigFilter(
                'fecha',
                fn (array $contexto, mixed $valor, ?string $formato = null) => Fecha::formatear(
                    $valor,
                    $formato,
                    $this->zona,
                    $this->idiomaDe($contexto) ?? $this->idiomas->predeterminado(),
                ),
                ['needs_context' => true],
            ),
            new TwigFilter('markdown', $this->markdownDeCampo(...), ['needs_context' => true, 'is_safe' => ['html']]),
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
     * El filtro `markdown` (formato §7.3): el texto de un campo, en Markdown de
     * línea o, con `'bloque'`, de bloque, ya en HTML. Un valor vacío o que
     * falta no da nada; un número se toma como texto; cualquier otra cosa
     * avisa y no da nada. Un modo que no existe avisa y se usa el de línea.
     *
     * @param array<string, mixed> $contexto
     */
    public function markdownDeCampo(array $contexto, mixed $valor, string $modo = 'linea'): string
    {
        if (!in_array($modo, ['linea', 'bloque'], true)) {
            $this->avisos->registrar("markdown: el modo «{$modo}» no existe; vale «linea» o «bloque», y se usa «linea»");
            $modo = 'linea';
        }

        if ($valor === null || $valor === '') {
            return '';
        }

        if (is_int($valor) || is_float($valor)) {
            $valor = (string) $valor;
        }

        if (!is_string($valor)) {
            $es = match (true) {
                is_array($valor) => 'una lista o un mapa',
                is_bool($valor) => 'un sí o un no',
                $valor instanceof \DateTimeInterface => 'una fecha',
                default => 'otra cosa',
            };
            $this->avisos->registrar("markdown: se esperaba un texto y llega {$es}; no se pinta nada");

            return '';
        }

        if ($this->convertirMarkdown === null) {
            return htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        }

        return ($this->convertirMarkdown)($valor, $this->idiomaDe($contexto) ?? '', $modo === 'bloque');
    }

    /**
     * La vista de una página; siempre la misma para la misma página, para
     * que su contenido se convierta una sola vez.
     */
    public function vistaDe(Pagina $pagina): VistaDePagina
    {
        return $this->vistas[$pagina->ruta] ??= new VistaDePagina(
            $pagina,
            $this->renderizar,
            $this->traducciones(...),
            $this->markdown(...),
            $this->paginasDelGrupo(...),
            $this->encabezados,
        );
    }

    /**
     * Las páginas del grupo de una página generada por `agrupar`, en el orden
     * de su colección (formato §6.3). Quien las pide depende de los campos de
     * cada una: si cambian, se rehace. Que entre o salga una ya cambia la
     * página generada (`Pagina::$miembros`), y el contenido de una solo
     * cuenta si se pide, como el de cualquier página.
     *
     * @return list<VistaDePagina>
     */
    private function paginasDelGrupo(Pagina $pagina): array
    {
        $miembros = $this->colecciones->paginas($pagina->miembros ?? []);

        foreach ($miembros as $miembro) {
            $this->registros?->anotar('campos', $miembro->ruta);
        }

        return $this->vistas($miembros);
    }

    /**
     * El Markdown de una página sin convertir. Quien lo pide depende del
     * fichero de esa página, igual que con su contenido.
     */
    private function markdown(Pagina $pagina): string
    {
        $this->registros?->anotar('contenidos', $pagina->ruta);

        return $pagina->cuerpo;
    }

    /**
     * Las páginas de una colección en el idioma de la página que la pide, o
     * en el que se indique; con `todos`, en todos (formato §15.5).
     *
     * @param array<string, mixed> $contexto
     *
     * @return list<VistaDePagina>
     */
    public function coleccion(array $contexto, string $nombre, ?string $idioma = null): array
    {
        $this->registros?->anotar('colecciones', $nombre);

        if ($idioma === null) {
            $idioma = $this->idiomaDe($contexto);
        } elseif ($idioma !== Idiomas::TODOS && !$this->idiomas->declara($idioma)) {
            $this->avisos->registrar("coleccion('{$nombre}', '{$idioma}'): «{$idioma}» no es un idioma del sitio; la lista sale vacía");

            return [];
        }

        return $this->vistas($this->colecciones->coleccion($nombre, $idioma));
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
     * La dirección de un fichero de `publico/` con la huella de su contenido,
     * `/css/estilo.css?v=3fa9c1d2ab`, para que el navegador pueda guardarlo
     * sin servir uno viejo tras un cambio. Si no se puede leer, se avisa y se
     * da la dirección sin huella.
     */
    public function version(string $ruta): string
    {
        $relativa = ltrim($ruta, '/');
        $this->registros?->anotar('publico', $relativa);
        $fichero = Proyecto::PUBLICO . "/{$relativa}";

        if ($this->proyecto === null) {
            return "/{$relativa}";
        }

        if ($relativa === '' || str_contains($relativa, '\\') || preg_match('#(^|/)\.\.?(/|$)#', $relativa) === 1) {
            $this->avisos->registrar("version('{$ruta}'): solo se versionan ficheros de dentro de publico/");

            return "/{$relativa}";
        }

        if (!is_file($this->proyecto->ruta($fichero))) {
            $this->avisos->registrar("version('{$ruta}'): no existe {$fichero}");

            return "/{$relativa}";
        }

        $huella = $this->versiones[$relativa] ??= Recursos::huellaDeFichero($this->proyecto->ruta($fichero));

        return $huella === null ? "/{$relativa}" : "/{$relativa}?v={$huella}";
    }

    /**
     * Si la página actual es esa URL o está dentro de ella. `/`, y la raíz
     * de cada idioma (`/eu/`), solo son activas en su portada.
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

        $raices = array_map($this->idiomas->raiz(...), $this->idiomas->codigos());

        return !in_array($url, $raices, true) && str_starts_with($actual, str_ends_with($url, '/') ? $url : "{$url}/");
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
     * `css_fichero()` marca dónde irá la etiqueta que enlaza el paquete de CSS
     * de la página; igual con `js_fichero()`. No declara ficheros: eso lo hace
     * `css('…')`, y se avisa si alguien lo intenta aquí.
     *
     * @param 'css'|'js'    $tipo
     * @param list<string> $rutas
     */
    private function recursoEnFichero(string $tipo, array $rutas): string
    {
        if ($rutas !== []) {
            $this->avisos->registrar("{$tipo}_fichero() no lleva ficheros, y se ignoran; se declaran con {$tipo}('…')");
        }

        return $tipo === 'css' ? Recursos::MARCA_CSS_FICHERO : Recursos::MARCA_JS_FICHERO;
    }

    /**
     * Las versiones publicadas de una página, por idioma. Queda anotado:
     * si aparece, desaparece o cambia una traducción, la página se rehace.
     *
     * @return array<string, VistaDePagina>
     */
    private function traducciones(Pagina $pagina): array
    {
        $this->registros?->anotar('traducciones', $pagina->clave());

        return array_map($this->vistaDe(...), $this->colecciones->traducciones($pagina));
    }

    /**
     * El idioma de la página que se está construyendo, si se sabe.
     *
     * @param array<string, mixed> $contexto
     */
    private function idiomaDe(array $contexto): ?string
    {
        $pagina = $contexto['pagina'] ?? null;

        return $pagina instanceof VistaDePagina ? $pagina->paginaDeOrigen()->idioma : null;
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
