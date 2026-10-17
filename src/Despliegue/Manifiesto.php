<?php

declare(strict_types=1);

namespace Ehundu\Despliegue;

/**
 * Lo que Ehundu ha subido a un destino: la `url` del sitio y la ruta, el MD5
 * y el tamaño de cada fichero, y nada más. Vive en el propio destino, en
 * `.ehundu.json`, para que cualquier máquina que despliegue el sitio sepa qué
 * hay allí sin tener que listarlo, y qué puede borrar: solo lo que subió
 * Ehundu. La `url` dice de qué sitio es el destino, para no desplegar uno
 * encima de otro.
 *
 * El MD5 es el mismo resumen que S3 usa para comprobar lo que recibe.
 */
final class Manifiesto
{
    public const string FICHERO = '.ehundu.json';

    private const int VERSION = 1;

    /**
     * @param array<string, array{md5: string, tamano: int}> $ficheros por ruta
     * @param string|null                                    $url      la del sitio; no la llevan los
     *                                                                 manifiestos de Ehundu 0.1
     */
    public function __construct(
        private array $ficheros = [],
        public readonly ?string $url = null,
    ) {
    }

    /**
     * @throws \UnexpectedValueException si no es un manifiesto de Ehundu, o si alguna ruta no es de dentro del sitio
     */
    public static function leer(string $json): self
    {
        $datos = json_decode($json, true);

        if (!is_array($datos) || ($datos['ehundu'] ?? null) !== self::VERSION || !is_array($datos['ficheros'] ?? null)) {
            throw new \UnexpectedValueException('no es un manifiesto de Ehundu que se pueda leer');
        }

        $url = $datos['url'] ?? null;

        if ($url !== null && (!is_string($url) || $url === '')) {
            throw new \UnexpectedValueException('tiene una «url» que no se entiende');
        }

        $ficheros = [];

        foreach ($datos['ficheros'] as $ruta => $fichero) {
            // El manifiesto vive en el destino: quien pueda escribirlo allí no puede hacer que Ehundu borre otra cosa
            if (!self::esRutaDelSitio((string) $ruta)) {
                throw new \UnexpectedValueException("la ruta «{$ruta}» no es una ruta de dentro del sitio");
            }

            if (!is_array($fichero) || !is_string($fichero['md5'] ?? null) || !is_int($fichero['tamano'] ?? null)) {
                throw new \UnexpectedValueException("la entrada de «{$ruta}» no se entiende");
            }

            $ficheros[(string) $ruta] = ['md5' => $fichero['md5'], 'tamano' => $fichero['tamano']];
        }

        return new self($ficheros, $url);
    }

    /**
     * Si una ruta es relativa y no sale del sitio: sin barra al principio ni barras invertidas, sin letra de unidad
     * (`C:`), sin bytes nulos y sin tramos vacíos, `.` ni `..`. Las rutas que escribe Ehundu siempre lo son.
     */
    private static function esRutaDelSitio(string $ruta): bool
    {
        if ($ruta === '' || str_contains($ruta, "\0") || str_contains($ruta, '\\') || str_starts_with($ruta, '/')) {
            return false;
        }

        $tramos = explode('/', $ruta);

        if (preg_match('/^[A-Za-z]:$/', $tramos[0]) === 1) {
            return false;
        }

        foreach ($tramos as $tramo) {
            if ($tramo === '' || $tramo === '.' || $tramo === '..') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, array{md5: string, tamano: int}>
     */
    public function ficheros(): array
    {
        return $this->ficheros;
    }

    public function anotar(string $ruta, string $md5, int $tamano): void
    {
        $this->ficheros[$ruta] = ['md5' => $md5, 'tamano' => $tamano];
    }

    public function quitar(string $ruta): void
    {
        unset($this->ficheros[$ruta]);
    }

    public function json(): string
    {
        $ficheros = $this->ficheros;
        ksort($ficheros, SORT_STRING);
        $url = $this->url !== null ? ['url' => $this->url] : [];

        return json_encode(
            ['ehundu' => self::VERSION, ...$url, 'ficheros' => (object) $ficheros],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ) . "\n";
    }
}
