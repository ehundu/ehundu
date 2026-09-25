<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * La configuración de `sitio.yml` (formato §2).
 */
final readonly class Sitio
{
    /**
     * @param string                  $url    dirección absoluta, sin barra final
     * @param array<array-key, mixed> $campos todo `sitio.yml` salvo `despliegue`: es la
     *                                        variable `sitio` de las plantillas
     */
    public function __construct(
        public string $nombre,
        public string $url,
        public array $campos,
    ) {
    }
}
