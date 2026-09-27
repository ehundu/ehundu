<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Los filtros que se aplican a una colección (formato §6.2). Reciben una
 * lista de páginas y devuelven otra; no modifican la original.
 */
final class Coleccion
{
    public const string ORDEN_POR_DEFECTO = 'fecha desc';

    /**
     * Ordena por uno o varios campos: 'fecha desc', 'orden, titulo asc'. Las
     * páginas a las que les falta un campo van al final en las dos
     * direcciones; a igualdad, quedan en el orden que tenían. Además de los
     * campos, ve `url` y `ruta`, como las plantillas (Pagina::valor).
     *
     * @param list<Pagina> $paginas
     *
     * @return list<Pagina>
     *
     * @throws ErrorDeProyecto si el criterio no se entiende
     */
    public static function orden(array $paginas, string $criterio = self::ORDEN_POR_DEFECTO): array
    {
        $claves = self::claves($criterio);

        usort($paginas, function (Pagina $a, Pagina $b) use ($claves): int {
            foreach ($claves as [$campo, $descendente]) {
                $resultado = self::comparar($a->valor($campo), $b->valor($campo), $descendente);

                if ($resultado !== 0) {
                    return $resultado;
                }
            }

            return 0;
        });

        return $paginas;
    }

    /**
     * Las `$cuantas` primeras páginas.
     *
     * @param list<Pagina> $paginas
     *
     * @return list<Pagina>
     */
    public static function limite(array $paginas, int $cuantas): array
    {
        return array_slice($paginas, 0, max(0, $cuantas));
    }

    /**
     * @param list<Pagina> $paginas
     *
     * @return list<Pagina>
     */
    public static function invertir(array $paginas): array
    {
        return array_reverse($paginas);
    }

    /**
     * La colección sin una página, normalmente la actual.
     *
     * @param list<Pagina>  $paginas
     * @param Pagina|string $quitar  la página o su ruta dentro de `contenido/`
     *
     * @return list<Pagina>
     */
    public static function sin(array $paginas, Pagina|string $quitar): array
    {
        $ruta = self::ruta($quitar);

        return array_values(array_filter($paginas, fn (Pagina $pagina) => $pagina->ruta !== $ruta));
    }

    /**
     * Las páginas cuyo campo vale `$valor`; si el campo es una lista, las que
     * lo contienen. Como `orden`, ve también `url` y `ruta` (Pagina::valor).
     *
     * @param list<Pagina> $paginas
     *
     * @return list<Pagina>
     */
    public static function donde(array $paginas, string $campo, mixed $valor): array
    {
        $campo = Campos::ALIAS[$campo] ?? $campo;

        return array_values(array_filter($paginas, function (Pagina $pagina) use ($campo, $valor): bool {
            $actual = $pagina->valor($campo);

            return is_array($actual) && array_is_list($actual)
                ? array_any($actual, fn (mixed $elemento) => self::iguales($elemento, $valor))
                : self::iguales($actual, $valor);
        }));
    }

    /**
     * La página de antes de `$actual` en la lista, o null si es la primera o
     * no está en ella.
     *
     * @param list<Pagina> $paginas
     */
    public static function anterior(array $paginas, Pagina|string $actual): ?Pagina
    {
        $posicion = self::posicion($paginas, $actual);

        return $posicion === null || $posicion === 0 ? null : $paginas[$posicion - 1];
    }

    /**
     * La página de después de `$actual` en la lista, o null si es la última o
     * no está en ella.
     *
     * @param list<Pagina> $paginas
     */
    public static function siguiente(array $paginas, Pagina|string $actual): ?Pagina
    {
        $posicion = self::posicion($paginas, $actual);

        return $posicion === null ? null : ($paginas[$posicion + 1] ?? null);
    }

    /**
     * Compara dos valores de un campo para ordenar: los que faltan, al final.
     */
    public static function comparar(mixed $a, mixed $b, bool $descendente = false): int
    {
        if ($a === null || $b === null) {
            return ($a === null) <=> ($b === null);
        }

        $resultado = self::compararValores($a, $b);

        return $descendente ? -$resultado : $resultado;
    }

    /**
     * @return list<array{0: string, 1: bool}> campo y si es descendente
     */
    private static function claves(string $criterio): array
    {
        if (trim($criterio) === '') {
            $criterio = self::ORDEN_POR_DEFECTO;
        }

        $claves = [];

        foreach (explode(',', $criterio) as $parte) {
            $palabras = preg_split('/\s+/', trim($parte), -1, PREG_SPLIT_NO_EMPTY);
            $direccion = strtolower($palabras[1] ?? 'asc');

            if ($palabras === [] || count($palabras) > 2 || !in_array($direccion, ['asc', 'desc'], true)) {
                throw new ErrorDeProyecto("orden('{$criterio}'): cada criterio es un campo seguido, si acaso, de asc o desc");
            }

            $claves[] = [Campos::ALIAS[$palabras[0]] ?? $palabras[0], $direccion === 'desc'];
        }

        return $claves;
    }

    private static function compararValores(mixed $a, mixed $b): int
    {
        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
            return $a <=> $b;
        }

        if ($a instanceof \DateTimeInterface && $b instanceof \DateTimeInterface) {
            return $a <=> $b;
        }

        if (is_bool($a) && is_bool($b)) {
            return $a <=> $b;
        }

        return strcmp(self::claveDeTexto($a), self::claveDeTexto($b)) <=> 0;
    }

    /**
     * El texto tal como se ordena: sin mayúsculas ni tildes y con la «ñ»
     * después de la «n» (`{` va justo detrás de la `z` en ASCII).
     */
    private static function claveDeTexto(mixed $valor): string
    {
        $texto = match (true) {
            $valor instanceof \DateTimeInterface => $valor->format('Y-m-d H:i:s'),
            is_array($valor) => implode(', ', array_map(strval(...), array_filter($valor, is_scalar(...)))),
            is_bool($valor) => $valor ? '1' : '0',
            default => (string) $valor,
        };

        return Slug::sinTildes(str_replace(['ñ', "ñ"], 'n{', mb_strtolower($texto, 'UTF-8')));
    }

    private static function iguales(mixed $actual, mixed $buscado): bool
    {
        if ($actual === null || $buscado === null || is_bool($actual) || is_bool($buscado)) {
            return $actual === $buscado;
        }

        if ($actual instanceof \DateTimeInterface) {
            return $buscado instanceof \DateTimeInterface
                ? $actual == $buscado
                : $actual->format('Y-m-d') === (string) $buscado;
        }

        if (is_numeric($actual) && is_numeric($buscado)) {
            return (float) $actual === (float) $buscado;
        }

        return is_scalar($actual) && is_scalar($buscado) && (string) $actual === (string) $buscado;
    }

    /**
     * @param list<Pagina> $paginas
     */
    private static function posicion(array $paginas, Pagina|string $buscada): ?int
    {
        $ruta = self::ruta($buscada);

        foreach ($paginas as $posicion => $pagina) {
            if ($pagina->ruta === $ruta) {
                return $posicion;
            }
        }

        return null;
    }

    private static function ruta(Pagina|string $pagina): string
    {
        return $pagina instanceof Pagina ? $pagina->ruta : $pagina;
    }
}
