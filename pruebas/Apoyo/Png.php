<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Apoyo;

/**
 * Un PNG con las medidas que se pidan: la firma y la cabecera, que es lo que
 * lee `getimagesize`. No hace falta GD para crearlo.
 */
final class Png
{
    public static function de(int $ancho, int $alto): string
    {
        $datos = 'IHDR' . pack('NN', $ancho, $alto) . "\x08\x02\x00\x00\x00";

        return "\x89PNG\r\n\x1a\n" . pack('N', 13) . $datos . pack('N', crc32($datos));
    }
}
