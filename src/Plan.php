<?php

declare(strict_types=1);

namespace Ehundu;

use Ehundu\Plantillas\Maquetador;
use Ehundu\Plantillas\Registro;

/**
 * Qué se puede aprovechar de la construcción anterior. Se decide antes de
 * construir nada, comparando lo que se leyó entonces con lo que se lee ahora
 * y lo que usó cada página y cada cuerpo (ver `Registro`).
 *
 * Una página o un cuerpo se rehacen si ha cambiado su fichero, o si han
 * cambiado una plantilla o un parcial que usaron, un fichero de `publico/`
 * que miraron o incrustaron, una colección que recorrieron o el contenido de
 * otra página que mostraron. Una colección cambia cuando entra, sale o cambia
 * los campos alguna página con su etiqueta. Si cambian `sitio.yml` o
 * `datos/`, que llegan a todas las plantillas, se rehace todo. Ante la duda,
 * también: el resultado tiene que ser siempre el de una construcción
 * completa.
 *
 * @internal
 */
final readonly class Plan
{
    /** Lo que se vigila con huellas; el contenido, los datos y `sitio.yml` se comparan ya leídos. */
    public const array VIGILADAS = [Proyecto::PLANTILLAS, Proyecto::PARCIALES, Proyecto::PUBLICO];

    /**
     * @param string|null  $completa por qué se rehace todo, o null si se aprovecha la construcción anterior
     * @param list<string> $paginas  rutas de las páginas cuyo HTML se aprovecha
     * @param list<string> $cuerpos  rutas de las páginas cuyo cuerpo convertido se aprovecha
     */
    public function __construct(
        public ?string $completa,
        public array $paginas = [],
        public array $cuerpos = [],
    ) {
    }

    /**
     * @param array<string, string> $huellas    las de `VIGILADAS`
     * @param array<string, true>   $publicadas rutas de las páginas publicadas ahora
     */
    public static function trazar(
        ?Memoria $anterior,
        Proyecto $proyecto,
        bool $conBorradores,
        Lectura $lectura,
        array $huellas,
        array $publicadas,
    ): self {
        $completa = match (true) {
            $anterior === null => 'no hay una construcción anterior',
            $anterior->raiz !== $proyecto->raiz => 'es otro proyecto',
            $anterior->conBorradores !== $conBorradores => 'cambia si se incluyen los borradores',
            serialize($anterior->lectura->sitio) !== serialize($lectura->sitio) => 'ha cambiado sitio.yml',
            serialize($anterior->lectura->datos) !== serialize($lectura->datos) => 'han cambiado los datos',
            default => null,
        };

        if ($anterior === null || $completa !== null) {
            return new self($completa);
        }

        $antes = self::porRuta($anterior->lectura->paginas);
        $ahora = self::porRuta($lectura->paginas);

        // Páginas nuevas, borradas o con cualquier cambio en su fichero, y de
        // ellas las que cambian alguna colección: las que entran, salen o
        // cambian de campos o de URL.
        $cambiadas = [];
        $conOtrosCampos = [];

        foreach (array_keys($antes + $ahora) as $ruta) {
            $vieja = $antes[$ruta] ?? null;
            $nueva = $ahora[$ruta] ?? null;

            if ($vieja === null || $nueva === null || serialize($vieja) !== serialize($nueva)) {
                $cambiadas[$ruta] = true;
            }

            if (
                $vieja === null || $nueva === null
                || isset($anterior->publicadas[$ruta]) !== isset($publicadas[$ruta])
                || serialize([$vieja->campos, $vieja->url]) !== serialize([$nueva->campos, $nueva->url])
            ) {
                $conOtrosCampos[$ruta] = true;
            }
        }

        $colecciones = [];

        if ($conOtrosCampos !== []) {
            $colecciones[Colecciones::TODO] = true;

            foreach (array_keys($conOtrosCampos) as $ruta) {
                foreach ([$antes[$ruta] ?? null, $ahora[$ruta] ?? null] as $pagina) {
                    foreach ($pagina?->campos['etiquetas'] ?? [] as $etiqueta) {
                        $colecciones[(string) $etiqueta] = true;
                    }
                }
            }
        }

        $plantillas = [];
        $publico = [];

        foreach (array_keys($anterior->huellas + $huellas) as $fichero) {
            $fichero = (string) $fichero;

            if (($anterior->huellas[$fichero] ?? null) === ($huellas[$fichero] ?? null)) {
                continue;
            }

            if (str_starts_with($fichero, Proyecto::PUBLICO . '/')) {
                $publico[self::normalizar(substr($fichero, strlen(Proyecto::PUBLICO) + 1))] = true;
            } else {
                $plantillas[self::normalizar($fichero)] = true;
            }
        }

        if (self::atajos($anterior->huellas) !== self::atajos($huellas)) {
            $plantillas[Maquetador::ATAJOS] = true;
        }

        $vale = function (Registro $registro) use ($cambiadas, $colecciones, $plantillas, $publico): bool {
            foreach (array_keys($registro->plantillas) as $nombre) {
                if (isset($plantillas[self::normalizar((string) $nombre)])) {
                    return false;
                }
            }

            foreach (array_keys($registro->publico) as $ruta) {
                $ruta = self::normalizar((string) $ruta);

                // Algo fuera de publico/ no tiene huella: no se puede saber si ha cambiado.
                if ($ruta === null || isset($publico[$ruta])) {
                    return false;
                }
            }

            foreach (array_keys($registro->colecciones) as $nombre) {
                if (isset($colecciones[$nombre])) {
                    return false;
                }
            }

            foreach (array_keys($registro->contenidos) as $ruta) {
                if (isset($cambiadas[$ruta])) {
                    return false;
                }
            }

            return true;
        };

        $cuerpos = [];

        foreach ($anterior->cuerpos as $ruta => $cuerpo) {
            $ruta = (string) $ruta;

            if (isset($ahora[$ruta]) && !isset($cambiadas[$ruta]) && $vale($cuerpo['registro'])) {
                $cuerpos[] = $ruta;
            }
        }

        $paginas = [];

        foreach ($anterior->paginas as $ruta => $pagina) {
            $ruta = (string) $ruta;

            if (isset($ahora[$ruta], $publicadas[$ruta]) && !isset($cambiadas[$ruta]) && $vale($pagina['registro'])) {
                $paginas[] = $ruta;
            }
        }

        return new self(null, $paginas, $cuerpos);
    }

    /**
     * @param list<Pagina> $paginas
     *
     * @return array<string, Pagina>
     */
    private static function porRuta(array $paginas): array
    {
        $porRuta = [];

        foreach ($paginas as $pagina) {
            $porRuta[$pagina->ruta] = $pagina;
        }

        return $porRuta;
    }

    /**
     * Los ficheros que pueden ser atajos del sitio: si aparece o desaparece
     * uno, cambia cómo se lee cualquier Markdown.
     *
     * @param array<string, string> $huellas
     *
     * @return list<string>
     */
    private static function atajos(array $huellas): array
    {
        return array_values(array_filter(
            array_map('strval', array_keys($huellas)),
            fn (string $fichero) => preg_match('#^' . preg_quote(Plantillas\Atajos::CARPETA, '#') . '/[^/]+\.twig$#i', $fichero) === 1,
        ));
    }

    /**
     * Una ruta tal como la vería el sistema de ficheros, para comparar lo que
     * piden las plantillas con lo que hay en disco: sin distinguir mayúsculas
     * (Windows no las distingue), con barras normales y sin «.». Si sube con
     * «..», null.
     */
    private static function normalizar(string $ruta): ?string
    {
        $partes = [];

        foreach (preg_split('#[\\\\/]+#', mb_strtolower($ruta), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $parte) {
            if ($parte === '..') {
                return null;
            }

            if ($parte !== '.') {
                $partes[] = $parte;
            }
        }

        return implode('/', $partes);
    }
}
