<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Cómo va una compilación por lotes (formato §10.4): lo que devuelven
 * `CompiladorPorLotes::iniciar()`, `avanzar()` y `estado()`.
 */
final readonly class Lote
{
    /**
     * @param int          $hechas     cuántas tareas lleva hechas
     * @param int          $total      cuántas tiene el lote: una por página, una por fichero que se copia, el
     *                                 sitemap y el feed, y la limpieza final
     * @param Informe|null $informe    el resultado, cuando el lote está completo
     * @param bool         $reiniciado si esta llamada ha empezado de nuevo porque el proyecto había cambiado
     * @param bool         $ocupado    si otra llamada estaba avanzando y esta no ha hecho nada
     */
    public function __construct(
        public int $hechas,
        public int $total,
        private ?Informe $informe = null,
        private bool $reiniciado = false,
        private bool $ocupado = false,
    ) {
    }

    /**
     * Si el lote ha terminado: `salida/` es entonces la de una compilación
     * completa. Mientras sea falso, no se debe publicar nada.
     */
    public function completo(): bool
    {
        return $this->informe !== null;
    }

    /**
     * Lo que lleva hecho, de 0 a 1.
     */
    public function progreso(): float
    {
        return $this->total === 0 ? 1.0 : min(1.0, $this->hechas / $this->total);
    }

    /**
     * El resultado, cuando el lote está completo.
     */
    public function informe(): ?Informe
    {
        return $this->informe;
    }

    /**
     * Si esta llamada ha tenido que empezar de nuevo porque el proyecto había
     * cambiado desde la anterior.
     */
    public function reiniciado(): bool
    {
        return $this->reiniciado;
    }

    /**
     * Si otra llamada estaba avanzando el lote y esta no ha hecho nada.
     */
    public function ocupado(): bool
    {
        return $this->ocupado;
    }
}
