<?php

declare(strict_types=1);

namespace Ehundu;

use Ehundu\Lotes\Estado;
use Ehundu\Lotes\Huella;
use Ehundu\Plantillas\Maquetador;

/**
 * La implementación por defecto de `CompiladorPorLotes` (formato §10.4): cada
 * llamada lee el proyecto entero, que cuesta poco frente a hacer las páginas, y
 * hace las tareas que le quedan al lote hasta agotar su presupuesto. Por dónde
 * va se guarda en `.ehundu/lote.json`.
 *
 * Las tareas son, por este orden, las de una compilación completa: cada página
 * (con su PDF), cada fichero que se copia, el sitemap y el feed, y la limpieza
 * de lo que sobra en `salida/`. Cada una se escribe al hacerla, así que `salida/`
 * mezcla páginas nuevas y viejas hasta que el lote está completo.
 *
 * No se promete en la 1.0 (decisión 128): para repartir una compilación en
 * llamadas, `CompiladorIncrementalEnProceso::avanzar`.
 *
 * @internal
 */
final class CompiladorPorLotesEnProceso implements CompiladorPorLotes
{
    /** Cada cuántas tareas, o segundos, se guarda el estado mientras se avanza. */
    private const int GUARDAR_CADA = 25;
    private const float GUARDAR_CADA_SEGUNDOS = 2.0;

    /**
     * @param \DateTimeImmutable|null $ahora el momento con el que se decide qué está publicado; si falta,
     *                                       el de cada lote nuevo. Se guarda al empezar y no cambia en todo el lote
     */
    public function __construct(
        private readonly ?\DateTimeImmutable $ahora = null,
    ) {
    }

    public function iniciar(Proyecto $proyecto): Lote
    {
        $cerrojo = Estado::bloquear($proyecto);

        if ($cerrojo === null) {
            return $this->ocupado($proyecto);
        }

        try {
            [$estado] = $this->empezar($proyecto);

            return self::lote($estado);
        } finally {
            Estado::soltar($cerrojo);
        }
    }

    public function avanzar(Proyecto $proyecto, float $segundos): Lote
    {
        $inicio = hrtime(true);
        $cerrojo = Estado::bloquear($proyecto);

        if ($cerrojo === null) {
            return $this->ocupado($proyecto);
        }

        try {
            $proyecto->comprobarSalida();
            $estado = Estado::leer($proyecto);

            if ($estado !== null && $estado->completo()) {
                return self::lote($estado);
            }

            $contexto = null;
            $reiniciado = false;

            if ($estado !== null) {
                if (Huella::de($proyecto) !== $estado->huella) {
                    $estado = null;
                    $reiniciado = true;
                } else {
                    $contexto = $this->preparar($proyecto, new \DateTimeImmutable($estado->ahora));

                    // Si el proyecto da otras tareas sin que cambie ningún fichero, no es el mismo lote
                    if (self::tareas($contexto) !== $estado->total) {
                        $estado = null;
                        $contexto = null;
                        $reiniciado = true;
                    }
                }
            }

            if ($estado === null) {
                [$estado, $contexto] = $this->empezar($proyecto);
            }

            $this->trabajar($proyecto, $estado, $contexto, $segundos, $inicio);

            return self::lote($estado, $reiniciado);
        } finally {
            Estado::soltar($cerrojo);
        }
    }

    public function estado(Proyecto $proyecto): ?Lote
    {
        $estado = Estado::leer($proyecto);

        return $estado === null ? null : self::lote($estado);
    }

    public function abandonar(Proyecto $proyecto): void
    {
        if (!is_dir($proyecto->ruta(Estado::CARPETA))) {
            return;
        }

        $cerrojo = Estado::bloquear($proyecto);

        if ($cerrojo === null) {
            return;
        }

        try {
            Estado::olvidar($proyecto);
        } finally {
            Estado::soltar($cerrojo);
        }
    }

    /**
     * Empieza un lote nuevo: lee el proyecto, comprueba lo que detiene una
     * compilación completa antes de escribir nada y guarda el estado.
     *
     * @return array{0: Estado, 1: array{lectura: Lectura, paginas: list<Pagina>, copias: array<string, string>, destinos: Destinos, ahora: \DateTimeImmutable}}
     *
     * @throws ErrorDeProyecto si el proyecto no se puede compilar
     */
    private function empezar(Proyecto $proyecto): array
    {
        $proyecto->comprobarSalida();

        // La huella, antes de leer: si algo cambia mientras se lee, la próxima llamada lo verá
        $huella = Huella::de($proyecto);
        $ahora = $this->ahora ?? new \DateTimeImmutable();
        $contexto = $this->preparar($proyecto, $ahora);

        $estado = new Estado($huella, $ahora->format(\DATE_ATOM), self::tareas($contexto));
        $estado->guardar($proyecto);

        return [$estado, $contexto];
    }

    /**
     * Lo que se lee del proyecto en cada llamada: las páginas que se hacen, los
     * ficheros que se copian y a dónde va cada cosa.
     *
     * @return array{lectura: Lectura, paginas: list<Pagina>, copias: array<string, string>, destinos: Destinos, ahora: \DateTimeImmutable}
     *
     * @throws ErrorDeProyecto si el proyecto no se puede leer o dos cosas van al mismo sitio
     */
    private function preparar(Proyecto $proyecto, \DateTimeImmutable $ahora): array
    {
        $lectura = Constructor::leer($proyecto, $ahora);
        $destinos = new Destinos();
        $paginas = [];

        foreach ($lectura->paginas as $pagina) {
            if ($pagina->url === false || !$pagina->estaPublicada($ahora)) {
                continue;
            }

            $paginas[] = $pagina;
            $destinos->anotar(Url::fichero($pagina->url), Proyecto::CONTENIDO . "/{$pagina->ruta}");

            if ($pagina->urlPdf() !== null && ($ficheroPdf = Url::ficheroPdf($pagina->url)) !== null) {
                $destinos->anotar($ficheroPdf, Proyecto::CONTENIDO . "/{$pagina->ruta}");
            }
        }

        return [
            'lectura' => $lectura,
            'paginas' => $paginas,
            'copias' => Constructor::reunirCopias($proyecto, $lectura, $destinos),
            'destinos' => $destinos,
            'ahora' => $ahora,
        ];
    }

    /**
     * Cuántas tareas tiene el lote: una por página y por copia, el sitemap con
     * el feed, y la limpieza.
     *
     * @param array{paginas: list<Pagina>, copias: array<string, string>} $contexto
     */
    private static function tareas(array $contexto): int
    {
        return count($contexto['paginas']) + count($contexto['copias']) + 2;
    }

    /**
     * Hace tareas hasta que se acaba el presupuesto, siempre al menos una, y
     * guarda por dónde va.
     *
     * @param array{lectura: Lectura, paginas: list<Pagina>, copias: array<string, string>, destinos: Destinos, ahora: \DateTimeImmutable} $contexto
     * @param int $inicio el `hrtime` del comienzo de la llamada, que también gasta presupuesto
     *
     * @throws ErrorDeProyecto si una tarea falla; el estado y lo escrito se quedan como están
     */
    private function trabajar(Proyecto $proyecto, Estado $estado, array $contexto, float $segundos, int $inicio): void
    {
        ['lectura' => $lectura, 'paginas' => $paginas, 'copias' => $copias, 'destinos' => $destinos, 'ahora' => $ahora] = $contexto;

        $colecciones = new Colecciones($lectura->paginas, $ahora, false, $lectura->sitio->idiomas);
        $avisos = new Avisos();
        $maquetador = new Maquetador($proyecto, $lectura, $colecciones, $avisos);

        // Los ficheros de CSS y JS que ya hicieron las llamadas anteriores también ocupan su sitio
        foreach ($estado->paquetes as $paquete => $pagina) {
            $destinos->anotar($paquete, Proyecto::CONTENIDO . "/{$pagina}");
        }

        $vistos = [];

        foreach ($estado->avisos as $aviso) {
            $vistos[(string) $aviso] = true;
        }

        $destinosDeCopias = array_keys($copias);
        $hastaPaginas = count($paginas);
        $hastaCopias = $hastaPaginas + count($copias);
        $limite = Presupuesto::limite($inicio, $segundos);
        $sinGuardar = 0;
        $guardado = hrtime(true);
        $completo = false;

        try {
            do {
                $tarea = $estado->hechas;
                $antes = count($avisos->todos());

                if ($tarea < $hastaPaginas) {
                    $this->hacerPagina($proyecto, $estado, $maquetador, $destinos, $paginas[$tarea]);
                } elseif ($tarea < $hastaCopias) {
                    $destino = $destinosDeCopias[$tarea - $hastaPaginas];
                    $proyecto->copiarSiCambia($copias[$destino], $destino);
                } elseif ($tarea === $hastaCopias) {
                    foreach (Constructor::ficherosFinales($lectura, $colecciones, $maquetador, $destinos, $avisos) as $fichero => $contenido) {
                        $proyecto->escribirSiCambia($fichero, $contenido);
                        $estado->escritos[$fichero] = true;
                    }
                } else {
                    $proyecto->limpiarSalida($estado->escritos + array_fill_keys(array_keys($copias), true));
                    $completo = true;
                }

                $estado->hechas++;

                foreach (array_slice($avisos->todos(), $antes) as $aviso) {
                    // Un aviso que ya dio una llamada anterior no se repite, como no se repite en una compilación completa
                    if (!isset($vistos[(string) $aviso])) {
                        $vistos[(string) $aviso] = true;
                        $estado->avisos[] = $aviso;
                    }
                }

                if (++$sinGuardar >= self::GUARDAR_CADA || (hrtime(true) - $guardado) / 1e9 >= self::GUARDAR_CADA_SEGUNDOS) {
                    $estado->guardar($proyecto);
                    $sinGuardar = 0;
                    $guardado = hrtime(true);
                }
            } while (!$completo && $estado->hechas < $estado->total && hrtime(true) < $limite);
        } finally {
            $estado->segundos += (hrtime(true) - $inicio) / 1e9;

            if ($completo) {
                $estado->informe = [
                    'paginas' => count($paginas),
                    'ficheros' => count($copias),
                    'segundos' => $estado->segundos,
                    'avisos' => [...$lectura->avisos, ...$estado->avisos],
                ];
            }

            $estado->guardar($proyecto);
        }
    }

    /**
     * La tarea de una página: la hace y escribe su HTML, su PDF y los ficheros
     * de CSS y JS que enlaza y que todavía no estaban.
     */
    private function hacerPagina(Proyecto $proyecto, Estado $estado, Maquetador $maquetador, Destinos $destinos, Pagina $pagina): void
    {
        /** @var string $url */
        $url = $pagina->url;
        $fichero = Url::fichero($url);
        $ficheroPdf = $pagina->urlPdf() === null ? null : Url::ficheroPdf($url);
        $hecha = Constructor::hacerPagina($maquetador, $pagina, $ficheroPdf);

        $proyecto->escribirSiCambia($fichero, $hecha['html']);
        $estado->escritos[$fichero] = true;

        if ($ficheroPdf !== null && $hecha['pdf'] !== null) {
            $proyecto->escribirSiCambia($ficheroPdf, $hecha['pdf']);
            $estado->escritos[$ficheroPdf] = true;
        }

        // Las páginas que declaran lo mismo enlazan el mismo fichero: se escribe una vez.
        foreach ($hecha['paquetes'] as $paquete => $contenido) {
            if (!isset($estado->paquetes[$paquete])) {
                $destinos->anotar($paquete, Proyecto::CONTENIDO . "/{$pagina->ruta}");
                $estado->paquetes[$paquete] = $pagina->ruta;
                $proyecto->escribirSiCambia($paquete, $contenido);
                $estado->escritos[$paquete] = true;
            }
        }
    }

    private function ocupado(Proyecto $proyecto): Lote
    {
        $estado = Estado::leer($proyecto);

        return $estado === null
            ? new Lote(0, 0, ocupado: true)
            : self::lote($estado, ocupado: true);
    }

    private static function lote(Estado $estado, bool $reiniciado = false, bool $ocupado = false): Lote
    {
        $informe = $estado->informe === null ? null : new Informe(
            paginas: $estado->informe['paginas'],
            avisos: $estado->informe['avisos'],
            segundos: $estado->informe['segundos'],
            ficheros: $estado->informe['ficheros'],
        );

        return new Lote($estado->hechas, $estado->total, $informe, $reiniciado, $ocupado);
    }
}
