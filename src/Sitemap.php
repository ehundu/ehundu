<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * El `sitemap.xml` del sitio (formato §10.1 y §15.10): las páginas HTML de
 * la colección `todo`, de todos los idiomas y en su orden, sin la
 * `404.html` de ninguno, con `lastmod` cuando la página tiene fecha.
 */
final class Sitemap
{
    /**
     * @param list<Pagina> $todo las páginas de la colección `todo`
     */
    public static function generar(Sitio $sitio, array $todo): string
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        $errores = array_map(
            fn (string $codigo) => $sitio->idiomas->prefijo($codigo) . '/404.html',
            $sitio->idiomas->codigos(),
        );

        foreach ($todo as $pagina) {
            if (!is_string($pagina->url) || !self::esHtml($pagina->url) || in_array($pagina->url, $errores, true)) {
                continue;
            }

            $xml .= "  <url>\n    <loc>" . self::escapar($sitio->absoluta($pagina->url)) . "</loc>\n";

            $fecha = $pagina->campos['fecha'] ?? null;
            if ($fecha instanceof \DateTimeInterface) {
                $xml .= '    <lastmod>' . \DateTimeImmutable::createFromInterface($fecha)->setTimezone($sitio->zonaHoraria)->format('Y-m-d') . "</lastmod>\n";
            }

            $xml .= "  </url>\n";
        }

        return $xml . "</urlset>\n";
    }

    private static function esHtml(string $url): bool
    {
        return str_ends_with($url, '/') || str_ends_with(strtolower($url), '.html');
    }

    private static function escapar(string $texto): string
    {
        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
