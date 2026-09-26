<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Lo que devuelve una compilación.
 */
final readonly class Informe
{
    /**
     * @param int         $paginas  páginas escritas en `salida/`
     * @param list<Aviso> $avisos
     * @param float       $segundos duración de la compilación
     * @param int         $ficheros ficheros copiados tal cual desde `publico/` y `contenido/`
     */
    public function __construct(
        public int $paginas,
        public array $avisos,
        public float $segundos,
        public int $ficheros = 0,
    ) {
    }
}
