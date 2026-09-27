<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Las colecciones de un proyecto (formato §6). Cada etiqueta da una, con las
 * páginas que la llevan, fragmentos incluidos; `todo` reúne las páginas que
 * tienen URL. Los borradores, las páginas que aún no se publican y las de
 * `listada: no` no entran en ninguna.
 *
 * Las páginas van por fecha, de la más antigua a la más reciente, y a igual
 * fecha por ruta; las que no tienen fecha, al final (formato §6.1).
 *
 * Una colección tiene las páginas de todos los idiomas, y se puede pedir la
 * de uno solo (formato §15.5). Aparte, se sabe qué traducciones publicadas
 * tiene cada página, también las de `listada: no` (formato §15.8).
 */
final class Colecciones
{
    public const string TODO = 'todo';

    /** @var array<string, list<Pagina>> */
    private array $porNombre = [];

    /** @var array<string, array<string, list<Pagina>>> las de un solo idioma, por nombre e idioma */
    private array $porIdioma = [];

    /** @var array<string, array<string, Pagina>> páginas publicadas, por clave e idioma */
    private array $porClave = [];

    /**
     * @param list<Pagina> $paginas
     * @param bool         $conBorradores si los borradores y las páginas futuras cuentan como publicados
     * @param Idiomas      $idiomas       los del sitio, que dan el orden de las traducciones
     */
    public function __construct(
        array $paginas,
        \DateTimeImmutable $ahora,
        bool $conBorradores = false,
        private readonly Idiomas $idiomas = new Idiomas(),
    ) {
        usort($paginas, fn (Pagina $a, Pagina $b): int => Coleccion::comparar($a->campos['fecha'] ?? null, $b->campos['fecha'] ?? null)
            ?: strcmp($a->ruta, $b->ruta) <=> 0);

        foreach ($paginas as $pagina) {
            if (!$pagina->estaPublicada($ahora, $conBorradores)) {
                continue;
            }

            $this->porClave[$pagina->clave()][$pagina->idioma] = $pagina;

            if (($pagina->campos['listada'] ?? true) === false) {
                continue;
            }

            if ($pagina->url !== false) {
                $this->porNombre[self::TODO][] = $pagina;
            }

            foreach ($pagina->campos['etiquetas'] ?? [] as $etiqueta) {
                if ($etiqueta !== self::TODO) {
                    $this->porNombre[$etiqueta][] = $pagina;
                }
            }
        }
    }

    /**
     * Las páginas de una colección. Una colección que no existe está vacía.
     *
     * @param string|null $idioma el código de un idioma para tener solo sus páginas;
     *                            null o `todos`, las de todos
     *
     * @return list<Pagina>
     */
    public function coleccion(string $nombre, ?string $idioma = null): array
    {
        $paginas = $this->porNombre[$nombre] ?? [];

        if ($idioma === null || $idioma === Idiomas::TODOS) {
            return $paginas;
        }

        return $this->porIdioma[$nombre][$idioma] ??= array_values(array_filter(
            $paginas,
            fn (Pagina $pagina) => $pagina->idioma === $idioma,
        ));
    }

    /**
     * Las versiones publicadas de una página, ella incluida si lo está, por
     * código y en el orden de los idiomas del sitio.
     *
     * @return array<string, Pagina>
     */
    public function traducciones(Pagina $pagina): array
    {
        $versiones = $this->porClave[$pagina->clave()] ?? [];

        return array_filter(
            array_merge(array_fill_keys($this->idiomas->codigos(), null), $versiones),
            fn (?Pagina $version) => $version !== null,
        );
    }
}
