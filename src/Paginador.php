<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Parte en trozos la lista de cada página con `paginar` (formato §6.4): la
 * propia página es la primera, en su `url`, y el resto las genera el motor, una
 * por trozo, con `paginas` (las de su trozo) y `paginacion` (dónde está y
 * adónde ir).
 *
 * Trabaja sobre lo que ha leído el `Lector`, después de `Agrupador` y antes de
 * que nadie construya colecciones, URL o nada más. Lo que sale es una lectura
 * como cualquier otra, con las páginas generadas entre las demás: el resto de la
 * compilación, incluida la incremental, no sabe que existen las series.
 *
 * Las páginas que genera no entran en ninguna colección ni en el sitemap
 * (`listada: no`), así que ninguna página puede depender de ellas por una
 * colección.
 *
 * @internal
 */
final class Paginador
{
    /** Para saber si el patrón de `paginar.url` usa el número: `{{ numero }}` o `{{ numero|slug }}`. */
    private const string USA_EL_NUMERO = '/\{\{\s*numero\s*(?:\|\s*slug\s*)?\}\}/';

    public function expandir(Lectura $lectura, \DateTimeImmutable $ahora, bool $conBorradores = false): Lectura
    {
        $definidoras = array_filter($lectura->paginas, self::parteUnaLista(...));

        if ($definidoras === []) {
            return $lectura;
        }

        $avisos = new Avisos();
        $colecciones = new Colecciones($lectura->paginas, $ahora, $conBorradores, $lectura->sitio->idiomas);

        /** @var array<string, list<Pagina>> $series las páginas de cada serie, por la ruta de la primera */
        $series = [];

        foreach ($definidoras as $definidora) {
            $serie = $this->partir($definidora, $colecciones, $lectura->sitio, $avisos);

            if ($serie !== null) {
                $series[$definidora->ruta] = $serie;
            }
        }

        $paginas = [];

        foreach ($lectura->paginas as $pagina) {
            foreach ($series[$pagina->ruta] ?? [$pagina] as $resultante) {
                $paginas[] = $resultante;
            }
        }

        usort($paginas, fn (Pagina $a, Pagina $b): int => strcmp($a->ruta, $b->ruta));

        return new Lectura(
            $lectura->sitio,
            $lectura->datos,
            $paginas,
            [...$lectura->avisos, ...$avisos->todos()],
            $lectura->ficheros,
            $lectura->datosPorIdioma,
        );
    }

    /**
     * Si la página pide partir una lista y dice de dónde sale y de a cuántas.
     * Lo que falta ya se avisó al leerla, con su línea.
     */
    private static function parteUnaLista(Pagina $pagina): bool
    {
        $definicion = $pagina->campos['paginar'] ?? null;

        return is_array($definicion) && isset($definicion['coleccion'], $definicion['porPagina']);
    }

    /**
     * Las páginas de la serie de una página con `paginar`, la primera entre
     * ellas; null si no se puede partir, y entonces la página es una más.
     *
     * @return list<Pagina>|null
     */
    private function partir(Pagina $definidora, Colecciones $colecciones, Sitio $sitio, Avisos $avisos): ?array
    {
        $fichero = Proyecto::CONTENIDO . "/{$definidora->ruta}";
        $definicion = $definidora->campos['paginar'];

        if ($definidora->url === false) {
            $avisos->registrar('Una página con «paginar» necesita una «url»: este es un fragmento (url: false); no se parte nada', $fichero);

            return null;
        }

        $patron = $definicion['url'] ?? null;

        if ($patron !== null && preg_match(self::USA_EL_NUMERO, $patron) !== 1) {
            $avisos->registrar(
                'Una «url» de «paginar» tiene que usar {{ numero }}: sin él, todas las páginas irían al mismo fichero; no se parte nada',
                $fichero,
            );

            return null;
        }

        $miembros = self::miembros($definidora, $definicion, $colecciones, $avisos, $fichero);
        $porPagina = $definicion['porPagina'];
        $cantidad = count($miembros);
        $total = max(1, intdiv($cantidad + $porPagina - 1, $porPagina));
        $urls = $this->urls($definidora, $patron, $total, $sitio, $avisos, $fichero);

        if ($urls === null) {
            return null;
        }

        // La lista de enlaces es la misma en todas las páginas salvo la que está
        // marcada: PHP comparte lo que no se toca, y una serie larga no repite
        // su índice entero en cada página.
        $enlaces = array_map(
            fn (int $otro): array => ['numero' => $otro, 'url' => $urls[$otro], 'actual' => false],
            range(1, $total),
        );
        $serie = [];

        for ($numero = 1; $numero <= $total; $numero++) {
            $campos = $definidora->campos;
            unset($campos['paginar']);
            $enlacesDeEsta = $enlaces;
            $enlacesDeEsta[$numero - 1]['actual'] = true;
            $campos['paginacion'] = [
                'actual' => $numero,
                'total' => $total,
                'porPagina' => $porPagina,
                'cantidad' => $cantidad,
                'primera' => $urls[1],
                'ultima' => $urls[$total],
                'anterior' => $urls[$numero - 1] ?? null,
                'siguiente' => $urls[$numero + 1] ?? null,
                'enlaces' => $enlacesDeEsta,
            ];

            $rutas = array_map(
                fn (Pagina $pagina): string => $pagina->ruta,
                array_slice($miembros, ($numero - 1) * $porPagina, $porPagina),
            );

            if ($numero === 1) {
                $serie[] = new Pagina($definidora->ruta, $definidora->formato, $campos, $urls[1], $definidora->cuerpo, $definidora->lineaCuerpo, $definidora->idioma, $rutas);

                continue;
            }

            // Se llega a las demás por los enlaces de la paginación: no salen en listados ni en el sitemap.
            $campos['listada'] = false;

            if (($campos['pdf'] ?? false) === true && Url::ficheroPdf($urls[$numero]) === null) {
                $avisos->registrar('«pdf» solo vale en una página cuya URL acaba en / o en .html; no se genera el PDF', $fichero);
                $campos['pdf'] = false;
            }

            $serie[] = new Pagina("{$definidora->ruta}#{$numero}", $definidora->formato, $campos, $urls[$numero], $definidora->cuerpo, $definidora->lineaCuerpo, $definidora->idioma, $rutas);
        }

        return $serie;
    }

    /**
     * Las páginas que se reparten, en el orden pedido: las de la colección en
     * el idioma de la página, sin la propia página, que lleva su etiqueta si
     * la hereda de la carpeta pero no se lista a sí misma.
     *
     * @param array<string, mixed> $definicion
     *
     * @return list<Pagina>
     */
    private static function miembros(Pagina $definidora, array $definicion, Colecciones $colecciones, Avisos $avisos, string $fichero): array
    {
        $miembros = array_values(array_filter(
            $colecciones->coleccion($definicion['coleccion'], $definidora->idioma),
            fn (Pagina $pagina): bool => $pagina->ruta !== $definidora->ruta,
        ));

        $criterio = $definicion['orden'] ?? null;

        if ($criterio === null) {
            return $miembros;
        }

        try {
            return Coleccion::orden($miembros, $criterio);
        } catch (ErrorDeProyecto) {
            $avisos->registrar(
                "«paginar»: «orden» no entiende «{$criterio}»; cada criterio es un campo seguido, si acaso, de asc o desc. Se usa el orden de la colección",
                $fichero,
            );

            return $miembros;
        }
    }

    /**
     * La URL de cada página de la serie, desde la 1: la de la propia página y,
     * para las demás, las del patrón de `paginar.url` o, sin él, la de la
     * primera más `pagina/2/`.
     *
     * @return array<int, string>|null null si alguna no vale; ya se ha avisado
     */
    private function urls(Pagina $definidora, ?string $patron, int $total, Sitio $sitio, Avisos $avisos, string $fichero): ?array
    {
        /** @var string $primera */
        $primera = $definidora->url;
        $urls = [1 => $primera];
        $prefijo = $sitio->idiomas->prefijo($definidora->idioma);

        for ($numero = 2; $numero <= $total; $numero++) {
            if ($patron === null) {
                if (!str_ends_with($primera, '/')) {
                    $avisos->registrar(
                        "La «url» de una página con «paginar» tiene que acabar en / para que las demás salgan en {$primera}pagina/2/; "
                        . 'si no, se escribe «paginar.url». No se parte nada',
                        $fichero,
                    );

                    return null;
                }

                $urls[$numero] = "{$primera}pagina/{$numero}/";

                continue;
            }

            $url = Url::resolverDePagina($patron, $definidora->campos, $numero, $fichero, $avisos, $prefijo, $definidora->clave() . ".{$definidora->formato}");

            if ($url === null) {
                return null;
            }

            $urls[$numero] = $url;
        }

        return $urls;
    }
}
