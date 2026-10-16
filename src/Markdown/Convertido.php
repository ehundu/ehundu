<?php

declare(strict_types=1);

namespace Ehundu\Markdown;

/**
 * Un cuerpo en Markdown ya convertido: su HTML y los encabezados que han
 * recibido `id` (formato §8), en el orden en que aparecen.
 *
 * @internal
 */
final readonly class Convertido
{
    /**
     * @param list<array{nivel: int, texto: string, id: string}> $encabezados
     */
    public function __construct(
        public string $html,
        public array $encabezados = [],
    ) {
    }
}
