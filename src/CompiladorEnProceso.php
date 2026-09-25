<?php

declare(strict_types=1);

namespace Ehundu;

use Ehundu\Plantillas\Maquetador;

/**
 * La implementación por defecto: compila el proyecto entero dentro del
 * proceso que la llama.
 *
 * Primero construye todas las páginas en memoria; solo si todas salen bien
 * vacía `salida/` y las escribe. Un error en una plantilla no deja `salida/`
 * a medias.
 */
final class CompiladorEnProceso implements Compilador
{
    /**
     * @param \DateTimeImmutable|null $ahora el momento con el que se decide qué está
     *                                       publicado; si falta, el de cada compilación
     */
    public function __construct(
        private readonly ?\DateTimeImmutable $ahora = null,
    ) {
    }

    public function compilar(Proyecto $proyecto): Informe
    {
        $inicio = hrtime(true);
        $ahora = $this->ahora ?? new \DateTimeImmutable();
        $avisos = new Avisos();

        $lectura = (new Lector())->leer($proyecto);
        Url::comprobarColisiones($lectura->paginas, $ahora);

        $maquetador = new Maquetador($proyecto, $lectura, new Colecciones($lectura->paginas, $ahora), $avisos);

        /** @var array<string, string> $ficheros HTML de cada fichero de salida */
        $ficheros = [];

        foreach ($lectura->paginas as $pagina) {
            if ($pagina->url !== false && $pagina->estaPublicada($ahora)) {
                $ficheros[Url::fichero($pagina->url)] = $maquetador->maquetar($pagina);
            }
        }

        $proyecto->vaciarSalida();

        foreach ($ficheros as $fichero => $html) {
            $proyecto->escribirEnSalida($fichero, $html);
        }

        return new Informe(
            paginas: count($ficheros),
            avisos: [...$lectura->avisos, ...$avisos->todos()],
            segundos: (hrtime(true) - $inicio) / 1e9,
        );
    }
}
