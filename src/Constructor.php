<?php

declare(strict_types=1);

namespace Ehundu;

use Ehundu\Plantillas\Maquetador;
use Ehundu\Plantillas\Registro;

/**
 * Construye un sitio en memoria: las páginas, el sitemap y el feed, y la
 * lista de ficheros que se copian tal cual. No escribe nada en disco.
 *
 * Un constructor incremental recuerda la última construcción que salió bien
 * y en la siguiente rehace solo las páginas y los cuerpos afectados por lo
 * que ha cambiado (ver `Plan`). El resultado es siempre el mismo que el de
 * una construcción completa. Si una construcción falla, la siguiente empieza
 * de cero.
 */
final class Constructor
{
    private ?Memoria $memoria = null;
    private ?Huellas $huellas = null;
    private ?string $raizDeLasHuellas = null;

    /**
     * @param bool $incremental si se recuerda cada construcción para aprovecharla en la siguiente
     */
    public function __construct(
        private readonly bool $incremental = false,
    ) {
    }

    /**
     * @param \DateTimeImmutable|null $ahora         el momento con el que se decide qué está publicado
     * @param bool                    $conBorradores si se incluyen los borradores y las páginas futuras
     *
     * @throws ErrorDeProyecto si el proyecto no se puede construir
     */
    public function construir(Proyecto $proyecto, ?\DateTimeImmutable $ahora = null, bool $conBorradores = false): Construccion
    {
        $ahora ??= new \DateTimeImmutable();
        $anterior = $this->memoria;
        $this->memoria = null;
        $avisos = new Avisos();

        $lectura = (new Lector())->leer($proyecto);
        Url::comprobarColisiones($lectura->paginas, $ahora, $conBorradores);

        /** @var array<string, true> $publicadas */
        $publicadas = [];

        foreach ($lectura->paginas as $pagina) {
            if ($pagina->estaPublicada($ahora, $conBorradores)) {
                $publicadas[$pagina->ruta] = true;
            }
        }

        $huellas = $this->incremental ? $this->huellas($proyecto)->tomar(Plan::VIGILADAS) : [];
        $plan = Plan::trazar($anterior, $proyecto, $conBorradores, $lectura, $huellas, $publicadas);
        $aprovechables = $plan->completa === null ? array_flip($plan->paginas) : [];

        $colecciones = new Colecciones($lectura->paginas, $ahora, $conBorradores, $lectura->sitio->idiomas);
        $maquetador = new Maquetador($proyecto, $lectura, $colecciones, $avisos);
        $destinos = new Destinos();

        if ($anterior !== null && $plan->completa === null) {
            $maquetador->precargar(array_intersect_key($anterior->cuerpos, array_flip($plan->cuerpos)));
        }

        /** @var array<string, string> $escritos */
        $escritos = [];

        /** @var array<string, array{html: string, registro: Registro}> $hechas */
        $hechas = [];
        $rehechas = 0;

        foreach ($lectura->paginas as $pagina) {
            if ($pagina->url === false || !isset($publicadas[$pagina->ruta])) {
                continue;
            }

            $fichero = Url::fichero($pagina->url);
            $destinos->anotar($fichero, Proyecto::CONTENIDO . "/{$pagina->ruta}");

            if ($anterior !== null && isset($aprovechables[$pagina->ruta])) {
                $hecha = $anterior->paginas[$pagina->ruta];

                foreach ($hecha['registro']->avisos as $aviso) {
                    $avisos->registrar($aviso->mensaje, $aviso->fichero, $aviso->linea);
                }
            } else {
                $html = $maquetador->maquetar($pagina);
                $hecha = ['html' => $html, 'registro' => $maquetador->registroDe($pagina->ruta) ?? new Registro()];
                $rehechas++;
            }

            $escritos[$fichero] = $hecha['html'];
            $hechas[$pagina->ruta] = $hecha;
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

        $this->avisarDeLasPortadas($lectura, $destinos, $avisos);

        // Si el proyecto ya tiene su propio sitemap o feed, gana el suyo.
        if (!$destinos->ocupado('sitemap.xml')) {
            $escritos['sitemap.xml'] = Sitemap::generar($lectura->sitio, $colecciones->coleccion(Colecciones::TODO));
        }

        $feeds = Feed::generar($lectura->sitio, $colecciones, $maquetador->cuerpo(...), $avisos, $destinos->ocupado(...));

        foreach ($feeds as $fichero => $feed) {
            $escritos[$fichero] = $feed;
        }

        if ($this->incremental) {
            $this->memoria = new Memoria($proyecto->raiz, $conBorradores, $lectura, $huellas, $publicadas, $maquetador->cuerpos(), $hechas);
        }

        return new Construccion($escritos, $copias, $paginas, [...$lectura->avisos, ...$avisos->todos()], $rehechas);
    }

    /**
     * En un sitio que declara sus idiomas, avisa de los que no tienen una
     * página en su raíz: el selector de idioma llevaría a una dirección que
     * no existe (formato §15.3).
     */
    private function avisarDeLasPortadas(Lectura $lectura, Destinos $destinos, Avisos $avisos): void
    {
        $idiomas = $lectura->sitio->idiomas;

        if (!$idiomas->declarados) {
            return;
        }

        foreach ($idiomas->codigos() as $codigo) {
            $raiz = $idiomas->raiz($codigo);

            if (!$destinos->ocupado(Url::fichero($raiz))) {
                $avisos->registrar(
                    "Ninguna página tiene la URL {$raiz}, la portada en «{$codigo}»: el selector de idioma llevaría a una dirección que no existe",
                    Proyecto::SITIO,
                );
            }
        }
    }

    /**
     * Olvida la construcción anterior: la siguiente será completa.
     */
    public function olvidar(): void
    {
        $this->memoria = null;
    }

    private function huellas(Proyecto $proyecto): Huellas
    {
        // Las huellas de un proyecto no valen para otro.
        if ($this->huellas === null || $this->raizDeLasHuellas !== $proyecto->raiz) {
            $this->huellas = new Huellas($proyecto, ocultos: true);
            $this->raizDeLasHuellas = $proyecto->raiz;
        }

        return $this->huellas;
    }
}
