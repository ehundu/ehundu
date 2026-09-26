<?php

declare(strict_types=1);

namespace Ehundu\Previsualizacion;

use Ehundu\Proyecto;

/**
 * Vigila los ficheros de un proyecto y dice si algo ha cambiado desde la
 * última vez que se preguntó. No mira `salida/` ni lo que empieza por punto.
 *
 * Compara la fecha de modificación y el tamaño de cada fichero: no necesita
 * extensiones ni procesos aparte, y en un proyecto de unos cientos de
 * ficheros tarda unos milisegundos.
 */
final class Vigilante
{
    private string $firma;

    public function __construct(
        private readonly Proyecto $proyecto,
    ) {
        $this->firma = $this->firmar();
    }

    /**
     * Si algo ha cambiado desde la última llamada (o desde que se creó).
     */
    public function haCambiado(): bool
    {
        $firma = $this->firmar();

        if ($firma === $this->firma) {
            return false;
        }

        $this->firma = $firma;

        return true;
    }

    private function firmar(): string
    {
        clearstatcache();

        $raiz = $this->proyecto->raiz;
        $filtro = function (\SplFileInfo $fichero) use ($raiz): bool {
            if (str_starts_with($fichero->getFilename(), '.')) {
                return false;
            }

            return str_replace('\\', '/', $fichero->getPathname()) !== $raiz . '/' . Proyecto::SALIDA;
        };

        $recorrido = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS),
            $filtro,
        ));

        $partes = [];

        foreach ($recorrido as $fichero) {
            $partes[] = $fichero->getPathname() . '|' . $fichero->getMTime() . '|' . $fichero->getSize();
        }

        sort($partes);

        return hash('xxh128', implode("\n", $partes));
    }
}
