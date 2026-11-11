<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Lo que devuelve `GeneradorDeVariantes` (formato §9.2).
 *
 * @internal
 */
final readonly class InformeDeVariantes
{
    /**
     * @param int          $imagenes   las imágenes que se han mirado
     * @param list<string> $generadas  los ficheros que se han escrito, relativos a `publico/`
     * @param int          $existentes los ficheros que ya estaban y no se han rehecho
     * @param list<Aviso>  $avisos
     * @param float        $segundos   lo que ha tardado
     */
    public function __construct(
        public int $imagenes,
        public array $generadas,
        public int $existentes,
        public array $avisos,
        public float $segundos,
    ) {
    }

    /**
     * «Variantes: 3 imágenes, 18 ficheros generados (6 ya existían) en 0,42 s, 1 aviso.»
     */
    public function resumen(): string
    {
        $imagenes = $this->imagenes === 1 ? '1 imagen' : "{$this->imagenes} imágenes";
        $generadas = count($this->generadas);
        $generadas = $generadas === 1 ? '1 fichero generado' : "{$generadas} ficheros generados";
        $existentes = match ($this->existentes) {
            0 => '',
            1 => ' (1 ya existía)',
            default => " ({$this->existentes} ya existían)",
        };
        $segundos = number_format($this->segundos, 2, ',', '.');
        $avisos = match (count($this->avisos)) {
            0 => '',
            1 => ', 1 aviso',
            default => ', ' . count($this->avisos) . ' avisos',
        };

        return "Variantes: {$imagenes}, {$generadas}{$existentes} en {$segundos} s{$avisos}.";
    }
}
