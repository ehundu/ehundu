<?php

declare(strict_types=1);

namespace Ehundu;

use Ehundu\Plantillas\Maquetador;
use Ehundu\Plantillas\Registro;

/**
 * Construye un sitio en memoria: las páginas, el PDF de las que lo piden, el
 * sitemap y el feed, y la lista de ficheros que se copian tal cual. No escribe
 * nada en disco.
 *
 * Un constructor incremental recuerda la última construcción que salió bien
 * y en la siguiente rehace solo las páginas y los cuerpos afectados por lo
 * que ha cambiado (ver `Plan`). El resultado es siempre el mismo que el de
 * una construcción completa. Si una construcción falla, la siguiente empieza
 * de cero.
 *
 * @internal
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

        $lectura = self::leer($proyecto, $ahora, $conBorradores);

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

        /** @var array<string, string> $documentos los PDF, por ruta en salida/ */
        $documentos = [];

        /** @var array<string, string> $paquetes los ficheros de CSS y JS que enlazan las páginas, por ruta en salida/ */
        $paquetes = [];

        /** @var array<string, array{html: string, pdf: string|null, paquetes: array<string, string>, registro: Registro}> $hechas */
        $hechas = [];
        $rehechas = 0;

        foreach ($lectura->paginas as $pagina) {
            if ($pagina->url === false || !isset($publicadas[$pagina->ruta])) {
                continue;
            }

            $fichero = Url::fichero($pagina->url);
            $destinos->anotar($fichero, Proyecto::CONTENIDO . "/{$pagina->ruta}");

            $ficheroPdf = $pagina->urlPdf() === null ? null : Url::ficheroPdf($pagina->url);

            if ($ficheroPdf !== null) {
                $destinos->anotar($ficheroPdf, Proyecto::CONTENIDO . "/{$pagina->ruta}");
            }

            if ($anterior !== null && isset($aprovechables[$pagina->ruta])) {
                $hecha = $anterior->paginas[$pagina->ruta];

                foreach ($hecha['registro']->avisos as $aviso) {
                    $avisos->registrar($aviso->mensaje, $aviso->fichero, $aviso->linea);
                }
            } else {
                $hecha = self::hacerPagina($maquetador, $pagina, $ficheroPdf);
                $rehechas++;
            }

            $escritos[$fichero] = $hecha['html'];

            // Las páginas que declaran lo mismo enlazan el mismo fichero: se escribe una vez.
            foreach ($hecha['paquetes'] as $paquete => $contenido) {
                if (!isset($paquetes[$paquete])) {
                    $destinos->anotar($paquete, Proyecto::CONTENIDO . "/{$pagina->ruta}");
                    $paquetes[$paquete] = $contenido;
                }
            }

            if ($ficheroPdf !== null && $hecha['pdf'] !== null) {
                $documentos[$ficheroPdf] = $hecha['pdf'];
            }

            $hechas[$pagina->ruta] = $hecha;
        }

        $paginas = count($escritos);
        $escritos = [...$escritos, ...$documentos, ...$paquetes];

        $copias = self::reunirCopias($proyecto, $lectura, $destinos);

        foreach (self::ficherosFinales($lectura, $colecciones, $maquetador, $destinos, $avisos) as $fichero => $contenido) {
            $escritos[$fichero] = $contenido;
        }

        if ($this->incremental) {
            $this->memoria = new Memoria($proyecto->raiz, $conBorradores, $lectura, $huellas, $publicadas, $maquetador->cuerpos(), $hechas);
        }

        return new Construccion($escritos, $copias, $paginas, [...$lectura->avisos, ...$avisos->todos()], $rehechas);
    }

    /**
     * Lo que se lee del proyecto antes de construir nada: las plantillas de
     * páginas (`agrupar`) se sustituyen por las que generan, y las listas con
     * `paginar` se parten en las suyas. Desde aquí, y en la memoria de la
     * incremental, son páginas como las demás. Y se comprueba que dos páginas
     * no van al mismo fichero.
     *
     * @throws ErrorDeProyecto si el proyecto no se puede leer o dos páginas van al mismo fichero
     */
    public static function leer(Proyecto $proyecto, \DateTimeImmutable $ahora, bool $conBorradores = false): Lectura
    {
        $lectura = (new Agrupador())->expandir((new Lector())->leer($proyecto), $ahora, $conBorradores);
        $lectura = (new Paginador())->expandir($lectura, $ahora, $conBorradores);
        Url::comprobarColisiones($lectura->paginas, $ahora, $conBorradores);

        return $lectura;
    }

    /**
     * Una página hecha: su HTML, su PDF si lo pide, los ficheros de CSS y JS
     * que enlaza y lo que ha usado para hacerse.
     *
     * @param string|null $ficheroPdf el de salida/ de su PDF, si lo tiene
     *
     * @return array{html: string, pdf: string|null, paquetes: array<string, string>, registro: Registro}
     */
    public static function hacerPagina(Maquetador $maquetador, Pagina $pagina, ?string $ficheroPdf): array
    {
        $html = $maquetador->maquetar($pagina);
        $pdf = $ficheroPdf === null ? null : $maquetador->generarPdf($pagina);

        return [
            'html' => $html,
            'pdf' => $pdf,
            'paquetes' => $maquetador->paquetesDe($pagina->ruta),
            'registro' => $maquetador->registroDe($pagina->ruta) ?? new Registro(),
        ];
    }

    /**
     * Los ficheros que se copian tal cual a `salida/` (origen, relativo a la
     * raíz del proyecto, por destino): los de `contenido/` que no son páginas
     * y los de `publico/`. Se anota cada uno en `$destinos`, y un fichero PHP en
     * `publico/` es un error.
     *
     * @return array<string, string>
     *
     * @throws ErrorDeProyecto si dos ficheros van al mismo sitio o hay uno PHP en `publico/`
     */
    public static function reunirCopias(Proyecto $proyecto, Lectura $lectura, Destinos $destinos): array
    {
        /** @var array<string, string> $copias */
        $copias = [];

        foreach ($lectura->ficheros as $fichero) {
            $destinos->anotar($fichero, $copias[$fichero] = Proyecto::CONTENIDO . "/{$fichero}");
        }

        foreach ($proyecto->ficherosDe(Proyecto::PUBLICO) as $fichero) {
            $origen = Proyecto::PUBLICO . "/{$fichero}";
            FicherosPhp::comprobar($origen);
            $destinos->anotar($fichero, $origen);
            $copias[$fichero] = $origen;
        }

        return $copias;
    }

    /**
     * Lo que se hace al final, con todas las páginas ya hechas: avisar de las
     * portadas que faltan, el sitemap y el feed. Un sitemap o un feed del propio
     * proyecto gana al del motor.
     *
     * @return array<string, string> contenido de cada fichero, por ruta en `salida/`
     */
    public static function ficherosFinales(Lectura $lectura, Colecciones $colecciones, Maquetador $maquetador, Destinos $destinos, Avisos $avisos): array
    {
        self::avisarDeLasPortadas($lectura, $destinos, $avisos);

        $escritos = [];

        // Si el proyecto ya tiene su propio sitemap o feed, gana el suyo.
        if (!$destinos->ocupado('sitemap.xml')) {
            $escritos['sitemap.xml'] = Sitemap::generar($lectura->sitio, $colecciones->coleccion(Colecciones::TODO));
        }

        foreach (Feed::generar($lectura->sitio, $colecciones, $maquetador->cuerpo(...), $avisos, $destinos->ocupado(...)) as $fichero => $feed) {
            $escritos[$fichero] = $feed;
        }

        return $escritos;
    }

    /**
     * En un sitio que declara sus idiomas, avisa de los que no tienen una
     * página en su raíz: el selector de idioma llevaría a una dirección que
     * no existe (formato §15.3).
     */
    private static function avisarDeLasPortadas(Lectura $lectura, Destinos $destinos, Avisos $avisos): void
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
