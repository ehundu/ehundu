<?php

declare(strict_types=1);

namespace Ehundu;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Convierte el cuerpo de una página de Markdown a HTML (formato §8):
 * CommonMark, con tablas, notas al pie y enlaces automáticos. El Markdown no
 * pasa nunca por Twig.
 */
final class Markdown
{
    private MarkdownConverter $conversor;

    public function __construct()
    {
        $entorno = new Environment();
        $entorno->addExtension(new CommonMarkCoreExtension());
        $entorno->addExtension(new TableExtension());
        $entorno->addExtension(new FootnoteExtension());
        $entorno->addExtension(new AutolinkExtension());

        $this->conversor = new MarkdownConverter($entorno);
    }

    public function convertir(string $markdown): string
    {
        return $this->conversor->convert($markdown)->getContent();
    }
}
