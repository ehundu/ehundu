<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * El ancho y el alto de una imagen (formato §7.3 y §8.2), leídos de su
 * cabecera con `getimagesize`, que trae PHP sin extensiones: JPEG, PNG, GIF,
 * WebP y AVIF, entre otros. Un SVG no tiene dimensiones que se puedan leer
 * así, y tampoco un fichero que no es una imagen.
 */
final class Dimensiones
{
    /**
     * @param string $fichero ruta completa en disco
     *
     * @return array{ancho: int, alto: int}|null
     */
    public static function leer(string $fichero): ?array
    {
        $medidas = is_file($fichero) ? @getimagesize($fichero) : false;

        if ($medidas === false || $medidas[0] <= 0 || $medidas[1] <= 0) {
            return null;
        }

        return ['ancho' => $medidas[0], 'alto' => $medidas[1]];
    }
}
