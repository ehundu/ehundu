<?php

declare(strict_types=1);

namespace Ehundu\Markdown;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\Config\ConfigurationAwareInterface;
use League\Config\ConfigurationInterface;

/**
 * Los renderizadores que Ehundu añade o cambia en CommonMark.
 *
 * @internal
 */
final class Renderizadores
{
    /**
     * Etiquetas que empiezan un bloque: si un atajo solo en su párrafo da una
     * de ellas, sustituye al párrafo, porque no cabe dentro de un `<p>`.
     */
    private const string BLOQUE = '~^<(?:address|article|aside|audio|blockquote|details|div|dl|fieldset|figure|footer|form|h[1-6]|header|hr|iframe|nav|ol|p|picture|pre|section|table|ul|video)\b~i';

    /**
     * Los atajos en medio del texto.
     */
    public static function atajoEnLinea(Contexto $contexto): NodeRendererInterface
    {
        return new class($contexto) implements NodeRendererInterface {
            public function __construct(
                private readonly Contexto $contexto,
            ) {
            }

            public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
            {
                AtajoEnLinea::assertInstanceOf($node);

                return Renderizadores::htmlDe($node, $this->contexto);
            }
        };
    }

    /**
     * Los atajos que envuelven contenido: su contenido se convierte antes.
     */
    public static function atajoDeBloque(Contexto $contexto): NodeRendererInterface
    {
        return new class($contexto) implements NodeRendererInterface {
            public function __construct(
                private readonly Contexto $contexto,
            ) {
            }

            public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
            {
                AtajoDeBloque::assertInstanceOf($node);

                return $this->contexto->atajo(
                    $node->nombre,
                    $node->atributos,
                    $childRenderer->renderNodes($node->children()),
                    $node->getStartLine(),
                );
            }
        };
    }

    /**
     * Los párrafos: uno que solo tiene una imagen (enlazada o no) es una
     * figura, y uno que solo tiene un atajo de bloque es ese atajo. El resto,
     * como siempre.
     */
    public static function parrafo(NodeRendererInterface $original, Contexto $contexto): NodeRendererInterface
    {
        return new class($original, $contexto) implements NodeRendererInterface, ConfigurationAwareInterface {
            public function __construct(
                private readonly NodeRendererInterface $original,
                private readonly Contexto $contexto,
            ) {
            }

            public function setConfiguration(ConfigurationInterface $configuration): void
            {
                if ($this->original instanceof ConfigurationAwareInterface) {
                    $this->original->setConfiguration($configuration);
                }
            }

            public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable|string|null
            {
                Paragraph::assertInstanceOf($node);
                $unico = $node->firstChild();

                if ($unico !== null && $unico->next() === null) {
                    if ($unico instanceof AtajoEnLinea) {
                        $html = Renderizadores::htmlDe($unico, $this->contexto);

                        if (Renderizadores::esBloque($html)) {
                            return $html;
                        }
                    }

                    $imagen = $unico instanceof Link && $unico->firstChild() instanceof Image && $unico->firstChild()->next() === null
                        ? $unico->firstChild()
                        : $unico;

                    if ($imagen instanceof Image) {
                        $pie = $childRenderer->renderNodes($imagen->children());

                        return $this->contexto->figura(
                            $imagen->getUrl(),
                            html_entity_decode(strip_tags($pie), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                            $pie,
                            $unico instanceof Link ? $unico->getUrl() : null,
                            $node->getStartLine(),
                        );
                    }
                }

                return $this->original->render($node, $childRenderer);
            }
        };
    }

    /**
     * Envuelve un renderizador de CommonMark para que escriba las etiquetas
     * vacías como HTML5, `<img …>` y `<br>`, sin la barra final.
     */
    public static function sinBarraFinal(NodeRendererInterface $original): NodeRendererInterface
    {
        return new class($original) implements NodeRendererInterface, ConfigurationAwareInterface {
            public function __construct(
                private readonly NodeRendererInterface $original,
            ) {
            }

            public function setConfiguration(ConfigurationInterface $configuration): void
            {
                if ($this->original instanceof ConfigurationAwareInterface) {
                    $this->original->setConfiguration($configuration);
                }
            }

            public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
            {
                return (string) preg_replace(
                    '~<(img|br|hr)\b([^<>]*?)\s*/>~',
                    '<$1$2>',
                    (string) $this->original->render($node, $childRenderer),
                    1,
                );
            }
        };
    }

    public static function htmlDe(AtajoEnLinea $atajo, Contexto $contexto): string
    {
        return $atajo->html(fn () => $contexto->atajo($atajo->nombre, $atajo->atributos, null, $atajo->linea));
    }

    public static function esBloque(string $html): bool
    {
        return preg_match(self::BLOQUE, $html) === 1;
    }
}
