<?php

declare(strict_types=1);

namespace Ehundu\Despliegue;

use Ehundu\ErrorDeProyecto;
use Ehundu\Proyecto;

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
     * @param string|null                                          $url        la del sitio, que queda en el
     *                                                                         manifiesto; sin ella, se queda
     *                                                                         la que tuviera
     */
    public function __construct(
        public array $subidas,
        public array $borrados,
        public int $sinCambios,
        public ?string $url = null,
    ) {
    }

    /**
     * Qué hay que hacer en un destino para que quede como la `salida/` de un proyecto ya compilado: lo que
     * `trazar` decide con los ficheros de `salida/`, que aquí lee quien llama sin calcular los MD5.
     *
     * @param Manifiesto|null $anterior el del destino, si lo hay
     * @param bool            $todo     si se sube todo aunque no haya cambiado
     * @param string|null     $url      la del sitio que se despliega
     *
     * @throws ErrorDeProyecto si `salida/` tiene un fichero que se llama como el manifiesto
     */
    public static function deSalida(Proyecto $proyecto, ?Manifiesto $anterior = null, bool $todo = false, ?string $url = null): self
    {
        return self::trazar(self::ficheros($proyecto), $anterior, $todo, $url);
    }

    /**
     * Los ficheros de `salida/`, con su MD5 y su tamaño, por ruta: lo que compara el despliegue con el manifiesto.
     *
     * @return array<string, array{md5: string, tamano: int}>
     *
     * @throws ErrorDeProyecto si `salida/` tiene un fichero que se llama como el manifiesto: ese nombre lo usa
     *                         Ehundu para saber qué ha subido
     */
    public static function ficheros(Proyecto $proyecto): array
    {
        $ficheros = [];

        foreach ($proyecto->ficherosDe(Proyecto::SALIDA) as $ruta) {
            if ($ruta === Manifiesto::FICHERO) {
                throw new ErrorDeProyecto(
                    'El sitio tiene un fichero ' . Manifiesto::FICHERO . ' en la raíz; ese nombre lo usa Ehundu para saber qué ha subido. Cámbiale el nombre',
                );
            }

            $completa = $proyecto->ruta(Proyecto::SALIDA, $ruta);
            $ficheros[$ruta] = ['md5' => (string) md5_file($completa), 'tamano' => (int) filesize($completa)];
        }

        return $ficheros;
    }

    /**
     * @param array<string, array{md5: string, tamano: int}> $ficheros los de `salida/`, por ruta
     * @param Manifiesto|null                                $anterior el del destino, si lo hay
     * @param bool                                           $todo     si se sube todo aunque no haya cambiado
     * @param string|null                                    $url      la del sitio que se despliega
     */
    public static function trazar(array $ficheros, ?Manifiesto $anterior, bool $todo = false, ?string $url = null): self
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

        return new self($subidas, $borrados, $sinCambios, $url);
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
            in_array(basename($ruta), ['sitemap.xml', 'feed.xml'], true) => 2,
            in_array(strtolower(pathinfo($ruta, PATHINFO_EXTENSION)), ['html', 'htm'], true) => 1,
            default => 0,
        };
    }
}
