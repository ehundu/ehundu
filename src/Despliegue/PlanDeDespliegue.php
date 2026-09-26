<?php

declare(strict_types=1);

namespace Ehundu\Despliegue;

/**
 * Qué hay que hacer en un destino para que quede como `salida/`: qué subir y
 * qué borrar. Se decide antes de tocar nada, así que sirve también para
 * simular, y se puede ejecutar por partes.
 *
 * Se sube lo que es nuevo o ha cambiado respecto al manifiesto: primero los
 * ficheros (CSS, imágenes, documentos), después las páginas y al final el
 * sitemap y el feed, para que una página nueva no enlace algo que aún no
 * está. Se borra solo lo que subió Ehundu y ya no se genera; lo que no está
 * en el manifiesto no se toca nunca.
 */
final readonly class PlanDeDespliegue
{
    /**
     * @param list<array{ruta: string, md5: string, tamano: int}> $subidas    en el orden en que se suben
     * @param list<string>                                         $borrados
     * @param int                                                  $sinCambios ficheros que ya están como deben
     */
    public function __construct(
        public array $subidas,
        public array $borrados,
        public int $sinCambios,
    ) {
    }

    /**
     * @param array<string, array{md5: string, tamano: int}> $ficheros los de `salida/`, por ruta
     * @param Manifiesto|null                                $anterior el del destino, si lo hay
     * @param bool                                           $todo     si se sube todo aunque no haya cambiado
     */
    public static function trazar(array $ficheros, ?Manifiesto $anterior, bool $todo = false): self
    {
        $subidos = $anterior?->ficheros() ?? [];
        $subidas = [];
        $sinCambios = 0;

        foreach ($ficheros as $ruta => $fichero) {
            $ruta = (string) $ruta;

            if (!$todo && ($subidos[$ruta] ?? null) === $fichero) {
                $sinCambios++;

                continue;
            }

            $subidas[] = ['ruta' => $ruta, ...$fichero];
        }

        usort($subidas, fn (array $a, array $b) => [self::turno($a['ruta']), $a['ruta']] <=> [self::turno($b['ruta']), $b['ruta']]);

        $borrados = array_values(array_filter(
            array_map('strval', array_keys($subidos)),
            fn (string $ruta) => !isset($ficheros[$ruta]),
        ));
        sort($borrados, SORT_STRING);

        return new self($subidas, $borrados, $sinCambios);
    }

    public function bytes(): int
    {
        return array_sum(array_column($this->subidas, 'tamano'));
    }

    public function estaVacio(): bool
    {
        return $this->subidas === [] && $this->borrados === [];
    }

    /**
     * Las carpetas que quedan sin nada de Ehundu después de borrar, de la más
     * honda a la menos: se intentan quitar, y si tienen algo más, se quedan.
     *
     * @param array<string, mixed> $quedan los ficheros que siguen en el destino, por ruta
     *
     * @return list<string>
     */
    public function carpetasQueSobran(array $quedan): array
    {
        $ocupadas = [];

        foreach (array_keys($quedan) as $ruta) {
            for ($carpeta = Rutas::carpeta((string) $ruta); $carpeta !== '' && $carpeta !== '/'; $carpeta = Rutas::carpeta($carpeta)) {
                $ocupadas[$carpeta] = true;
            }
        }

        $sobran = [];

        foreach ($this->borrados as $ruta) {
            for ($carpeta = Rutas::carpeta($ruta); $carpeta !== '' && $carpeta !== '/'; $carpeta = Rutas::carpeta($carpeta)) {
                if (!isset($ocupadas[$carpeta])) {
                    $sobran[$carpeta] = substr_count($carpeta, '/');
                }
            }
        }

        arsort($sobran);

        return array_map('strval', array_keys($sobran));
    }

    private static function turno(string $ruta): int
    {
        return match (true) {
            $ruta === 'sitemap.xml' || $ruta === 'feed.xml' => 2,
            in_array(strtolower(pathinfo($ruta, PATHINFO_EXTENSION)), ['html', 'htm'], true) => 1,
            default => 0,
        };
    }
}
