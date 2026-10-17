<?php

declare(strict_types=1);

namespace Ehundu;

use Ehundu\Incremental\Almacen;

/**
 * La compilación incremental para quien incruste el motor (formato §10.5): construye el proyecto dentro del
 * proceso que la llama, como `CompiladorEnProceso`, pero recuerda la última compilación en `.ehundu/memoria.bin`
 * y rehace solo lo que un cambio afecta, aunque la compilación anterior fuera de otro proceso. El resultado
 * es el de una compilación completa: los mismos ficheros en `salida/` y los mismos avisos.
 *
 * Sin una memoria que valga (la primera vez, tras cambiar de versión del motor, si se borra `.ehundu/`) hace una
 * compilación completa. Con `avanzar` esa compilación, o cualquier otra, se reparte en las llamadas que haga falta.
 *
 * Solo si la compilación sale bien se guarda la memoria; un error del proyecto la deja como estaba.
 */
final class CompiladorIncrementalEnProceso implements Compilador
{
    /**
     * @param \DateTimeImmutable|null $ahora el momento con el que se decide qué está publicado; si falta, el de
     *                                       cada llamada
     */
    public function __construct(
        private readonly ?\DateTimeImmutable $ahora = null,
    ) {
    }

    /**
     * Compila de una vez. Si otra llamada está compilando este proyecto, espera a que termine, hasta un
     * minuto (`Almacen::ESPERA_MAXIMA`): pasado ese tiempo falla en vez de quedarse esperando.
     *
     * @throws ErrorDeProyecto si el proyecto no se puede compilar, o si otra llamada no acaba a tiempo
     */
    public function compilar(Proyecto $proyecto): Informe
    {
        return $this->trabajar($proyecto, null)->informe()
            ?? throw new \LogicException('Una compilación sin límite de tiempo tiene que terminar');
    }

    /**
     * Rehace páginas hasta agotar el presupuesto, y siempre al menos una si hay alguna que rehacer, y guarda lo
     * hecho para la llamada siguiente. Cuando el `Lote` que devuelve está completo, `salida/` es la de una
     * compilación completa; hasta entonces mezcla páginas nuevas y viejas y nada debe publicarse. Una llamada que
     * llega mientras otra está avanzando no espera: devuelve el estado con `ocupado()`.
     *
     * @param float $segundos lo que se puede tardar: la página que se ha empezado se termina, así que una llamada
     *                        puede tardar más
     *
     * @throws ErrorDeProyecto si el proyecto no se puede compilar
     */
    public function avanzar(Proyecto $proyecto, float $segundos): Lote
    {
        return $this->trabajar($proyecto, max(0.0, $segundos));
    }

    /**
     * Olvida lo que recordaba de este proyecto: la siguiente compilación será completa.
     */
    public function olvidar(Proyecto $proyecto): void
    {
        $cerrojo = Almacen::bloquear($proyecto, esperar: true);

        try {
            Almacen::olvidar($proyecto);
        } finally {
            Almacen::soltar($cerrojo);
        }
    }

    private function trabajar(Proyecto $proyecto, ?float $segundos): Lote
    {
        $inicio = hrtime(true);
        $cerrojo = Almacen::bloquear($proyecto, esperar: $segundos === null);

        if ($cerrojo === null) {
            return new Lote(0, 0, ocupado: true);
        }

        try {
            $proyecto->comprobarSalida();

            $guardada = Almacen::cargar($proyecto);
            $constructor = new Constructor(incremental: true);
            $constructor->recordar($guardada['memoria'] ?? null, $guardada['guardada'] ?? 0);

            // Lo que llevan hecho las llamadas anteriores de esta compilación repartida, si la memoria que quedó
            // es la suya
            $curso = $guardada === null ? ['rehechas' => 0, 'segundos' => 0.0] : Almacen::leerCurso($proyecto);

            $construccion = $constructor->construir(
                $proyecto,
                $this->ahora,
                limite: $segundos === null ? null : Presupuesto::limite($inicio, $segundos),
            );

            if (!$construccion->completa) {
                foreach ($construccion->escritos as $fichero => $contenido) {
                    $proyecto->escribirSiCambia($fichero, $contenido);
                }

                self::guardar($proyecto, $constructor);
                Almacen::guardarCurso($proyecto, [
                    'rehechas' => $curso['rehechas'] + (int) $construccion->rehechas,
                    'segundos' => $curso['segundos'] + (hrtime(true) - $inicio) / 1e9,
                ]);

                return new Lote($construccion->paginas, $construccion->total);
            }

            $proyecto->sincronizarSalida($construccion->escritos, $construccion->copias);

            // Si no se ha rehecho ninguna página, lo que había guardado vale igual: no se vuelve a escribir
            // (cientos de megas en un sitio grande) para que la siguiente compare con lo mismo
            if ($guardada === null || $construccion->rehechas !== 0) {
                self::guardar($proyecto, $constructor);
            }

            Almacen::olvidarCurso($proyecto);
            $segundosEnTotal = $curso['segundos'] + (hrtime(true) - $inicio) / 1e9;

            return new Lote($construccion->total, $construccion->total, new Informe(
                paginas: $construccion->paginas,
                avisos: $construccion->avisos,
                segundos: $segundosEnTotal,
                ficheros: count($construccion->copias),
                rehechas: min($construccion->paginas, $curso['rehechas'] + (int) $construccion->rehechas),
            ));
        } finally {
            Almacen::soltar($cerrojo);
        }
    }

    /**
     * Guarda lo que recuerda el constructor. Si no se puede (el disco está lleno), no es un error de la
     * compilación, que ha salido bien: la siguiente se aprovechará de la memoria anterior, si valía, o será
     * completa.
     */
    private static function guardar(Proyecto $proyecto, Constructor $constructor): void
    {
        $memoria = $constructor->memoria();

        if ($memoria === null) {
            return;
        }

        try {
            Almacen::guardar($proyecto, $memoria);
        } catch (ErrorDeProyecto) {
            // Se sigue con lo que hubiera
        }
    }
}
