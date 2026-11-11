<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Apoyo;

/**
 * Imágenes de verdad hechas con GD, para las pruebas que las leen y las reducen. Las que solo
 * necesitan unas medidas se hacen con `Png` y `Webp`, sin GD.
 */
final class ImagenGd
{
    /**
     * Si esta instalación de PHP puede hacer y leer lo que usan las pruebas: JPEG, PNG y WebP.
     */
    public static function disponible(): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }

        $gd = gd_info();

        return ($gd['JPEG Support'] ?? false) && ($gd['PNG Support'] ?? false) && ($gd['WebP Support'] ?? false);
    }

    /**
     * Si GD escribe AVIF.
     */
    public static function sabeAvif(): bool
    {
        return extension_loaded('gd') && (gd_info()['AVIF Support'] ?? false) && function_exists('imageavif');
    }

    /**
     * Un JPEG con un degradado. Con una orientación distinta de 1 lleva el segmento EXIF de la cámara que la
     * anota; los píxeles no cambian: es quien lo lee quien tiene que girarlo.
     */
    public static function jpeg(int $ancho, int $alto, int $orientacion = 1): string
    {
        $imagen = imagecreatetruecolor($ancho, $alto);

        for ($y = 0; $y < $alto; $y++) {
            imageline($imagen, 0, $y, $ancho - 1, $y, (int) imagecolorallocate($imagen, intdiv($y * 255, max(1, $alto)), 120, 200));
        }

        ob_start();
        imagejpeg($imagen, null, 90);
        $jpeg = (string) ob_get_clean();

        if ($orientacion === 1) {
            return $jpeg;
        }

        $tiff = 'II' . pack('v', 42) . pack('V', 8) . pack('v', 1)
            . pack('v', 0x0112) . pack('v', 3) . pack('V', 1) . pack('v', $orientacion) . pack('v', 0)
            . pack('V', 0);
        $segmento = "Exif\0\0" . $tiff;

        return "\xFF\xD8" . "\xFF\xE1" . pack('n', strlen($segmento) + 2) . $segmento . substr($jpeg, 2);
    }

    /**
     * Un PNG. Opaco, es de un solo color; con transparencia, rojo en la mitad izquierda y transparente en la
     * derecha.
     */
    public static function png(int $ancho, int $alto, bool $transparente = false): string
    {
        $imagen = imagecreatetruecolor($ancho, $alto);

        if ($transparente) {
            imagealphablending($imagen, false);
            imagesavealpha($imagen, true);
            imagefill($imagen, 0, 0, (int) imagecolorallocatealpha($imagen, 0, 0, 0, 127));
            imagefilledrectangle($imagen, 0, 0, intdiv($ancho, 2) - 1, $alto - 1, (int) imagecolorallocate($imagen, 200, 30, 30));
        } else {
            imagefill($imagen, 0, 0, (int) imagecolorallocate($imagen, 30, 90, 200));
        }

        ob_start();
        imagepng($imagen);

        return (string) ob_get_clean();
    }
}
