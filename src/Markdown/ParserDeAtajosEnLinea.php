<?php

declare(strict_types=1);

namespace Ehundu\Markdown;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

/**
 * Reconoce los atajos simples dentro del texto. Solo los registrados: un
 * `[algo]` sin atributos es texto normal; con atributos, o si es un cierre
 * suelto, se deja como texto y se avisa.
 *
 * @internal
 */
final class ParserDeAtajosEnLinea implements InlineParserInterface
{
    public function __construct(
        private readonly Contexto $contexto,
    ) {
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex(SintaxisDeAtajos::EN_LINEA)->caseSensitive();
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        [$cierre, $nombre, $atributos] = $inlineContext->getSubMatches();
        $linea = $inlineContext->getContainer()->getStartLine();

        if (!$this->contexto->registrado($nombre)) {
            if ($cierre !== '' || trim($atributos) !== '') {
                $this->contexto->avisar("No existe el atajo «{$nombre}»; se deja como texto", $linea);
            }

            return false;
        }

        if ($cierre !== '') {
            $this->contexto->avisar("«[/{$nombre}]» no cierra ningún atajo; se deja como texto", $linea);

            return false;
        }

        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());
        $inlineContext->getContainer()->appendChild(
            new AtajoEnLinea($nombre, SintaxisDeAtajos::atributos($atributos), $linea),
        );

        return true;
    }
}
