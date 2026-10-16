<?php

declare(strict_types=1);

namespace Ehundu\Plantillas;

use Ehundu\Pagina;
use Twig\Markup;

/**
 * Una página tal como la ven las plantillas (formato §7 y §15.8): sus campos,
 * más `url`, `ruta`, `idioma`, `pdf` (la dirección de su PDF, si lo tiene),
 * `contenido`, `markdown` (su cuerpo sin convertir, solo en Markdown),
 * `encabezados` (los de su cuerpo que han recibido `id`, formato §8),
 * `traducciones` y, en una página que genera `agrupar`, `paginas` (formato
 * §6.3; su `grupo` es un campo más). Se lee como un
 * array: `pagina.titulo`, `articulo.url`. No se puede modificar desde una
 * plantilla.
 *
 * @implements \ArrayAccess<string, mixed>
 *
 * @internal
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
     * @param \Closure(Pagina): string|null $markdown  da el cuerpo sin convertir, y apunta que
     *                                                 quien lo pide depende de esa página
     * @param \Closure(Pagina): list<VistaDePagina>|null $paginasDelGrupo da las páginas del grupo
     *                                                 de una página generada por `agrupar`, y apunta
     *                                                 que quien las pide depende de cada una
     * @param \Closure(Pagina): list<array{nivel: int, texto: string, id: string}>|null $encabezados da los
     *                                                 encabezados del cuerpo que han recibido `id`, y
     *                                                 apunta que quien los pide depende de ese cuerpo
     */
    public function __construct(
        private readonly Pagina $pagina,
        private readonly ?\Closure $renderizar = null,
        private readonly ?\Closure $traducciones = null,
        private readonly ?\Closure $markdown = null,
        private readonly ?\Closure $paginasDelGrupo = null,
        private readonly ?\Closure $encabezados = null,
    ) {
    }

    public function paginaDeOrigen(): Pagina
    {
        return $this->pagina;
    }

    public function offsetExists(mixed $clave): bool
    {
        return match ($clave) {
            'url', 'ruta', 'idioma', 'traducciones', 'encabezados' => true,
            'pdf' => $this->pagina->urlPdf() !== null,
            'contenido' => $this->renderizar !== null,
            'markdown' => $this->tieneMarkdown(),
            'paginas' => $this->tienePaginas() || isset($this->pagina->campos['paginas']),
            default => isset($this->pagina->campos[$clave]),
        };
    }

    public function offsetGet(mixed $clave): mixed
    {
        return match ($clave) {
            'contenido' => $this->contenido(),
            'markdown' => $this->tieneMarkdown() ? ($this->markdown)($this->pagina) : null,
            'traducciones' => $this->traducciones === null ? [] : ($this->traducciones)($this->pagina),
            'encabezados' => $this->encabezados === null ? [] : ($this->encabezados)($this->pagina),
            'paginas' => $this->tienePaginas() ? ($this->paginasDelGrupo)($this->pagina) : $this->pagina->valor('paginas'),
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

    private function tieneMarkdown(): bool
    {
        return $this->markdown !== null && $this->pagina->formato === 'md';
    }

    private function tienePaginas(): bool
    {
        return $this->paginasDelGrupo !== null && $this->pagina->miembros !== null;
    }

    private function contenido(): ?Markup
    {
        return $this->renderizar === null ? null : new Markup(($this->renderizar)($this->pagina), 'UTF-8');
    }
}
