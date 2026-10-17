<?php

declare(strict_types=1);

namespace Ehundu\Markdown;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

/**
 * Avisa de un atajo registrado cuyos atributos no se pueden leer, como `[imagen fichero=a.png]` sin comillas: no
 * lo reconoce `ParserDeAtajosEnLinea`, y sin este aviso se quedaría en la página como texto, sin que nadie lo
 * notara. Lo deja como texto, igual que antes (formato §8.1).
 *
 * @internal
 */
final class ParserDeAtajosMalEscritos implements InlineParserInterface
{
    public function __construct(
        private readonly Contexto $contexto,
    ) {
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('\[(' . SintaxisDeAtajos::NOMBRE . ')(\s+[^\]\[]*=[^\]\[]*)\](?![(\[:])')->caseSensitive();
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        [$nombre] = $inlineContext->getSubMatches();

        if ($this->contexto->registrado($nombre)) {
            $this->contexto->avisar(
                "El atajo «{$nombre}» está mal escrito: cada atributo se escribe nombre=\"valor\", con comillas dobles; se deja como texto",
                $inlineContext->getContainer()->getStartLine(),
            );
        }

        return false;
    }
}
