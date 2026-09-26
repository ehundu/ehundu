<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * La URL de cada página y el fichero de `salida/` que le corresponde
 * (formato §5).
 */
final class Url
{
    private const array FILTROS_SLUG = ['slug', 'slugify'];

    /**
     * La URL de una página: la de su campo `url` si vale, y si no, la que sale
     * de su ruta. `false` si la página es un fragmento.
     *
     * @param string                  $ruta    ruta dentro de `contenido/`
     * @param array<array-key, mixed> $campos  los campos ya combinados con la cascada
     * @param string                  $fichero ruta relativa a la raíz del proyecto, para los avisos
     */
    public static function resolver(string $ruta, array $campos, string $fichero, Avisos $avisos): string|false
    {
        $escrita = $campos['url'] ?? null;

        if ($escrita === false) {
            return false;
        }

        if (is_string($escrita) && $escrita !== '') {
            $url = self::aplicarPatron($escrita, $campos, $fichero, $avisos);
            $url = $url === null ? null : self::normalizar($url, $fichero, $avisos);

            if ($url !== null) {
                return $url;
            }
        }

        return self::derivada($ruta, $fichero, $avisos);
    }

    /**
     * La URL que sale de la ruta: sin extensión y con barra final; un
     * `index` da la de su carpeta.
     */
    public static function derivada(string $ruta, string $fichero, Avisos $avisos): string
    {
        $tramos = explode('/', substr($ruta, 0, (int) strrpos($ruta, '.')));

        if (end($tramos) === 'index') {
            array_pop($tramos);
        }

        $url = $tramos === [] ? '/' : '/' . implode('/', $tramos) . '/';

        if (preg_match('#[^a-z0-9._/-]#', $url) === 1) {
            $avisos->registrar(
                "La URL que sale de la ruta tiene espacios, mayúsculas u otros caracteres especiales: {$url}. "
                . 'Conviene renombrar el fichero o darle un «url»',
                $fichero,
            );
        }

        return $url;
    }

    /**
     * El fichero de `salida/` que corresponde a una URL: '/blog/' da
     * 'blog/index.html' y '/404.html' da '404.html'.
     */
    public static function fichero(string $url): string
    {
        $relativa = ltrim($url, '/');

        return $relativa === '' || str_ends_with($relativa, '/') ? "{$relativa}index.html" : $relativa;
    }

    /**
     * @param list<Pagina> $paginas
     *
     * @throws ErrorDeProyecto si dos páginas que se publican van al mismo fichero
     */
    public static function comprobarColisiones(array $paginas, \DateTimeImmutable $ahora, bool $conBorradores = false): void
    {
        /** @var array<string, Pagina> $porFichero */
        $porFichero = [];

        foreach ($paginas as $pagina) {
            if ($pagina->url === false || !$pagina->estaPublicada($ahora, $conBorradores)) {
                continue;
            }

            $fichero = self::fichero($pagina->url);
            $clave = mb_strtolower($fichero, 'UTF-8');
            $otra = $porFichero[$clave] ?? null;

            if ($otra !== null) {
                throw new ErrorDeProyecto(sprintf(
                    'Dos páginas van al mismo fichero de salida, %s: %s (%s) y %s (%s)',
                    $fichero,
                    Proyecto::CONTENIDO . "/{$otra->ruta}",
                    $otra->url,
                    Proyecto::CONTENIDO . "/{$pagina->ruta}",
                    $pagina->url,
                ), Proyecto::CONTENIDO . "/{$pagina->ruta}");
            }

            $porFichero[$clave] = $pagina;
        }
    }

    /**
     * Sustituye `{{ campo }}` y `{{ campo|slug }}` (formato §5.1).
     *
     * @param array<array-key, mixed> $campos
     *
     * @return string|null null si el patrón no vale; ya se ha avisado
     */
    private static function aplicarPatron(string $patron, array $campos, string $fichero, Avisos $avisos): ?string
    {
        $problema = str_contains($patron, '{%') ? '«url» no admite etiquetas {% %}' : null;

        $url = preg_replace_callback('/\{\{(.*?)\}\}/s', function (array $llaves) use ($campos, &$problema): string {
            if ($problema !== null) {
                return '';
            }

            if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*(?:\|\s*([A-Za-z_]+)\s*)?$/', $llaves[1], $partes) !== 1) {
                $problema = "«url» solo admite {{ campo }} y {{ campo|slug }}, no {$llaves[0]}";

                return '';
            }

            $escrito = $partes[1];
            $filtro = $partes[2] ?? '';
            $valor = $campos[Campos::ALIAS[$escrito] ?? $escrito] ?? null;

            if ($filtro !== '' && !in_array($filtro, self::FILTROS_SLUG, true)) {
                $problema = "«url» no conoce el filtro «{$filtro}»; solo existe «slug»";

                return '';
            }

            if (!is_string($valor) && !is_int($valor) && !is_float($valor)) {
                $problema = "«url» usa «{$escrito}», que la página no tiene o no es un texto";

                return '';
            }

            $valor = (string) $valor;

            if ($filtro !== '') {
                $valor = Slug::de($valor);

                if ($valor === '') {
                    $problema = "«url» pasa «{$escrito}» por slug y sale vacío";
                }
            }

            return $valor;
        }, $patron);

        if ($problema === null && (str_contains($url, '{{') || str_contains($url, '}}'))) {
            $problema = '«url» tiene unas llaves sin cerrar';
        }

        if ($problema !== null) {
            $avisos->registrar("{$problema}; se usa la URL que sale de la ruta", $fichero);

            return null;
        }

        return $url;
    }

    /**
     * @return string|null null si la URL no vale como ruta de fichero; ya se ha avisado
     */
    private static function normalizar(string $url, string $fichero, Avisos $avisos): ?string
    {
        if (!str_starts_with($url, '/')) {
            $avisos->registrar("«url» tiene que empezar por /; se toma como /{$url}", $fichero);
            $url = "/{$url}";
        }

        $url = preg_replace('#/{2,}#', '/', $url);
        $tramos = explode('/', substr($url, 1));

        foreach ($tramos as $tramo) {
            if ($tramo === '.' || $tramo === '..' || preg_match('/[\\\\?#:*"<>|\x00-\x1F]/', $tramo) === 1) {
                $avisos->registrar("La URL {$url} no vale como ruta de fichero; se usa la que sale de la ruta", $fichero);

                return null;
            }
        }

        $ultimo = end($tramos);

        if ($ultimo !== '' && preg_match('/\.[A-Za-z0-9]+$/', $ultimo) !== 1) {
            $avisos->registrar("«url» no acaba en / ni en un fichero con extensión; se toma como {$url}/", $fichero);
            $url .= '/';
        }

        return $url;
    }
}
