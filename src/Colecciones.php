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
 */
final class Colecciones
{
    public const string TODO = 'todo';

    /** @var array<string, list<Pagina>> */
    private array $porNombre = [];

    /**
     * @param list<Pagina> $paginas
     * @param bool         $conBorradores si los borradores y las páginas futuras cuentan como publicados
     */
    public function __construct(array $paginas, \DateTimeImmutable $ahora, bool $conBorradores = false)
    {
        usort($paginas, fn (Pagina $a, Pagina $b): int => Coleccion::comparar($a->campos['fecha'] ?? null, $b->campos['fecha'] ?? null)
            ?: strcmp($a->ruta, $b->ruta) <=> 0);

        foreach ($paginas as $pagina) {
            if (!$pagina->estaPublicada($ahora, $conBorradores) || ($pagina->campos['listada'] ?? true) === false) {
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
     * @param string|null $idioma reservado para el multiidioma (formato §15.5); de momento no cambia nada
     *
     * @return list<Pagina>
     */
    public function coleccion(string $nombre, ?string $idioma = null): array
    {
        return $this->porNombre[$nombre] ?? [];
    }
}
