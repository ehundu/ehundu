<?php

declare(strict_types=1);

namespace Ehundu\Previsualizacion;

use Ehundu\Huellas;
use Ehundu\Proyecto;

/**
 * Vigila los ficheros de un proyecto y dice si algo ha cambiado desde la
 * última vez que se preguntó. No mira `salida/` ni lo que empieza por punto.
 *
 * Compara la huella de cada fichero (ver `Huellas`): no necesita extensiones
 * ni procesos aparte, y en un proyecto de unos cientos de ficheros tarda
 * unos milisegundos.
 */
final class Vigilante
{
    private Huellas $huellas;

    /** @var array<string, string> */
    private array $anteriores;

    public function __construct(
        private readonly Proyecto $proyecto,
    ) {
        $this->huellas = new Huellas($proyecto);
        $this->anteriores = $this->tomar();
    }

    /**
     * Si algo ha cambiado desde la última llamada (o desde que se creó).
     */
    public function haCambiado(): bool
    {
        $huellas = $this->tomar();

        if ($huellas === $this->anteriores) {
            return false;
        }

        $this->anteriores = $huellas;

        return true;
    }

    /**
     * @return array<string, string>
     */
    private function tomar(): array
    {
        $entradas = array_filter(
            scandir($this->proyecto->raiz) ?: [],
            fn (string $nombre) => !str_starts_with($nombre, '.') && $nombre !== Proyecto::SALIDA,
        );

        return $this->huellas->tomar(array_values($entradas));
    }
}
