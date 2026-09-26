<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Un sitio construido en memoria, listo para escribirse en `salida/` o para
 * servirse tal cual desde la previsualización.
 */
final readonly class Construccion
{
    /**
     * @param array<string, string> $escritos contenido de cada fichero que se genera
     *                                        (páginas, sitemap, feed), por ruta en `salida/`
     * @param array<string, string> $copias   origen de cada fichero que se copia tal cual,
     *                                        relativo a la raíz del proyecto, por ruta en `salida/`
     * @param int                   $paginas  cuántos de los escritos son páginas
     * @param list<Aviso>           $avisos
     */
    public function __construct(
        public array $escritos,
        public array $copias,
        public int $paginas,
        public array $avisos,
    ) {
    }
}
