<?php

declare(strict_types=1);

namespace Ehundu\Plantillas;

use Ehundu\Pagina;
use Twig\Markup;

/**
 * Una página tal como la ven las plantillas (formato §7 y §15.8): sus campos,
 * más `url`, `ruta`, `idioma`, `contenido` y `traducciones`. Se lee como un
 * array: `pagina.titulo`, `articulo.url`. No se puede modificar desde una
 * plantilla.
 *
 * @implements \ArrayAccess<string, mixed>
 */
final class VistaDePagina implements \ArrayAccess
{
    /**
     * @param \Closure(Pagina): string|null $renderizar da el cuerpo ya convertido a HTML. Se
     *                                                 llama cada vez que se pide: quien lo da
     *                                                 guarda el cuerpo convertido, y así puede
     *                                                 volver a declarar su CSS y su JS en la
     *                                                 página que lo reutiliza
     * @param \Closure(Pagina): array<string, VistaDePagina>|null $traducciones da sus versiones
     *                                                 publicadas, por idioma
     */
    public function __construct(
        private readonly Pagina $pagina,
        private readonly ?\Closure $renderizar = null,
        private readonly ?\Closure $traducciones = null,
    ) {
    }

    public function paginaDeOrigen(): Pagina
    {
        return $this->pagina;
    }

    public function offsetExists(mixed $clave): bool
    {
        return match ($clave) {
            'url', 'ruta', 'idioma', 'traducciones' => true,
            'contenido' => $this->renderizar !== null,
            default => isset($this->pagina->campos[$clave]),
        };
    }

    public function offsetGet(mixed $clave): mixed
    {
        return match ($clave) {
            'contenido' => $this->contenido(),
            'traducciones' => $this->traducciones === null ? [] : ($this->traducciones)($this->pagina),
            default => $this->pagina->valor((string) $clave),
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
        return $this->renderizar === null ? null : new Markup(($this->renderizar)($this->pagina), 'UTF-8');
    }
}
