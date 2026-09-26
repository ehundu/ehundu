<?php

declare(strict_types=1);

namespace Ehundu\Markdown;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * Un atajo que envuelve contenido: sus hijos son los bloques de Markdown que
 * hay entre `[nombre ...]` y `[/nombre]`.
 *
 * @internal
 */
final class AtajoDeBloque extends AbstractBlock
{
    /**
     * @param array<string, string> $atributos
     */
    public function __construct(
        public readonly string $nombre,
        public readonly array $atributos,
    ) {
        parent::__construct();
    }
}
