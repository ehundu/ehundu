<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Compila un proyecto repartido en varias llamadas, cada una con un presupuesto
 * de tiempo (formato §10.4), para un proceso web que no puede dedicarle de una
 * vez todo lo que hace falta. Al terminar deja `salida/` y los avisos de una
 * compilación completa. Por dónde va se guarda en `.ehundu/lote.json`, en el
 * proyecto.
 *
 * No se promete en la 1.0 (decisión 128): para repartir una compilación en
 * llamadas, `CompiladorIncrementalEnProceso::avanzar`.
 *
 * @internal
 */
interface CompiladorPorLotes
{
    /**
     * Empieza un lote nuevo y descarta el que hubiera. Hace las comprobaciones
     * que detienen una compilación completa antes de escribir nada, y no deja
     * hecha ninguna tarea.
     *
     * @throws ErrorDeProyecto si el proyecto no se puede compilar
     */
    public function iniciar(Proyecto $proyecto): Lote;

    /**
     * Retoma el lote que haya y hace tareas hasta agotar el presupuesto, y
     * siempre al menos una. Si no hay ninguno, empieza uno; si el proyecto ha
     * cambiado desde la llamada anterior, empieza de nuevo; si está completo,
     * no hace nada.
     *
     * @param float $segundos lo que puede durar esta llamada; la tarea que ha empezado se termina. Negativo
     *                        o `NAN` vale 0, y más de un día, un día: `INF` quiere decir «sin límite»
     *
     * @throws ErrorDeProyecto si el proyecto no se puede compilar
     */
    public function avanzar(Proyecto $proyecto, float $segundos): Lote;

    /**
     * Cómo va el lote, sin hacer nada; null si no hay ninguno.
     */
    public function estado(Proyecto $proyecto): ?Lote;

    /**
     * Descarta el lote que hubiera, completo o no. No hace nada si otra llamada
     * lo está avanzando.
     */
    public function abandonar(Proyecto $proyecto): void;
}
