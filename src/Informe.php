<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Lo que devuelve una compilación.
 */
final readonly class Informe
{
    /**
     * @param int         $paginas  páginas escritas en `salida/`
     * @param list<Aviso> $avisos
     * @param float       $segundos duración de la compilación
     * @param int         $ficheros ficheros copiados tal cual desde `publico/` y `contenido/`
     * @param int|null    $rehechas de las páginas, las que se han construido de nuevo; si no se dice, todas
     *                              (una compilación completa): `$rehechas` vale siempre un número
     */
    public function __construct(
        public int $paginas,
        public array $avisos,
        public float $segundos,
        public int $ficheros = 0,
        ?int $rehechas = null,
    ) {
        $this->rehechas = $rehechas ?? $paginas;
    }

    /** De las páginas, las que se han construido de nuevo: todas en una compilación completa. */
    public int $rehechas;

    /**
     * «Compilado: 57 páginas y 154 ficheros en 0,80 s, 2 avisos.» Si solo
     * se han rehecho algunas páginas, «57 páginas (3 rehechas)».
     */
    public function resumen(): string
    {
        $paginas = $this->paginas === 1 ? '1 página' : "{$this->paginas} páginas";

        if ($this->rehechas < $this->paginas) {
            $paginas .= $this->rehechas === 1 ? ' (1 rehecha)' : " ({$this->rehechas} rehechas)";
        }

        $paginas .= match ($this->ficheros) {
            0 => '',
            1 => ' y 1 fichero',
            default => " y {$this->ficheros} ficheros",
        };
        $segundos = number_format($this->segundos, 2, ',', '.');
        $avisos = match (count($this->avisos)) {
            0 => '',
            1 => ', 1 aviso',
            default => ', ' . count($this->avisos) . ' avisos',
        };

        return "Compilado: {$paginas} en {$segundos} s{$avisos}.";
    }
}
