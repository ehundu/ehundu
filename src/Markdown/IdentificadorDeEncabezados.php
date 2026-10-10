<?php

declare(strict_types=1);

namespace Ehundu\Markdown;

use Ehundu\Encabezados;
use Ehundu\Slug;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\NodeIterator;
use League\CommonMark\Node\StringContainerInterface;

/**
 * Da un `id` a los encabezados del Markdown y, si el sitio lo pide, un enlace
 * permanente al final de cada uno (formato §8). Recuerda cuáles ha tocado, que
 * son los que ven las plantillas en `pagina.encabezados`.
 *
 * Solo cuentan los que escribe el Markdown del cuerpo: no los de dentro de un
 * atajo que envuelve contenido ni, claro, los del HTML escrito a mano.
 *
 * @internal
 */
final class IdentificadorDeEncabezados
{
    /** El `id` de un encabezado cuyo texto no deja nada en el `slug`. */
    private const string ID_SIN_TEXTO = 'seccion';

    /** @var list<array{nivel: int, texto: string, id: string}> */
    private array $encabezados = [];

    /**
     * @param string $idioma el de la página, para la etiqueta del enlace
     */
    public function __construct(
        private readonly Encabezados $configuracion,
        private readonly string $idioma,
    ) {
    }

    /**
     * @return list<array{nivel: int, texto: string, id: string}>
     */
    public function encabezados(): array
    {
        return $this->encabezados;
    }

    public function __invoke(DocumentParsedEvent $evento): void
    {
        /** @var array<string, true> $usados */
        $usados = [];

        // Solo los bloques: añadir el enlace a un encabezado no toca lo que se recorre.
        foreach ($evento->getDocument()->iterator(NodeIterator::FLAG_BLOCKS_ONLY) as $nodo) {
            if (!$nodo instanceof Heading || !$this->configuracion->recibeId($nodo->getLevel()) || self::estaDentroDeUnAtajo($nodo)) {
                continue;
            }

            $texto = self::textoDe($nodo);
            $id = self::idLibre($texto, $usados);
            $usados[$id] = true;

            $nodo->data->set('attributes/id', $id);
            $this->encabezados[] = ['nivel' => $nodo->getLevel(), 'texto' => $texto, 'id' => $id];

            if ($this->configuracion->enlace !== null) {
                $nodo->appendChild(new HtmlInline($this->enlace($id, $texto)));
            }
        }
    }

    /**
     * El `slug` del texto y, si ya hay otro encabezado con él, el primer
     * `slug-2`, `slug-3`… que esté libre.
     *
     * @param array<string, true> $usados
     */
    private static function idLibre(string $texto, array $usados): string
    {
        $base = Slug::de($texto);
        $base = $base === '' ? self::ID_SIN_TEXTO : $base;
        $id = $base;

        for ($n = 2; isset($usados[$id]); ++$n) {
            $id = "{$base}-{$n}";
        }

        return $id;
    }

    private static function estaDentroDeUnAtajo(Node $nodo): bool
    {
        for ($padre = $nodo->parent(); $padre !== null; $padre = $padre->parent()) {
            if ($padre instanceof AtajoDeBloque) {
                return true;
            }
        }

        return false;
    }

    /**
     * El texto de un encabezado como lo leería quien no ve el marcado: sin
     * enlaces, sin énfasis, sin el HTML escrito a mano y sin el texto
     * alternativo de una imagen, con los espacios y los saltos de línea en uno.
     */
    private static function textoDe(Node $nodo): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', self::textoDeLosHijos($nodo)));
    }

    private static function textoDeLosHijos(Node $nodo): string
    {
        $texto = '';

        foreach ($nodo->children() as $hijo) {
            $texto .= match (true) {
                $hijo instanceof Image, $hijo instanceof HtmlInline, $hijo instanceof AtajoEnLinea => '',
                $hijo instanceof Newline => ' ',
                $hijo instanceof StringContainerInterface => $hijo->getLiteral(),
                default => self::textoDeLosHijos($hijo),
            };
        }

        return $texto;
    }

    private function enlace(string $id, string $texto): string
    {
        $etiqueta = $this->configuracion->etiquetaDe($this->idioma);

        return sprintf(
            '<a class="%s" href="#%s"%s>%s</a>',
            htmlspecialchars($this->configuracion->clase, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $id,
            $etiqueta === null ? '' : sprintf(' aria-label="%s"', htmlspecialchars(str_replace('{texto}', $texto, $etiqueta), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            htmlspecialchars((string) $this->configuracion->enlace, ENT_NOQUOTES | ENT_HTML5, 'UTF-8'),
        );
    }
}
