<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * La implementación por defecto: compila el proyecto entero dentro del
 * proceso que la llama.
 */
final class CompiladorEnProceso implements Compilador
{
    /**
     * @param \DateTimeImmutable|null $ahora el momento con el que se decide qué está
     *                                       publicado; si falta, el de cada compilación
     */
    public function __construct(
        private readonly ?\DateTimeImmutable $ahora = null,
    ) {
    }

    public function compilar(Proyecto $proyecto): Informe
    {
        $inicio = hrtime(true);
        $ahora = $this->ahora ?? new \DateTimeImmutable();

        $lectura = (new Lector())->leer($proyecto);
        Url::comprobarColisiones($lectura->paginas, $ahora);

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
