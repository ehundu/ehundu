<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\ErrorDeProyecto;
use Ehundu\FrontMatter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FrontMatterPrueba extends TestCase
{
    #[Test]
    public function separaElFrontMatterDelCuerpo(): void
    {
        $partes = FrontMatter::separar("---\ntitulo: Hola\nfecha: 2025-03-18\n---\nCuerpo\n", 'contenido/a.md');

        self::assertSame("titulo: Hola\nfecha: 2025-03-18", $partes->yaml);
        self::assertSame(2, $partes->lineaYaml);
        self::assertSame("Cuerpo\n", $partes->cuerpo);
        self::assertSame(5, $partes->lineaCuerpo);
    }

    #[Test]
    public function sinFrontMatterTodoEsCuerpo(): void
    {
        $partes = FrontMatter::separar("# Hola\n\n---\n\nAdiós\n", 'contenido/a.md');

        self::assertSame('', $partes->yaml);
        self::assertSame("# Hola\n\n---\n\nAdiós\n", $partes->cuerpo);
        self::assertSame(1, $partes->lineaCuerpo);
    }

    #[Test]
    public function admiteUnFrontMatterVacio(): void
    {
        $partes = FrontMatter::separar("---\n---\nCuerpo", 'contenido/a.md');

        self::assertSame('', $partes->yaml);
        self::assertSame('Cuerpo', $partes->cuerpo);
        self::assertSame(3, $partes->lineaCuerpo);
    }

    #[Test]
    public function toleraEspaciosDetrasDeLasRayas(): void
    {
        $partes = FrontMatter::separar("---  \ntitulo: Hola\n--- \nCuerpo", 'contenido/a.md');

        self::assertSame('titulo: Hola', $partes->yaml);
        self::assertSame('Cuerpo', $partes->cuerpo);
    }

    #[Test]
    public function fallaSiNoSeCierra(): void
    {
        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('contenido/a.md:1: El front matter no se cierra: falta la segunda línea «---»');

        FrontMatter::separar("---\ntitulo: Hola\nCuerpo", 'contenido/a.md');
    }

    #[Test]
    public function rechazaFrontMatterQueNoEsYaml(): void
    {
        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('contenido/etiquetas.twig:1: Solo se admite front matter en YAML');

        FrontMatter::separar("---js\nconst layout = 'x';\n---\n", 'contenido/etiquetas.twig');
    }
}
