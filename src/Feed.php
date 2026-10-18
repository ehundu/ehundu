<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * El `feed.xml` del sitio, en Atom (formato §10.2). Solo se genera si
 * `sitio.yml` tiene una sección `feed` con la colección de la que sale.
 *
 * En un sitio en varios idiomas hay uno por idioma, en la raíz de cada uno
 * (formato §15.10): el del predeterminado siempre, y los demás si tienen
 * alguna entrada.
 *
 * @internal
 */
final class Feed
{
    public const int LIMITE_POR_DEFECTO = 20;

    /**
     * La fecha de un feed sin entradas, en la zona del sitio: siempre la
     * misma, para que no cambie de una compilación a otra (formato §10.2).
     */
    private const string SIN_ENTRADAS = '1970-01-01 00:00:00';

    /**
     * @param \Closure(Pagina): string     $cuerpo  da el contenido de una página ya en HTML
     * @param (\Closure(string): bool)|null $ocupado si el proyecto ya tiene algo en ese fichero de
     *                                               salida; entonces gana lo suyo
     * @param (\Closure(string): bool)|null $existe  si alguna página, publicada o no, lleva esa etiqueta; si no,
     *                                               la colección del feed seguramente está mal escrita
     *
     * @return array<string, string> cada feed, por su fichero en `salida/`; ninguno si el sitio no tiene
     */
    public static function generar(Sitio $sitio, Colecciones $colecciones, \Closure $cuerpo, Avisos $avisos, ?\Closure $ocupado = null, ?\Closure $existe = null): array
    {
        $configuracion = self::configuracion($sitio, $avisos);

        if ($configuracion === null) {
            return [];
        }

        if ($existe !== null && !$existe($configuracion[0])) {
            $avisos->registrar(
                "«feed.coleccion» es «{$configuracion[0]}», y ninguna página lleva esa etiqueta; el feed sale vacío",
                Proyecto::SITIO,
            );
        }

        $feeds = [];

        foreach ($sitio->idiomas->codigos() as $idioma) {
            $fichero = ltrim($sitio->idiomas->prefijo($idioma) . '/feed.xml', '/');

            if ($ocupado !== null && $ocupado($fichero)) {
                continue;
            }

            $feed = self::deUnIdioma($sitio, $idioma, $configuracion, $colecciones, $cuerpo, $avisos);

            if ($feed !== null) {
                $feeds[$fichero] = $feed;
            }
        }

        return $feeds;
    }

    /**
     * @param array{0: string, 1: int, 2: array<string, string>} $configuracion colección, límite y título de cada idioma
     * @param \Closure(Pagina): string                           $cuerpo
     *
     * @return string|null null si no es el idioma predeterminado y no tiene entradas
     */
    private static function deUnIdioma(Sitio $sitio, string $idioma, array $configuracion, Colecciones $colecciones, \Closure $cuerpo, Avisos $avisos): ?string
    {
        [$coleccion, $limite, $titulos] = $configuracion;
        // Un idioma sin título propio lleva el nombre del sitio, como un feed sin `titulo`
        $titulo = $titulos[$idioma] ?? $sitio->nombre;
        $entradas = [];

        foreach ($colecciones->coleccion($coleccion, $idioma) as $pagina) {
            if ($pagina->url === false) {
                continue;
            }

            if (!($pagina->campos['fecha'] ?? null) instanceof \DateTimeInterface) {
                $avisos->registrar('La página no tiene fecha y no entra en el feed', Proyecto::CONTENIDO . "/{$pagina->ruta}");

                continue;
            }

            $entradas[] = $pagina;
        }

        if ($entradas === [] && $idioma !== $sitio->idiomas->predeterminado()) {
            return null;
        }

        $entradas = Coleccion::limite(Coleccion::orden($entradas, 'fecha desc'), $limite);
        $inicio = $sitio->absoluta($sitio->idiomas->raiz($idioma));
        $actualizado = $entradas === [] ? new \DateTimeImmutable(self::SIN_ENTRADAS, $sitio->zonaHoraria) : $entradas[0]->campos['fecha'];

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . '<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="' . self::escapar($idioma) . "\">\n"
            . '  <title>' . self::escapar($titulo) . "</title>\n"
            . '  <link href="' . self::escapar($sitio->absoluta($sitio->idiomas->prefijo($idioma) . '/feed.xml')) . "\" rel=\"self\"/>\n"
            . '  <link href="' . self::escapar($inicio) . "\"/>\n"
            . '  <updated>' . self::fecha($actualizado, $sitio) . "</updated>\n"
            . '  <id>' . self::escapar($inicio) . "</id>\n"
            . '  <author><name>' . self::escapar($sitio->nombre) . "</name></author>\n";

        foreach ($entradas as $pagina) {
            $url = $sitio->absoluta((string) $pagina->url);
            $descripcion = $pagina->campos['descripcion'] ?? null;

            $xml .= "  <entry>\n"
                . '    <title>' . self::escapar((string) ($pagina->campos['titulo'] ?? $pagina->url)) . "</title>\n"
                . '    <link href="' . self::escapar($url) . "\"/>\n"
                . '    <updated>' . self::fecha($pagina->campos['fecha'], $sitio) . "</updated>\n"
                . '    <id>' . self::escapar($url) . "</id>\n"
                . (is_string($descripcion) && $descripcion !== '' ? '    <summary>' . self::escapar($descripcion) . "</summary>\n" : '')
                . '    <content type="html">' . self::escapar(self::urlsAbsolutas($cuerpo($pagina), $sitio)) . "</content>\n"
                . "  </entry>\n";
        }

        return $xml . "</feed>\n";
    }

    /**
     * @return array{0: string, 1: int, 2: array<string, string>}|null colección, límite y título de cada idioma
     */
    private static function configuracion(Sitio $sitio, Avisos $avisos): ?array
    {
        $feed = $sitio->campos['feed'] ?? null;

        if ($feed === null) {
            return null;
        }

        if (!is_array($feed) || !is_string($feed['coleccion'] ?? null) || $feed['coleccion'] === '') {
            $avisos->registrar('«feed» necesita la colección de la que sale, por ejemplo feed: { coleccion: blog }; no se genera', Proyecto::SITIO);

            return null;
        }

        $limite = $feed['limite'] ?? self::LIMITE_POR_DEFECTO;

        if (!is_int($limite) || $limite < 1) {
            $avisos->registrar('«feed.limite» tiene que ser un número mayor que cero; se usan ' . self::LIMITE_POR_DEFECTO, Proyecto::SITIO);
            $limite = self::LIMITE_POR_DEFECTO;
        }

        // Un texto vale para todos los idiomas, sin aviso: puede ser el nombre de una marca; o un texto por idioma, con su
        // código como clave (formato §10.2, decisión 150)
        $titulos = (new TextosPorIdioma('feed', 'titulo', 'el', 'los feeds', false, 'el feed de ese idioma se titula como el sitio'))
            ->leer($feed['titulo'] ?? null, null, $sitio->idiomas, $avisos);

        return [$feed['coleccion'], $limite, $titulos];
    }

    /**
     * Los enlaces y las imágenes que empiezan por `/` pasan a llevar la
     * dirección del sitio: un lector de feeds no sabe de qué sitio vienen. En
     * un `srcset`, también cada una de sus direcciones (formato §10.2).
     */
    private static function urlsAbsolutas(string $html, Sitio $sitio): string
    {
        $html = (string) preg_replace_callback(
            '~\b(href|src)="(/(?!/)[^"]*)"~',
            fn (array $partes) => $partes[1] . '="' . htmlspecialchars(
                $sitio->absoluta(html_entity_decode($partes[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            ) . '"',
            $html,
        );

        return (string) preg_replace_callback(
            '~\bsrcset="([^"]*)"~',
            function (array $partes) use ($sitio): string {
                $candidatas = array_map(function (string $candidata) use ($sitio): string {
                    $candidata = trim(html_entity_decode($candidata, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                    if (preg_match('~^(/(?!/)\S*)(.*)$~s', $candidata, $partes) === 1) {
                        $candidata = $sitio->absoluta($partes[1]) . $partes[2];
                    }

                    return htmlspecialchars($candidata, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }, explode(',', $partes[1]));

                return 'srcset="' . implode(', ', $candidatas) . '"';
            },
            $html,
        );
    }

    private static function fecha(\DateTimeInterface $fecha, Sitio $sitio): string
    {
        return \DateTimeImmutable::createFromInterface($fecha)->setTimezone($sitio->zonaHoraria)->format(\DATE_ATOM);
    }

    private static function escapar(string $texto): string
    {
        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
