<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Markdown;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MarkdownPrueba extends TestCase
{
    #[Test]
    public function convierteCommonMark(): void
    {
        self::assertSame(
            "<h2>Título</h2>\n<p>Un <strong>texto</strong> con <a href=\"/contacto/\">enlace</a>.</p>\n",
            (new Markdown())->convertir("## Título\n\nUn **texto** con [enlace](/contacto/)."),
        );
    }

    #[Test]
    public function admiteTablasNotasAlPieYEnlacesAutomaticos(): void
    {
        $html = (new Markdown())->convertir("| a | b |\n|---|---|\n| 1 | 2 |\n\nVer https://ejemplo.com y la nota[^1].\n\n[^1]: La nota.\n");

        self::assertStringContainsString('<table>', $html);
        self::assertStringContainsString('<a href="https://ejemplo.com">https://ejemplo.com</a>', $html);
        self::assertStringContainsString('class="footnotes"', $html);
    }

    #[Test]
    public function noInterpretaTwig(): void
    {
        self::assertSame("<p>{{ datos.cliente.nombre }}</p>\n", (new Markdown())->convertir('{{ datos.cliente.nombre }}'));
    }
}
