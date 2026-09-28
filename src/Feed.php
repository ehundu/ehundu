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
 */
final class Feed
{
    public const int LIMITE_POR_DEFECTO = 20;

    /**
     * @param \Closure(Pagina): string     $cuerpo  da el contenido de una página ya en HTML
     * @param \DateTimeImmutable           $ahora   el momento de la construcción: es el `updated` de un
     *                                               feed sin entradas, para que dos construcciones con el
     *                                               mismo momento den el mismo feed
     * @param (\Closure(string): bool)|null $ocupado si el proyecto ya tiene algo en ese fichero de
     *                                               salida; entonces gana lo suyo
     *
     * @return array<string, string> cada feed, por su fichero en `salida/`; ninguno si el sitio no tiene
     */
    public static function generar(Sitio $sitio, Colecciones $colecciones, \Closure $cuerpo, Avisos $avisos, \DateTimeImmutable $ahora, ?\Closure $ocupado = null): array
    {
        $configuracion = self::configuracion($sitio, $avisos);

        if ($configuracion === null) {
            return [];
        }

        $feeds = [];

        foreach ($sitio->idiomas->codigos() as $idioma) {
            $fichero = ltrim($sitio->idiomas->prefijo($idioma) . '/feed.xml', '/');

            if ($ocupado !== null && $ocupado($fichero)) {
                continue;
            }

            $feed = self::deUnIdioma($sitio, $idioma, $configuracion, $colecciones, $cuerpo, $avisos, $ahora);

            if ($feed !== null) {
                $feeds[$fichero] = $feed;
            }
        }

        return $feeds;
    }

    /**
     * @param array{0: string, 1: int, 2: string} $configuracion colección, límite y título
     * @param \Closure(Pagina): string            $cuerpo
     *
     * @return string|null null si no es el idioma predeterminado y no tiene entradas
     */
    private static function deUnIdioma(Sitio $sitio, string $idioma, array $configuracion, Colecciones $colecciones, \Closure $cuerpo, Avisos $avisos, \DateTimeImmutable $ahora): ?string
    {
        [$coleccion, $limite, $titulo] = $configuracion;
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
        $actualizado = $entradas === [] ? $ahora : $entradas[0]->campos['fecha'];

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
     * @return array{0: string, 1: int, 2: string}|null colección, límite y título
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

        $titulo = is_string($feed['titulo'] ?? null) && $feed['titulo'] !== '' ? $feed['titulo'] : $sitio->nombre;

        return [$feed['coleccion'], $limite, $titulo];
    }

    /**
     * Los enlaces y las imágenes que empiezan por `/` pasan a llevar la
     * dirección del sitio: un lector de feeds no sabe de qué sitio vienen.
     */
    private static function urlsAbsolutas(string $html, Sitio $sitio): string
    {
        return (string) preg_replace_callback(
            '~\b(href|src)="(/(?!/)[^"]*)"~',
            fn (array $partes) => $partes[1] . '="' . htmlspecialchars(
                $sitio->absoluta(html_entity_decode($partes[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            ) . '"',
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
