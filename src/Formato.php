<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * La versión del formato de un proyecto (formato §2 y §12): un número entero
 * que declara `sitio.yml` con `formato: 1` y, si un editor lo pide, `esquema.json`
 * con `"formato": 1`. Dentro de una versión mayor el formato solo crece, así que
 * no hay versión menor.
 *
 * Sin la clave, el formato es el 1. Uno mayor que el que entiende esta versión de
 * Ehundu detiene la compilación: compilarlo a ciegas daría un sitio mal hecho sin
 * que nadie lo notara.
 */
final class Formato
{
    /** La versión del formato que entiende esta versión de Ehundu. */
    public const int VERSION = 1;

    /**
     * La versión que declara un proyecto, tal como sale del YAML o del JSON.
     * Un valor que no es un entero mayor que cero se avisa y se toma como 1.
     *
     * @param string   $fichero ruta relativa a la raíz del proyecto, para los avisos
     * @param int|null $linea   la de `formato` en ese fichero
     *
     * @throws ErrorDeProyecto si es mayor que la que entiende esta versión de Ehundu
     */
    public static function leer(mixed $valor, string $fichero, ?int $linea, Avisos $avisos): int
    {
        if ($valor === null) {
            return 1;
        }

        if (!is_int($valor) || $valor < 1) {
            $avisos->registrar('«formato» tiene que ser un número entero mayor que cero, la versión del formato (ahora, ' . self::VERSION . '); se toma 1', $fichero, $linea);

            return 1;
        }

        if ($valor > self::VERSION) {
            throw new ErrorDeProyecto(
                "Este proyecto es del formato {$valor} y esta versión de Ehundu entiende hasta el " . self::VERSION . '; actualiza Ehundu',
                $fichero,
                $linea,
            );
        }

        return $valor;
    }
}
