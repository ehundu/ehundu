<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * La implementación por defecto: construye el proyecto entero dentro del
 * proceso que la llama y lo escribe en `salida/`.
 *
 * Solo si la construcción sale bien vacía `salida/` y escribe; un error no
 * deja `salida/` a medias.
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

        $proyecto->vaciarSalida();

        foreach ($construccion->escritos as $fichero => $contenido) {
            $proyecto->escribirEnSalida($fichero, $contenido);
        }

        foreach ($construccion->copias as $destino => $origen) {
            $proyecto->copiarASalida($origen, $destino);
        }

        return new Informe(
            paginas: $construccion->paginas,
            avisos: $construccion->avisos,
            segundos: (hrtime(true) - $inicio) / 1e9,
            ficheros: count($construccion->copias),
        );
    }
}
