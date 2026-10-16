<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Apoyo;

/**
 * Un WebP con las medidas que se pidan: la cabecera `VP8X`, que es lo que lee
 * `getimagesize`. No hace falta GD para crearlo.
 */
final class Webp
{
    public static function de(int $ancho, int $alto): string
    {
        $medidas = substr(pack('V', $ancho - 1), 0, 3) . substr(pack('V', $alto - 1), 0, 3);
        $cabecera = "\x00\x00\x00\x00" . $medidas;

        return 'RIFF' . pack('V', 4 + 8 + strlen($cabecera)) . 'WEBP' . 'VP8X' . pack('V', strlen($cabecera)) . $cabecera;
    }
}
