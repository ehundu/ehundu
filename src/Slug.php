<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * El filtro `slug` (formato §5.2): convierte un texto en un tramo de URL.
 *
 * La tabla de letras es propia, sin `intl` ni `iconv`, para que el resultado
 * sea el mismo en cualquier máquina.
 */
final class Slug
{
    private const array LETRAS = [
        'a' => 'àáâãäåāăą',
        'c' => 'çćĉċč',
        'd' => 'ďđð',
        'e' => 'èéêëēĕėęě',
        'g' => 'ĝğġģ',
        'h' => 'ĥħ',
        'i' => 'ìíîïĩīĭįı',
        'j' => 'ĵ',
        'k' => 'ķ',
        'l' => 'ĺļľŀł',
        'n' => 'ñńņňŉ',
        'o' => 'òóôõöøōŏő',
        'r' => 'ŕŗř',
        's' => 'śŝşšſ',
        't' => 'ţťŧ',
        'u' => 'ùúûüũūŭůűų',
        'w' => 'ŵ',
        'y' => 'ýÿŷ',
        'z' => 'źżž',
    ];

    private const array OTRAS = [
        'ß' => 'ss',
        'æ' => 'ae',
        'œ' => 'oe',
        'þ' => 'th',
        'ĳ' => 'ij',
        '·' => '',
        '&' => ' y ',
    ];

    /** @var array<string, string>|null */
    private static ?array $tabla = null;

    public static function de(string $texto): string
    {
        $texto = strtr(mb_strtolower($texto, 'UTF-8'), self::tabla());
        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);

        return trim($texto, '-');
    }

    /**
     * @return array<string, string>
     */
    private static function tabla(): array
    {
        if (self::$tabla === null) {
            self::$tabla = self::OTRAS;

            foreach (self::LETRAS as $base => $variantes) {
                foreach (mb_str_split($variantes) as $variante) {
                    self::$tabla[$variante] = $base;
                }
            }
        }

        return self::$tabla;
    }
}
