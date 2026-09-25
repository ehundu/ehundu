<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * La implementación por defecto: compila el proyecto entero dentro del
 * proceso que la llama.
 */
final class CompiladorEnProceso implements Compilador
{
    public function compilar(Proyecto $proyecto): Informe
    {
        $inicio = hrtime(true);

        $lectura = (new Lector())->leer($proyecto);

        $salida = $proyecto->ruta(Proyecto::SALIDA);
        if (!is_dir($salida) && !@mkdir($salida, 0777, true) && !is_dir($salida)) {
            throw new ErrorDeProyecto("No se puede crear la carpeta de salida: {$salida}");
        }

        return new Informe(
            paginas: 0,
            avisos: $lectura->avisos,
            segundos: (hrtime(true) - $inicio) / 1e9,
        );
    }
}
