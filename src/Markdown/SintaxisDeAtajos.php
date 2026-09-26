<?php

declare(strict_types=1);

namespace Ehundu\Markdown;

/**
 * La forma de un atajo (formato §8.1): `[nombre atributo="valor"]`, y para
 * los que envuelven contenido, una línea `[nombre ...]` y otra `[/nombre]`.
 *
 * @internal
 */
final class SintaxisDeAtajos
{
    public const string NOMBRE = '[a-z][a-z0-9-]*';

    /** Cero o más atributos `nombre="valor"` o `nombre='valor'`. */
    public const string ATRIBUTOS = '(?:\s+[A-Za-z][A-Za-z0-9]*\s*=\s*(?:"[^"]*"|\'[^\']*\'))*';

    /**
     * Un atajo en medio del texto, o su cierre. No lo es si le sigue `(`,
     * `[` o `:`, porque entonces es un enlace de Markdown.
     */
    public const string EN_LINEA = '\[(\/?)(' . self::NOMBRE . ')(' . self::ATRIBUTOS . ')\s*\](?![(\[:])';

    /** Una línea que solo tiene la apertura de un atajo. */
    public const string APERTURA = '/^[ \t]{0,3}\[(' . self::NOMBRE . ')(' . self::ATRIBUTOS . ')\s*\]\s*$/';

    /**
     * @return array<string, string>
     */
    public static function atributos(string $texto): array
    {
        preg_match_all(
            '/([A-Za-z][A-Za-z0-9]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/',
            $texto,
            $partes,
            PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL,
        );

        $atributos = [];

        foreach ($partes as $parte) {
            $atributos[(string) $parte[1]] = (string) ($parte[2] ?? $parte[3]);
        }

        return $atributos;
    }

    /**
     * Los nombres de los atajos que tienen una línea de cierre en el texto,
     * y por tanto envuelven contenido.
     *
     * @return list<string>
     */
    public static function conCierre(string $markdown): array
    {
        preg_match_all('/^[ \t]{0,3}\[\/(' . self::NOMBRE . ')\]\s*$/m', $markdown, $partes);

        return array_values(array_unique($partes[1]));
    }

    public static function esCierre(string $linea, string $nombre): bool
    {
        return preg_match('/^[ \t]{0,3}\[\/' . preg_quote($nombre, '/') . '\]\s*$/', $linea) === 1;
    }
}
