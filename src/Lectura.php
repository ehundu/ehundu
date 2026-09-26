<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Lo que se ha leído de un proyecto, antes de compilar nada.
 */
final readonly class Lectura
{
    /**
     * @param array<string, mixed> $datos   los ficheros de `datos/`, por nombre
     * @param list<Pagina>         $paginas ordenadas por ruta
     * @param list<Aviso>          $avisos
     * @param list<string>         $ficheros los demás ficheros de `contenido/`, que se copian tal cual
     */
    public function __construct(
        public Sitio $sitio,
        public array $datos,
        public array $paginas,
        public array $avisos,
        public array $ficheros = [],
    ) {
    }
}
