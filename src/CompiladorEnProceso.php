<?php

declare(strict_types=1);

namespace Ehundu;

use Ehundu\Plantillas\Maquetador;

/**
 * La implementación por defecto: compila el proyecto entero dentro del
 * proceso que la llama.
 *
 * Primero construye en memoria las páginas, el sitemap y el feed, y reúne
 * los ficheros que hay que copiar; solo si todo sale bien vacía `salida/` y
 * lo escribe. Un error no deja `salida/` a medias.
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

        $colecciones = new Colecciones($lectura->paginas, $ahora);
        $maquetador = new Maquetador($proyecto, $lectura, $colecciones, $avisos);
        $destinos = new Destinos();

        /** @var array<string, string> $escritos contenido de cada fichero de salida que se escribe */
        $escritos = [];

        foreach ($lectura->paginas as $pagina) {
            if ($pagina->url !== false && $pagina->estaPublicada($ahora)) {
                $fichero = Url::fichero($pagina->url);
                $destinos->anotar($fichero, Proyecto::CONTENIDO . "/{$pagina->ruta}");
                $escritos[$fichero] = $maquetador->maquetar($pagina);
            }
        }

        $paginas = count($escritos);

        /** @var array<string, string> $copias origen de cada fichero que se copia, por destino */
        $copias = [];

        foreach ($lectura->ficheros as $fichero) {
            $destinos->anotar($fichero, $copias[$fichero] = Proyecto::CONTENIDO . "/{$fichero}");
        }

        foreach ($proyecto->ficherosDe(Proyecto::PUBLICO) as $fichero) {
            $origen = Proyecto::PUBLICO . "/{$fichero}";
            $destinos->anotar($fichero, $origen);
            $copias[$fichero] = $origen;
        }

        // Si el proyecto ya tiene su propio sitemap o feed, gana el suyo.
        if (!$destinos->ocupado('sitemap.xml')) {
            $escritos['sitemap.xml'] = Sitemap::generar($lectura->sitio, $colecciones->coleccion(Colecciones::TODO));
        }

        if (!$destinos->ocupado('feed.xml')) {
            $feed = Feed::generar($lectura->sitio, $colecciones, $maquetador->cuerpo(...), $avisos);

            if ($feed !== null) {
                $escritos['feed.xml'] = $feed;
            }
        }

        $proyecto->vaciarSalida();

        foreach ($escritos as $fichero => $contenido) {
            $proyecto->escribirEnSalida($fichero, $contenido);
        }

        foreach ($copias as $destino => $origen) {
            $proyecto->copiarASalida($origen, $destino);
        }

        return new Informe(
            paginas: $paginas,
            avisos: [...$lectura->avisos, ...$avisos->todos()],
            segundos: (hrtime(true) - $inicio) / 1e9,
            ficheros: count($copias),
        );
    }
}
