<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Compila un proyecto y deja el resultado en su carpeta `salida/`.
 *
 * Es una interfaz para que quien hospeda el motor (la consola, una
 * herramienta de edición) no dependa de cómo se compila: hoy en el propio
 * proceso, más adelante de forma incremental, por lotes o en un trabajador
 * aparte.
 */
interface Compilador
{
    /**
     * @throws ErrorDeProyecto si el proyecto no se puede compilar
     */
    public function compilar(Proyecto $proyecto): Informe;
}
