<?php

declare(strict_types=1);

namespace Ehundu\Despliegue;

/**
 * Lo que Ehundu ha subido a un destino: la ruta, el MD5 y el tamaño de cada
 * fichero, y nada más. Vive en el propio destino, en `.ehundu.json`, para que
 * cualquier máquina que despliegue el sitio sepa qué hay allí sin tener que
 * listarlo, y qué puede borrar: solo lo que subió Ehundu.
 *
 * El MD5 es el mismo resumen que S3 usa para comprobar lo que recibe.
 */
final class Manifiesto
{
    public const string FICHERO = '.ehundu.json';

    private const int VERSION = 1;

    /**
     * @param array<string, array{md5: string, tamano: int}> $ficheros por ruta
     */
    public function __construct(
        private array $ficheros = [],
    ) {
    }

    /**
     * @throws \UnexpectedValueException si no es un manifiesto de Ehundu
     */
    public static function leer(string $json): self
    {
        $datos = json_decode($json, true);

        if (!is_array($datos) || ($datos['ehundu'] ?? null) !== self::VERSION || !is_array($datos['ficheros'] ?? null)) {
            throw new \UnexpectedValueException('no es un manifiesto de Ehundu que se pueda leer');
        }

        $ficheros = [];

        foreach ($datos['ficheros'] as $ruta => $fichero) {
            if (!is_array($fichero) || !is_string($fichero['md5'] ?? null) || !is_int($fichero['tamano'] ?? null)) {
                throw new \UnexpectedValueException("la entrada de «{$ruta}» no se entiende");
            }

            $ficheros[(string) $ruta] = ['md5' => $fichero['md5'], 'tamano' => $fichero['tamano']];
        }

        return new self($ficheros);
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

        return json_encode(
            ['ehundu' => self::VERSION, 'ficheros' => (object) $ficheros],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ) . "\n";
    }
}
