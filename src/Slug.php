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

    private const array LIGADURAS = [
        'ß' => 'ss',
        'æ' => 'ae',
        'œ' => 'oe',
        'þ' => 'th',
        'ĳ' => 'ij',
    ];

    private const array SIMBOLOS = [
        '·' => '',
        '&' => ' y ',
    ];

    /** @var array<string, string>|null */
    private static ?array $tabla = null;

    public static function de(string $texto): string
    {
        $texto = strtr(self::sinTildes(mb_strtolower($texto, 'UTF-8')), self::SIMBOLOS);
        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);

        return trim($texto, '-');
    }

    /**
     * Quita tildes, diéresis y demás marcas de un texto ya en minúsculas, y
     * deshace las ligaduras: «ñandú» da «nandu» y «straße», «strasse».
     *
     * Vale también para letras descompuestas (una «a» seguida del acento
     * como carácter aparte), que llegan a menudo al pegar texto copiado en
     * un Mac: las marcas que quedan sueltas se quitan.
     */
    public static function sinTildes(string $minusculas): string
    {
        return (string) preg_replace('/\p{Mn}+/u', '', strtr($minusculas, self::tabla()));
    }

    /**
     * @return array<string, string>
     */
    private static function tabla(): array
    {
        if (self::$tabla === null) {
            self::$tabla = self::LIGADURAS;

            foreach (self::LETRAS as $base => $variantes) {
                foreach (mb_str_split($variantes) as $variante) {
                    self::$tabla[$variante] = $base;
                }
            }
        }

        return self::$tabla;
    }
}
