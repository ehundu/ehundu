<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Lo que puede durar una llamada que reparte su trabajo: el `avanzar` de la
 * compilación por lotes y el de la incremental, y el despliegue con
 * presupuesto (formato §10.4 y §10.5).
 *
 * Los segundos que da quien llama se convierten en el momento, de
 * `hrtime(true)`, en que hay que parar. Un número que no cabe en un entero de
 * nanosegundos (`INF`, `PHP_INT_MAX`) no puede dar un límite: en PHP, un
 * decimal fuera de rango pasa a 0 y la llamada acababa tras la primera tarea,
 * justo al contrario de lo que quería quien pedía «sin límite».
 *
 * @internal
 */
final class Presupuesto
{
    /** El mayor presupuesto que se tiene en cuenta, en segundos (un día): más que eso es «sin límite». */
    public const int MAXIMO = 86400;

    /**
     * El momento en que se acaba un presupuesto de `$segundos` que empieza en `$inicio`.
     * Uno negativo o que no es un número vale 0 (se hace una tarea, que es lo
     * mínimo que se hace siempre), y uno de más de un día, un día.
     *
     * @param int $inicio el `hrtime(true)` del comienzo
     */
    public static function limite(int $inicio, float $segundos): int
    {
        if (is_nan($segundos) || $segundos < 0) {
            $segundos = 0.0;
        }

        return $inicio + (int) round(min($segundos, self::MAXIMO) * 1e9);
    }
}
