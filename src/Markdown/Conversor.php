<?php

declare(strict_types=1);

namespace Ehundu\Markdown;

use Ehundu\Avisos;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\ThematicBreak;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Renderer\Block\ThematicBreakRenderer;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\ImageRenderer;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\Footnote\Node\FootnoteContainer;
use League\CommonMark\Extension\Footnote\Renderer\FootnoteContainerRenderer;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Renderer\Block\ParagraphRenderer;
use League\CommonMark\Renderer\Inline\NewlineRenderer;

/**
 * Convierte el cuerpo de una página de Markdown a HTML (formato §8).
 *
 * CommonMark, con tablas, notas al pie, tachado y enlaces automáticos. Una
 * imagen sola en su párrafo es una figura; los enlaces a otros dominios se
 * abren en otra pestaña; los atajos se reconocen al convertir y su HTML lo
 * da el sitio. Las etiquetas vacías salen como en HTML5, sin barra final.
 * El Markdown no pasa nunca por Twig.
 */
final class Conversor
{
    /** @var list<string> dominios que no son externos */
    private array $dominiosPropios;

    /**
     * @param string $urlDelSitio la de `sitio.yml`, para saber qué enlaces son externos
     */
    public function __construct(string $urlDelSitio)
    {
        $dominio = (string) preg_replace('/^www\./i', '', (string) parse_url($urlDelSitio, PHP_URL_HOST));
        $this->dominiosPropios = [$dominio, "www.{$dominio}"];
    }

    /**
     * @param string $fichero      ruta del fichero relativa a la raíz del proyecto, para los avisos
     * @param int    $primeraLinea línea del fichero en la que empieza el Markdown
     */
    public function convertir(
        string $markdown,
        ResolutorDeAtajos $atajos,
        Avisos $avisos,
        string $fichero,
        int $primeraLinea = 1,
    ): string {
        $contexto = new Contexto($atajos, $avisos, $fichero, $primeraLinea);

        $entorno = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            'external_link' => [
                'internal_hosts' => $this->dominiosPropios,
                'open_in_new_window' => true,
                'noopener' => 'external',
                'noreferrer' => '',
            ],
        ]);

        $entorno->addExtension(new CommonMarkCoreExtension());
        $entorno->addExtension(new TableExtension());
        $entorno->addExtension(new FootnoteExtension());
        $entorno->addExtension(new StrikethroughExtension());
        $entorno->addExtension(new AutolinkExtension());
        $entorno->addExtension(new ExternalLinkExtension());

        $entorno->addBlockStartParser(new ParserDeAtajosDeBloque($contexto, SintaxisDeAtajos::conCierre($markdown)), 100);
        $entorno->addInlineParser(new ParserDeAtajosEnLinea($contexto), 100);

        $entorno->addRenderer(AtajoEnLinea::class, Renderizadores::atajoEnLinea($contexto));
        $entorno->addRenderer(AtajoDeBloque::class, Renderizadores::atajoDeBloque($contexto));
        $entorno->addRenderer(Paragraph::class, Renderizadores::parrafo(new ParagraphRenderer(), $contexto), 10);
        $entorno->addRenderer(Image::class, Renderizadores::sinBarraFinal(new ImageRenderer()), 10);
        $entorno->addRenderer(Newline::class, Renderizadores::sinBarraFinal(new NewlineRenderer()), 10);
        $entorno->addRenderer(ThematicBreak::class, Renderizadores::sinBarraFinal(new ThematicBreakRenderer()), 10);
        $entorno->addRenderer(FootnoteContainer::class, Renderizadores::sinBarraFinal(new FootnoteContainerRenderer()), 10);

        return (new MarkdownConverter($entorno))->convert($markdown)->getContent();
    }
}
