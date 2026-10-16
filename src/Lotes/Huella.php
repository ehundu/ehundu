<?php

declare(strict_types=1);

namespace Ehundu\Lotes;

use Ehundu\Proyecto;

/**
 * La huella de un proyecto para una compilación por lotes (formato §10.4): sirve
 * para saber, de una llamada a la siguiente, si algo de lo que se lee ha
 * cambiado. Son el nombre, el tamaño y la fecha de `sitio.yml` y de todos los
 * ficheros de `contenido/`, `datos/`, `plantillas/`, `parciales/` y `publico/`;
 * el contenido no se lee, porque en `publico/` puede haber gigas.
 *
 * @internal
 */
final class Huella
{
    private const array CARPETAS = [Proyecto::CONTENIDO, Proyecto::DATOS, Proyecto::PLANTILLAS, Proyecto::PARCIALES, Proyecto::PUBLICO];

    public static function de(Proyecto $proyecto): string
    {
        $lineas = [self::linea($proyecto, Proyecto::SITIO)];

        foreach (self::CARPETAS as $carpeta) {
            foreach ($proyecto->ficherosDe($carpeta) as $fichero) {
                $lineas[] = self::linea($proyecto, "{$carpeta}/{$fichero}");
            }
        }

        return sha1(implode("\n", $lineas));
    }

    private static function linea(Proyecto $proyecto, string $fichero): string
    {
        $completo = $proyecto->ruta($fichero);

        return is_file($completo)
            ? "{$fichero}|" . filesize($completo) . '|' . filemtime($completo)
            : "{$fichero}|-";
    }
}
