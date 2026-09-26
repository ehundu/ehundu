<?php

declare(strict_types=1);

namespace Ehundu\Markdown;

use Ehundu\Avisos;

/**
 * Lo que comparten las piezas del conversor mientras convierten una página:
 * los atajos del sitio, los avisos y dónde está el Markdown en su fichero.
 *
 * @internal
 */
final class Contexto
{
    /** @var array<string, true> */
    private array $registrados;

    /**
     * @param string $fichero      ruta del fichero relativa a la raíz del proyecto
     * @param int    $primeraLinea línea del fichero en la que empieza el Markdown
     */
    public function __construct(
        private readonly ResolutorDeAtajos $resolutor,
        private readonly Avisos $avisos,
        private readonly string $fichero,
        private readonly int $primeraLinea,
    ) {
        $this->registrados = array_fill_keys($resolutor->nombres(), true);
    }

    public function registrado(string $nombre): bool
    {
        return isset($this->registrados[$nombre]);
    }

    public function avisar(string $mensaje, ?int $lineaDelMarkdown): void
    {
        $this->avisos->registrar($mensaje, $this->fichero, $this->lineaDelFichero($lineaDelMarkdown));
    }

    /**
     * @param array<string, string> $atributos
     */
    public function atajo(string $nombre, array $atributos, ?string $contenido, ?int $lineaDelMarkdown): string
    {
        return trim($this->resolutor->atajo($nombre, $atributos, $contenido, $this->lineaDelFichero($lineaDelMarkdown)));
    }

    public function figura(string $src, string $alt, string $pie, ?string $enlace, ?int $lineaDelMarkdown): string
    {
        return trim($this->resolutor->figura($src, $alt, $pie, $enlace, $this->lineaDelFichero($lineaDelMarkdown)));
    }

    private function lineaDelFichero(?int $lineaDelMarkdown): ?int
    {
        return $lineaDelMarkdown === null ? null : $this->primeraLinea + $lineaDelMarkdown - 1;
    }
}
