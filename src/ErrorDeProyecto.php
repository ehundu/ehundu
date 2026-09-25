<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Un error que impide compilar y que se debe al proyecto, no al motor: una
 * carpeta que no existe, un fichero ilegible, un YAML mal escrito.
 *
 * El mensaje va en español y está pensado para quien mantiene el sitio. El
 * fichero (relativo a la raíz del proyecto) y la línea van también por
 * separado, para que un programa que incruste el motor pueda señalarlos.
 */
final class ErrorDeProyecto extends \RuntimeException
{
    public function __construct(
        public readonly string $descripcion,
        public readonly ?string $fichero = null,
        public readonly ?int $linea = null,
        ?\Throwable $anterior = null,
    ) {
        parent::__construct(Aviso::conUbicacion($descripcion, $fichero, $linea), 0, $anterior);
    }
}
