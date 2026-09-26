<?php

declare(strict_types=1);

namespace Ehundu\Markdown;

/**
 * Lo que el Markdown necesita saber de los atajos de un sitio: cuáles hay y
 * cómo se convierten en HTML. El conversor no sabe nada de plantillas.
 */
interface ResolutorDeAtajos
{
    /**
     * Los nombres de los atajos registrados: los de Ehundu y los del sitio.
     *
     * @return list<string>
     */
    public function nombres(): array;

    /**
     * @param array<string, string> $atributos
     * @param string|null           $contenido el contenido ya en HTML, si el atajo lo envuelve
     * @param int|null              $linea     línea del Markdown, para los avisos
     */
    public function atajo(string $nombre, array $atributos, ?string $contenido, ?int $linea): string;

    /**
     * Una imagen sola en su párrafo, que se construye como el atajo `imagen`.
     *
     * @param string      $alt     el texto alternativo, en texto plano
     * @param string      $pie     el mismo texto convertido a HTML
     * @param string|null $enlace  la dirección, si la imagen está enlazada
     */
    public function figura(string $src, string $alt, string $pie, ?string $enlace, ?int $linea): string;
}
