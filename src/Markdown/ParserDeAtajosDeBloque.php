<?php

declare(strict_types=1);

namespace Ehundu\Markdown;

use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

/**
 * Reconoce la apertura de un atajo que envuelve contenido: una línea con
 * solo `[nombre ...]`, de un atajo registrado que tiene su `[/nombre]` más
 * abajo. Todo lo que hay hasta el cierre es Markdown y queda dentro.
 *
 * @internal
 */
final class ParserDeAtajosDeBloque implements BlockStartParserInterface
{
    /**
     * @param list<string> $conCierre los atajos que tienen una línea de cierre en el texto
     */
    public function __construct(
        private readonly Contexto $contexto,
        private readonly array $conCierre,
    ) {
    }

    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented()
            || preg_match(SintaxisDeAtajos::APERTURA, $cursor->getRemainder(), $partes) !== 1
            || !in_array($partes[1], $this->conCierre, true)
            || !$this->contexto->registrado($partes[1])) {
            return BlockStart::none();
        }

        $cursor->advanceToEnd();

        return BlockStart::of(new class(new AtajoDeBloque($partes[1], SintaxisDeAtajos::atributos($partes[2])), $this->contexto) extends AbstractBlockContinueParser {
            private bool $cerrado = false;

            public function __construct(
                private readonly AtajoDeBloque $atajo,
                private readonly Contexto $contexto,
            ) {
            }

            public function getBlock(): AtajoDeBloque
            {
                return $this->atajo;
            }

            public function isContainer(): bool
            {
                return true;
            }

            public function canContain(AbstractBlock $childBlock): bool
            {
                return true;
            }

            public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
            {
                if (!$cursor->isIndented() && SintaxisDeAtajos::esCierre($cursor->getRemainder(), $this->atajo->nombre)) {
                    $this->cerrado = true;

                    return BlockContinue::finished();
                }

                return BlockContinue::at($cursor);
            }

            public function closeBlock(): void
            {
                if (!$this->cerrado) {
                    $this->contexto->avisar(
                        "Falta la línea [/{$this->atajo->nombre}] que cierra el atajo; se cierra al final del texto",
                        $this->atajo->getStartLine(),
                    );
                }
            }
        })->at($cursor);
    }
}
