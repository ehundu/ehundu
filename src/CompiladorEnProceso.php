<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * La implementación por defecto: construye el proyecto entero dentro del
 * proceso que la llama y deja `salida/` sincronizada con lo construido.
 *
 * Solo si la construcción sale bien se toca `salida/`; un error la deja como
 * estaba.
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
        $construccion = (new Constructor())->construir($proyecto, $this->ahora);

        $proyecto->sincronizarSalida($construccion->escritos, $construccion->copias);

        return new Informe(
            paginas: $construccion->paginas,
            avisos: $construccion->avisos,
            segundos: (hrtime(true) - $inicio) / 1e9,
            ficheros: count($construccion->copias),
        );
    }
}
