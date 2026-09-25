<?php

declare(strict_types=1);

namespace Ehundu\Plantillas;

use Ehundu\Pagina;
use Twig\Markup;

/**
 * Una página tal como la ven las plantillas (formato §7): sus campos, más
 * `url`, `ruta` y `contenido`. Se lee como un array: `pagina.titulo`,
 * `articulo.url`. No se puede modificar desde una plantilla.
 *
 * @implements \ArrayAccess<string, mixed>
 */
final class VistaDePagina implements \ArrayAccess
{
    private ?Markup $contenido = null;

    /**
     * @param \Closure(Pagina): string|null $renderizar da el cuerpo ya convertido a HTML;
     *                                                 se llama la primera vez que se pide
     */
    public function __construct(
        private readonly Pagina $pagina,
        private readonly ?\Closure $renderizar = null,
    ) {
    }

    public function paginaDeOrigen(): Pagina
    {
        return $this->pagina;
    }

    public function offsetExists(mixed $clave): bool
    {
        return match ($clave) {
            'url', 'ruta' => true,
            'contenido' => $this->renderizar !== null,
            default => isset($this->pagina->campos[$clave]),
        };
    }

    public function offsetGet(mixed $clave): mixed
    {
        return match ($clave) {
            'url' => $this->pagina->url === false ? null : $this->pagina->url,
            'ruta' => $this->pagina->ruta,
            'contenido' => $this->contenido(),
            default => $this->pagina->campos[$clave] ?? null,
        };
    }

    public function offsetSet(mixed $clave, mixed $valor): never
    {
        throw new \LogicException('Una página no se puede modificar desde una plantilla');
    }

    public function offsetUnset(mixed $clave): never
    {
        throw new \LogicException('Una página no se puede modificar desde una plantilla');
    }

    private function contenido(): ?Markup
    {
        if ($this->contenido === null && $this->renderizar !== null) {
            $this->contenido = new Markup(($this->renderizar)($this->pagina), 'UTF-8');
        }

        return $this->contenido;
    }
}
