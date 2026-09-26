<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Lo que va a cada fichero de `salida/`: una página, un fichero de
 * `publico/` o uno de `contenido/`. Si dos cosas van al mismo sitio es un
 * error; las mayúsculas no cuentan, porque en Windows y en macOS `Foto.jpg`
 * y `foto.jpg` son el mismo fichero.
 *
 * @internal
 */
final class Destinos
{
    /** @var array<string, string> origen de cada destino, por destino en minúsculas */
    private array $origenes = [];

    /**
     * @param string $destino ruta relativa a `salida/`
     * @param string $origen  ruta relativa a la raíz del proyecto
     *
     * @throws ErrorDeProyecto si el destino ya está ocupado
     */
    public function anotar(string $destino, string $origen): void
    {
        $clave = mb_strtolower($destino, 'UTF-8');

        if (isset($this->origenes[$clave])) {
            throw new ErrorDeProyecto(
                "Dos ficheros van al mismo sitio de salida/, {$destino}: {$this->origenes[$clave]} y {$origen}",
                $origen,
            );
        }

        $this->origenes[$clave] = $origen;
    }

    public function ocupado(string $destino): bool
    {
        return isset($this->origenes[mb_strtolower($destino, 'UTF-8')]);
    }
}
