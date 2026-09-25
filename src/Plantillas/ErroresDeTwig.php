<?php

declare(strict_types=1);

namespace Ehundu\Plantillas;

use Ehundu\ErrorDeProyecto;
use Twig\Error\Error;

/**
 * Pasa los errores de Twig a errores de proyecto, en español y con el
 * fichero y la línea de la plantilla. Los mensajes más habituales se
 * traducen; el resto conserva el texto original de Twig detrás de una
 * explicación en español.
 *
 * @internal
 */
final class ErroresDeTwig
{
    /** Mensajes que ya da el propio Ehundu en español. */
    private const array PROPIOS = ['/^Las plantillas y los parciales /'];

    /**
     * @param \Closure(string): string $fichero pasa el nombre de la plantilla en Twig a su
     *                                          ruta relativa a la raíz del proyecto
     */
    public static function traducir(Error $error, \Closure $fichero): ErrorDeProyecto
    {
        $nombre = $error->getSourceContext()?->getName();
        $donde = $nombre === null ? null : $fichero($nombre);
        $linea = $error->getTemplateLine() > 0 ? $error->getTemplateLine() : null;

        // Un error que viene de dentro (una página que se necesita a sí misma,
        // un criterio de orden mal escrito) se respeta; si no sabe dónde
        // ocurrió, se le añade la plantilla.
        for ($anterior = $error->getPrevious(); $anterior !== null; $anterior = $anterior->getPrevious()) {
            if ($anterior instanceof ErrorDeProyecto) {
                return $anterior->fichero !== null
                    ? $anterior
                    : new ErrorDeProyecto($anterior->descripcion, $donde, $linea, $anterior);
            }

            if (!$anterior instanceof Error) {
                return new ErrorDeProyecto("Error en la plantilla: {$anterior->getMessage()}", $donde, $linea, $error);
            }
        }

        return new ErrorDeProyecto(self::mensaje($error->getRawMessage()), $donde, $linea, $error);
    }

    private static function mensaje(string $original): string
    {
        foreach (self::PROPIOS as $patron) {
            if (preg_match($patron, $original) === 1) {
                return $original;
            }
        }

        $traducciones = [
            '/^Unknown "([^"]+)" filter/' => 'No existe el filtro «%s»',
            '/^Unknown "([^"]+)" function/' => 'No existe la función «%s»',
            '/^Unknown "([^"]+)" tag/' => 'No existe la etiqueta «%s»',
            '/^Unable to find template "([^"]+)"/' => 'No se encuentra la plantilla «%s»',
            '/^Template "([^"]+)" is not defined/' => 'No se encuentra la plantilla «%s»',
        ];

        foreach ($traducciones as $patron => $traduccion) {
            if (preg_match($patron, $original, $partes) === 1) {
                $mensaje = sprintf($traduccion, $partes[1]);

                if (preg_match('/Did you mean "([^"]+)"/', $original, $sugerida) === 1) {
                    $mensaje .= ". ¿Querías decir «{$sugerida[1]}»?";
                }

                return $mensaje;
            }
        }

        return "Error en la plantilla (Twig dice: {$original})";
    }
}
