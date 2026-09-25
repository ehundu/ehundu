<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Un fichero de `contenido/` que se compila, en Markdown o en Twig, con sus
 * campos ya combinados con la cascada y normalizados.
 */
final readonly class Pagina
{
    /**
     * @param string                  $ruta        ruta dentro de `contenido/`: 'blog/un-articulo.md'
     * @param string                  $formato     'md' o 'twig'
     * @param array<array-key, mixed> $campos
     * @param string                  $cuerpo      el fichero sin el front matter
     * @param int                     $lineaCuerpo línea del fichero en la que empieza el cuerpo
     */
    public function __construct(
        public string $ruta,
        public string $formato,
        public array $campos,
        public string $cuerpo,
        public int $lineaCuerpo,
    ) {
    }
}
