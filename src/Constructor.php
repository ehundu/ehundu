<?php

declare(strict_types=1);

namespace Ehundu;

use Ehundu\Plantillas\Maquetador;

/**
 * Construye un sitio en memoria: las páginas, el sitemap y el feed, y la
 * lista de ficheros que se copian tal cual. No escribe nada en disco.
 */
final class Constructor
{
    /**
     * @param \DateTimeImmutable|null $ahora         el momento con el que se decide qué está publicado
     * @param bool                    $conBorradores si se incluyen los borradores y las páginas futuras
     *
     * @throws ErrorDeProyecto si el proyecto no se puede construir
     */
    public function construir(Proyecto $proyecto, ?\DateTimeImmutable $ahora = null, bool $conBorradores = false): Construccion
    {
        $ahora ??= new \DateTimeImmutable();
        $avisos = new Avisos();

        $lectura = (new Lector())->leer($proyecto);
        Url::comprobarColisiones($lectura->paginas, $ahora, $conBorradores);

        $colecciones = new Colecciones($lectura->paginas, $ahora, $conBorradores);
        $maquetador = new Maquetador($proyecto, $lectura, $colecciones, $avisos);
        $destinos = new Destinos();

        /** @var array<string, string> $escritos */
        $escritos = [];

        foreach ($lectura->paginas as $pagina) {
            if ($pagina->url !== false && $pagina->estaPublicada($ahora, $conBorradores)) {
                $fichero = Url::fichero($pagina->url);
                $destinos->anotar($fichero, Proyecto::CONTENIDO . "/{$pagina->ruta}");
                $escritos[$fichero] = $maquetador->maquetar($pagina);
            }
        }

        $paginas = count($escritos);

        /** @var array<string, string> $copias */
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

        return new Construccion($escritos, $copias, $paginas, [...$lectura->avisos, ...$avisos->todos()]);
    }
}
