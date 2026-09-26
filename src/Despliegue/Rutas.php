<?php

declare(strict_types=1);

namespace Ehundu\Despliegue;

/**
 * Rutas de un destino, siempre con barras normales. `dirname()` no sirve:
 * en Windows devuelve `\` para `/www`, y las rutas de un servidor no son
 * rutas de esta máquina.
 *
 * @internal
 */
final class Rutas
{
    /**
     * La carpeta que contiene una ruta: `blog` para `blog/index.html`, `/`
     * para `/www`, y vacío para `index.html`.
     */
    public static function carpeta(string $ruta): string
    {
        $posicion = strrpos($ruta, '/');

        return match ($posicion) {
            false => '',
            0 => '/',
            default => substr($ruta, 0, $posicion),
        };
    }
}
