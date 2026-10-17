<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Sustituye cada página con `agrupar` por las que genera (formato §6.3): una
 * por cada valor distinto de un campo en las páginas de una colección. Es lo
 * que permite tener una página por etiqueta sin escribir una por una.
 *
 * Trabaja sobre lo que ha leído el `Lector` y antes de que nadie construya
 * colecciones, URL o nada más. Lo que sale es una lectura como cualquier
 * otra, con las páginas generadas entre las demás: el resto de la compilación,
 * incluida la incremental, no sabe que existen las plantillas de páginas.
 *
 * @internal
 */
final class Agrupador
{
    /** Para saber si el patrón de `url` usa el grupo: `{{ grupo }}` o `{{ grupo|slug }}` (`slugify` es el mismo filtro). */
    private const string USA_EL_GRUPO = '/\{\{\s*grupo\s*(?:\|\s*(?:slug|slugify)\s*)?\}\}/';

    /**
     * @throws ErrorDeProyecto si una plantilla de páginas depende de las que se generan
     */
    public function expandir(Lectura $lectura, \DateTimeImmutable $ahora, bool $conBorradores = false): Lectura
    {
        $plantillas = [];
        $reales = [];

        foreach ($lectura->paginas as $pagina) {
            if (is_array($pagina->campos['agrupar'] ?? null)) {
                $plantillas[] = $pagina;
            } else {
                $reales[] = $pagina;
            }
        }

        if ($plantillas === []) {
            return $lectura;
        }

        self::comprobarQueNoHayVueltas($plantillas);

        $avisos = new Avisos();

        // Se agrupa lo que hay sin ellas: una página generada nunca es una de las que se agrupan.
        $colecciones = new Colecciones($reales, $ahora, $conBorradores, $lectura->sitio->idiomas);
        $paginas = $reales;

        foreach ($plantillas as $plantilla) {
            foreach ($this->generar($plantilla, $colecciones, $lectura->sitio, $avisos) as $generada) {
                $paginas[] = $generada;
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
     * Las páginas que genera una plantilla de páginas, por el `slug` de su grupo.
     *
     * @return list<Pagina>
     */
    private function generar(Pagina $plantilla, Colecciones $colecciones, Sitio $sitio, Avisos $avisos): array
    {
        $definicion = $plantilla->campos['agrupar'];
        $coleccion = $definicion['coleccion'] ?? null;
        $campo = $definicion['campo'] ?? null;

        // Lo que falta ya se avisó al leer la página, con su línea.
        if ($coleccion === null || $campo === null) {
            return [];
        }

        $fichero = Proyecto::CONTENIDO . "/{$plantilla->ruta}";
        $patron = $plantilla->campos['url'] ?? null;

        if (!is_string($patron) || preg_match(self::USA_EL_GRUPO, $patron) !== 1) {
            $avisos->registrar(
                'Una página con «agrupar» necesita una «url» que use {{ grupo|slug }}: sin él, todas las páginas que genera irían al mismo fichero; no se genera ninguna',
                $fichero,
            );

            return [];
        }

        $campo = Campos::ALIAS[$campo] ?? $campo;
        $excluidos = array_flip(array_filter(
            array_map(Slug::de(...), $definicion['excluir'] ?? []),
            fn (string $slug) => $slug !== '',
        ));

        /** @var array<string, array<string, array<string, true>>> $usos por slug, variante y página que la usa */
        $usos = [];

        /** @var array<string, array<string, true>> $miembros por slug y página, en el orden de la colección */
        $miembros = [];

        foreach ($colecciones->coleccion($coleccion, $plantilla->idioma) as $pagina) {
            foreach (self::valoresDe($pagina, $campo, $avisos) as $valor) {
                $slug = Slug::de($valor);

                if ($slug === '') {
                    $avisos->registrar(
                        "«{$valor}», un valor de «{$campo}», no deja ninguna letra ni número para la URL; no forma parte de ningún grupo",
                        Proyecto::CONTENIDO . "/{$pagina->ruta}",
                    );

                    continue;
                }

                if (isset($excluidos[$slug])) {
                    continue;
                }

                $usos[$slug][$valor][$pagina->ruta] = true;
                $miembros[$slug][$pagina->ruta] = true;
            }
        }

        ksort($usos, SORT_STRING);

        $prefijo = $sitio->idiomas->prefijo($plantilla->idioma);
        $generadas = [];

        foreach ($usos as $slug => $variantes) {
            $slug = (string) $slug;
            $nombre = self::nombreDelGrupo($variantes);

            $campos = $plantilla->campos;
            unset($campos['agrupar']);
            $campos['grupo'] = $nombre;

            if (($campos['titulo'] ?? '') === '') {
                $campos['titulo'] = $nombre;
            }

            $url = Url::resolverDeGrupo($patron, $campos, $fichero, $avisos, $prefijo, $plantilla->clave() . ".{$plantilla->formato}");

            if ($url === null) {
                continue;
            }

            if (($campos['pdf'] ?? false) === true && Url::ficheroPdf($url) === null) {
                $avisos->registrar('«pdf» solo vale en una página cuya URL acaba en / o en .html; no se genera el PDF', $fichero);
                $campos['pdf'] = false;
            }

            $generadas[] = new Pagina(
                "{$plantilla->ruta}#{$slug}",
                $plantilla->formato,
                $campos,
                $url,
                $plantilla->cuerpo,
                $plantilla->lineaCuerpo,
                $plantilla->idioma,
                array_map(strval(...), array_keys($miembros[$slug])),
            );
        }

        return $generadas;
    }

    /**
     * Los valores de un campo de una página como textos, uno por elemento si
     * es una lista. Lo que no es un texto ni un número, o está en blanco, se
     * avisa y se deja fuera.
     *
     * @return list<string>
     */
    private static function valoresDe(Pagina $pagina, string $campo, Avisos $avisos): array
    {
        $valor = $pagina->valor($campo);

        if ($valor === null) {
            return [];
        }

        $textos = [];

        foreach (is_array($valor) && array_is_list($valor) ? $valor : [$valor] as $elemento) {
            if (is_int($elemento) || is_float($elemento)) {
                $elemento = (string) $elemento;
            }

            if (!is_string($elemento)) {
                $avisos->registrar(
                    "«{$campo}» tiene un valor que no es un texto ni un número; no forma parte de ningún grupo",
                    Proyecto::CONTENIDO . "/{$pagina->ruta}",
                );
            } elseif (trim($elemento) === '') {
                $avisos->registrar(
                    "«{$campo}» tiene un valor en blanco; no forma parte de ningún grupo",
                    Proyecto::CONTENIDO . "/{$pagina->ruta}",
                );
            } else {
                $textos[] = $elemento;
            }
        }

        return $textos;
    }

    /**
     * Cómo se llama el grupo: la variante que más páginas usan y, a igualdad,
     * la primera en el orden de `orden` (formato §6.2). Si aun así empatan
     * (`Ideas` e `ideas`), la de menor código de carácter, para que el nombre
     * no dependa del orden en que llegan las páginas.
     *
     * @param array<string, array<string, true>> $variantes las páginas que usa cada variante
     */
    private static function nombreDelGrupo(array $variantes): string
    {
        $mejor = null;
        $paginasDelMejor = 0;

        foreach ($variantes as $variante => $paginas) {
            $variante = (string) $variante;
            $cuantas = count($paginas);

            if ($mejor !== null && $cuantas === $paginasDelMejor) {
                $orden = Coleccion::comparar($variante, $mejor) ?: strcmp($variante, $mejor);

                if ($orden >= 0) {
                    continue;
                }
            } elseif ($mejor !== null && $cuantas < $paginasDelMejor) {
                continue;
            }

            $mejor = $variante;
            $paginasDelMejor = $cuantas;
        }

        return (string) $mejor;
    }

    /**
     * Una plantilla de páginas no puede agrupar páginas que genera una (ella
     * misma u otra): no habría por dónde empezar. Pasa si agrupa `todo`, o una
     * colección a la que pertenecen las páginas generadas por la etiqueta
     * `etiquetas` de la plantilla, que ellas heredan.
     *
     * @param list<Pagina> $plantillas
     *
     * @throws ErrorDeProyecto
     */
    private static function comprobarQueNoHayVueltas(array $plantillas): void
    {
        foreach ($plantillas as $agrupa) {
            $coleccion = $agrupa->campos['agrupar']['coleccion'] ?? null;

            if ($coleccion === null) {
                continue;
            }

            foreach ($plantillas as $genera) {
                $definicion = $genera->campos['agrupar'];

                // Las colecciones están acotadas al idioma: las de otro no se ven.
                $generaPaginas = $genera->idioma === $agrupa->idioma
                    && ($genera->campos['listada'] ?? true) !== false
                    && isset($definicion['coleccion'], $definicion['campo']);

                if ($generaPaginas && ($coleccion === Colecciones::TODO || in_array($coleccion, $genera->campos['etiquetas'] ?? [], true))) {
                    throw new ErrorDeProyecto(
                        sprintf(
                            'Una página con «agrupar» agrupa una colección a la que pertenecen páginas que genera una plantilla de grupos (ella misma u otra): %s → «%s» → %s',
                            Proyecto::CONTENIDO . "/{$agrupa->ruta}",
                            $coleccion,
                            Proyecto::CONTENIDO . "/{$genera->ruta}",
                        ),
                        Proyecto::CONTENIDO . "/{$agrupa->ruta}",
                    );
                }
            }
        }
    }
}
