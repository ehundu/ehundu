<?php

declare(strict_types=1);

namespace Ehundu\Markdown;

use League\CommonMark\Node\Inline\AbstractInline;

/**
 * Un atajo simple, en medio del texto o solo en su párrafo.
 *
 * @internal
 */
final class AtajoEnLinea extends AbstractInline
{
    private ?string $html = null;

    /**
     * @param array<string, string> $atributos
     * @param int|null              $linea     línea del Markdown en la que está su párrafo
     */
    public function __construct(
        public readonly string $nombre,
        public readonly array $atributos,
        public readonly ?int $linea,
    ) {
        parent::__construct();
    }

    /**
     * El HTML del atajo; se construye una sola vez aunque se pida dos.
     *
     * @param \Closure(): string $construir
     */
    public function html(\Closure $construir): string
    {
        return $this->html ??= $construir();
    }
}
