<?php

declare(strict_types=1);

namespace Ehundu\Markdown;

use Ehundu\Avisos;
use Ehundu\Encabezados;
use Ehundu\EnlacesExternos;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\ThematicBreak;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Renderer\Block\ThematicBreakRenderer;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\ImageRenderer;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\Footnote\Node\FootnoteContainer;
use League\CommonMark\Extension\Footnote\Renderer\FootnoteContainerRenderer;
use League\CommonMark\Extension\InlinesOnly\InlinesOnlyExtension;
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
 *
 * Si el sitio lo pide, los encabezados reciben un `id` y, a su elección, un
 * enlace permanente (`Encabezados`); no es un paso sobre el HTML ya generado,
 * sino algo que se hace al convertir.
 *
 * @internal
 */
final class Conversor
{
    /** @var list<string> dominios que no son externos */
    private array $dominiosPropios;

    /** @var array<string, MarkdownConverter> convertidores de línea ya montados, por idioma */
    private array $convertidoresEnLinea = [];

    /** @var array<string, MarkdownConverter> convertidores de bloque ya montados, por idioma */
    private array $convertidoresEnBloque = [];

    /**
     * @param string          $urlDelSitio     la de `sitio.yml`, para saber qué enlaces son externos
     * @param EnlacesExternos $enlacesExternos lo que llevan esos enlaces además de `noopener` (formato §2)
     * @param Encabezados     $encabezados     qué encabezados reciben `id` y si llevan enlace permanente (formato §2)
     */
    public function __construct(
        string $urlDelSitio,
        private readonly EnlacesExternos $enlacesExternos = new EnlacesExternos(),
        private readonly Encabezados $encabezados = new Encabezados(),
    ) {
        $this->dominiosPropios = EnlacesExternos::dominiosPropios($urlDelSitio);
    }

    /**
     * @param string $fichero      ruta del fichero relativa a la raíz del proyecto, para los avisos
     * @param int    $primeraLinea línea del fichero en la que empieza el Markdown
     * @param string $idioma       el de la página, para el texto del aviso de los enlaces externos
     */
    public function convertir(
        string $markdown,
        ResolutorDeAtajos $atajos,
        Avisos $avisos,
        string $fichero,
        int $primeraLinea = 1,
        string $idioma = '',
    ): string {
        return $this->convertirConEncabezados($markdown, $atajos, $avisos, $fichero, $primeraLinea, $idioma)->html;
    }

    /**
     * Lo mismo que `convertir()`, y además los encabezados que han recibido
     * `id`, en el orden en que aparecen.
     *
     * @param string $fichero      ruta del fichero relativa a la raíz del proyecto, para los avisos
     * @param int    $primeraLinea línea del fichero en la que empieza el Markdown
     * @param string $idioma       el de la página, para el texto del aviso de los enlaces externos y de los encabezados
     */
    public function convertirConEncabezados(
        string $markdown,
        ResolutorDeAtajos $atajos,
        Avisos $avisos,
        string $fichero,
        int $primeraLinea = 1,
        string $idioma = '',
    ): Convertido {
        $contexto = new Contexto($atajos, $avisos, $fichero, $primeraLinea);

        $entorno = new Environment($this->configuracion());

        $entorno->addExtension(new CommonMarkCoreExtension());
        $entorno->addExtension(new TableExtension());
        $entorno->addExtension(new FootnoteExtension());
        $entorno->addExtension(new StrikethroughExtension());
        $entorno->addExtension(new AutolinkExtension());
        $entorno->addExtension(new ExternalLinkExtension());

        $this->avisarDeLosEnlacesExternos($entorno, $idioma);

        $identificador = new IdentificadorDeEncabezados($this->encabezados, $idioma);

        if ($this->encabezados->activo()) {
            // Después de todo lo que cambia el documento: lo que cuenta es el texto que queda.
            $entorno->addEventListener(DocumentParsedEvent::class, $identificador, -200);
        }

        $entorno->addBlockStartParser(new ParserDeAtajosDeBloque($contexto, SintaxisDeAtajos::conCierre($markdown)), 100);
        $entorno->addInlineParser(new ParserDeAtajosEnLinea($contexto), 100);

        $entorno->addRenderer(AtajoEnLinea::class, Renderizadores::atajoEnLinea($contexto));
        $entorno->addRenderer(AtajoDeBloque::class, Renderizadores::atajoDeBloque($contexto));
        $entorno->addRenderer(Paragraph::class, Renderizadores::parrafo(new ParagraphRenderer(), $contexto), 10);
        $entorno->addRenderer(Image::class, Renderizadores::sinBarraFinal(new ImageRenderer()), 10);
        $entorno->addRenderer(Newline::class, Renderizadores::sinBarraFinal(new NewlineRenderer()), 10);
        $entorno->addRenderer(ThematicBreak::class, Renderizadores::sinBarraFinal(new ThematicBreakRenderer()), 10);
        $entorno->addRenderer(FootnoteContainer::class, Renderizadores::sinBarraFinal(new FootnoteContainerRenderer()), 10);

        $html = (new MarkdownConverter($entorno))->convert($markdown)->getContent();

        return new Convertido($html, $identificador->encabezados());
    }

    /**
     * El Markdown de un campo (formato §7.3, filtro `markdown`): el de línea de
     * CommonMark, sin párrafos ni bloques y sin atajos, con tachado, enlaces
     * automáticos y HTML escrito a mano; los enlaces a otros dominios van como
     * en el cuerpo. Un salto con barra invertida o dos espacios sale como
     * `<br>`. No da salto de línea final.
     *
     * @param string $idioma el de la página, para el texto del aviso de los enlaces externos
     */
    public function convertirEnLinea(string $markdown, string $idioma = ''): string
    {
        $convertidor = $this->convertidoresEnLinea[$idioma] ??= $this->montarConvertidorEnLinea($idioma);

        return rtrim($convertidor->convert($markdown)->getContent(), "\n");
    }

    /**
     * El Markdown de un campo con párrafos (formato §7.3, filtro
     * `markdown('bloque')`): los bloques de CommonMark, con tachado, enlaces
     * automáticos y enlaces externos como en el cuerpo. Sin atajos, sin
     * figuras (una imagen sola es una imagen), sin tablas, sin notas al pie y
     * sin `id` en los encabezados: eso es del cuerpo. No da salto de línea
     * final.
     *
     * @param string $idioma el de la página, para el texto del aviso de los enlaces externos
     */
    public function convertirEnBloque(string $markdown, string $idioma = ''): string
    {
        $convertidor = $this->convertidoresEnBloque[$idioma] ??= $this->montarConvertidorEnBloque($idioma);

        return rtrim($convertidor->convert($markdown)->getContent(), "\n");
    }

    private function montarConvertidorEnBloque(string $idioma): MarkdownConverter
    {
        $entorno = new Environment($this->configuracion());

        $entorno->addExtension(new CommonMarkCoreExtension());
        $entorno->addExtension(new StrikethroughExtension());
        $entorno->addExtension(new AutolinkExtension());
        $entorno->addExtension(new ExternalLinkExtension());

        $this->avisarDeLosEnlacesExternos($entorno, $idioma);

        $entorno->addRenderer(Image::class, Renderizadores::sinBarraFinal(new ImageRenderer()), 10);
        $entorno->addRenderer(Newline::class, Renderizadores::sinBarraFinal(new NewlineRenderer()), 10);
        $entorno->addRenderer(ThematicBreak::class, Renderizadores::sinBarraFinal(new ThematicBreakRenderer()), 10);

        return new MarkdownConverter($entorno);
    }

    private function montarConvertidorEnLinea(string $idioma): MarkdownConverter
    {
        $entorno = new Environment($this->configuracion());

        $entorno->addExtension(new InlinesOnlyExtension());
        $entorno->addExtension(new StrikethroughExtension());
        $entorno->addExtension(new AutolinkExtension());
        $entorno->addExtension(new ExternalLinkExtension());

        $this->avisarDeLosEnlacesExternos($entorno, $idioma);

        $entorno->addRenderer(Image::class, Renderizadores::sinBarraFinal(new ImageRenderer()), 10);
        $entorno->addRenderer(Newline::class, Renderizadores::sinBarraFinal(new NewlineRenderer()), 10);

        return new MarkdownConverter($entorno);
    }

    /**
     * @return array<string, mixed>
     */
    private function configuracion(): array
    {
        return [
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            'external_link' => [
                'internal_hosts' => $this->dominiosPropios,
                'open_in_new_window' => true,
                'noopener' => 'external',
                'noreferrer' => $this->enlacesExternos->noreferrer ? 'external' : '',
            ],
        ];
    }

    private function avisarDeLosEnlacesExternos(Environment $entorno, string $idioma): void
    {
        $aviso = $this->enlacesExternos->avisoDe($idioma);

        if ($aviso !== null) {
            // Después de ExternalLinkProcessor (prioridad -50), que es quien dice qué enlaces son externos.
            $entorno->addEventListener(DocumentParsedEvent::class, $this->anadirAviso($aviso), -100);
        }
    }

    /**
     * Pone al final de cada enlace externo el aviso para lectores de pantalla,
     * en un `<span>` con la clase del sitio y un espacio delante. Un enlace que
     * solo envuelve una imagen es una figura (formato §8) y no lo lleva.
     *
     * @return \Closure(DocumentParsedEvent): void
     */
    private function anadirAviso(string $aviso): \Closure
    {
        $html = sprintf(
            '<span class="%s"> %s</span>',
            htmlspecialchars($this->enlacesExternos->clase, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            htmlspecialchars($aviso, ENT_NOQUOTES | ENT_HTML5, 'UTF-8'),
        );

        return function (DocumentParsedEvent $evento) use ($html): void {
            foreach ($evento->getDocument()->iterator() as $enlace) {
                if (!$enlace instanceof Link || $enlace->data->get('external', false) !== true) {
                    continue;
                }

                $primero = $enlace->firstChild();

                if ($primero instanceof Image && $primero->next() === null) {
                    continue;
                }

                $enlace->appendChild(new HtmlInline($html));
            }
        };
    }
}
