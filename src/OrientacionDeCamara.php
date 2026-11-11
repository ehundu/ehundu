<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * La orientación que anota la cámara en un JPEG (EXIF, etiqueta 0x0112) y el giro que la deshace
 * (formato §9.2).
 *
 * La lee esta clase y no la extensión `exif`, que no está en todas partes: busca el segmento de la
 * cámara al principio del fichero y, dentro, la entrada de la orientación. Una cabecera que no se
 * entiende da 1, que es dejar la imagen como está.
 *
 * @internal
 */
final class OrientacionDeCamara
{
    /**
     * Cómo hay que girar el JPEG para verlo derecho (1 a 8). Sin dato, o con uno que no se entiende, 1.
     */
    public static function de(string $fichero): int
    {
        // Los datos de la cámara están al principio del fichero, en un segmento de 64 KB como mucho
        $inicio = (string) @file_get_contents($fichero, false, null, 0, 65_545);

        if (strlen($inicio) < 12 || !str_starts_with($inicio, "\xFF\xD8")) {
            return 1;
        }

        $posicion = 2;
        $largo = strlen($inicio);

        while ($posicion + 4 <= $largo && $inicio[$posicion] === "\xFF") {
            $marca = ord($inicio[$posicion + 1]);

            if ($marca === 0xFF) {
                $posicion++;

                continue;
            }

            // El inicio de los datos de la imagen: ya no hay más segmentos
            if ($marca === 0xDA || $marca === 0xD9) {
                return 1;
            }

            $tamano = (ord($inicio[$posicion + 2]) << 8) | ord($inicio[$posicion + 3]);

            if ($tamano < 2) {
                return 1;
            }

            if ($marca === 0xE1 && substr($inicio, $posicion + 4, 6) === "Exif\0\0") {
                return self::orientacionDelTiff(substr($inicio, $posicion + 10, $tamano - 8));
            }

            $posicion += 2 + $tamano;
        }

        return 1;
    }

    /**
     * Si con esa orientación lo que se ve tiene el ancho y el alto cambiados (cámara girada de lado, 5 a 8).
     */
    public static function deLado(int $orientacion): bool
    {
        return $orientacion >= 5;
    }

    /**
     * Gira o voltea una imagen para que se vea derecha.
     */
    public static function girar(\GdImage $imagen, int $orientacion): \GdImage
    {
        switch ($orientacion) {
            case 2:
                imageflip($imagen, IMG_FLIP_HORIZONTAL);

                return $imagen;
            case 3:
                return self::rotada($imagen, 180);
            case 4:
                imageflip($imagen, IMG_FLIP_VERTICAL);

                return $imagen;
            case 5:
                $imagen = self::rotada($imagen, -90);
                imageflip($imagen, IMG_FLIP_HORIZONTAL);

                return $imagen;
            case 6:
                return self::rotada($imagen, -90);
            case 7:
                $imagen = self::rotada($imagen, -90);
                imageflip($imagen, IMG_FLIP_VERTICAL);

                return $imagen;
            case 8:
                return self::rotada($imagen, 90);
            default:
                return $imagen;
        }
    }

    /** La orientación dentro de los datos TIFF de un segmento EXIF */
    private static function orientacionDelTiff(string $tiff): int
    {
        if (strlen($tiff) < 14) {
            return 1;
        }

        $orden = substr($tiff, 0, 2);

        if ($orden !== 'II' && $orden !== 'MM') {
            return 1;
        }

        $corto = $orden === 'II' ? 'v' : 'n';
        $largo = $orden === 'II' ? 'V' : 'N';

        $entero = static function (string $formato, int $desde, int $bytes) use ($tiff): ?int {
            if ($desde < 0 || $desde + $bytes > strlen($tiff)) {
                return null;
            }

            $valor = unpack($formato, substr($tiff, $desde, $bytes));

            return is_array($valor) ? (int) $valor[1] : null;
        };

        $primera = $entero($largo, 4, 4);
        $cuantas = $primera === null ? null : $entero($corto, $primera, 2);

        if ($primera === null || $cuantas === null || $cuantas > 512) {
            return 1;
        }

        for ($i = 0; $i < $cuantas; $i++) {
            $entrada = $primera + 2 + $i * 12;

            // La etiqueta 0x0112 es la orientación: un SHORT, con su valor dentro de la propia entrada
            if ($entero($corto, $entrada, 2) === 0x0112) {
                $valor = $entero($corto, $entrada + 8, 2);

                return $valor !== null && $valor >= 1 && $valor <= 8 ? $valor : 1;
            }
        }

        return 1;
    }

    /** `imagerotate` gira hacia la izquierda: un ángulo negativo es hacia la derecha */
    private static function rotada(\GdImage $imagen, int $grados): \GdImage
    {
        $girada = imagerotate($imagen, $grados, 0);

        return $girada === false ? $imagen : $girada;
    }
}
