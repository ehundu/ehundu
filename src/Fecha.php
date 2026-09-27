<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * El filtro `fecha` (formato §7.3 y §15.9): las mismas letras que el filtro
 * `date` de Twig y la función `date()` de PHP, con los nombres de meses y
 * días en el idioma de la página, y siempre en la zona horaria del sitio.
 *
 * Trae castellano, euskera e inglés. Con cualquier otro idioma, los nombres
 * salen en inglés, que son los de PHP.
 */
final class Fecha
{
    /** «18 de marzo de 2025». */
    public const string FORMATO = 'j \d\e F \d\e Y';

    /** «18 March 2025», también en los idiomas que no trae el motor. */
    private const string FORMATO_EN_INGLES = 'j F Y';

    /** Los idiomas que trae el motor. */
    private const array IDIOMAS = ['es', 'eu', 'en'];

    /**
     * Meses, meses cortos, días y días cortos de cada idioma, salvo el
     * inglés, que da PHP.
     */
    private const array NOMBRES = [
        'es' => [
            'F' => [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
            'M' => [1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'],
            'l' => [1 => 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'],
            'D' => [1 => 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'],
        ],
        'eu' => [
            'F' => [1 => 'urtarrila', 'otsaila', 'martxoa', 'apirila', 'maiatza', 'ekaina',
                'uztaila', 'abuztua', 'iraila', 'urria', 'azaroa', 'abendua'],
            'M' => [1 => 'urt', 'ots', 'mar', 'api', 'mai', 'eka', 'uzt', 'abu', 'ira', 'urr', 'aza', 'abe'],
            'l' => [1 => 'astelehena', 'asteartea', 'asteazkena', 'osteguna', 'ostirala', 'larunbata', 'igandea'],
            'D' => [1 => 'al', 'ar', 'az', 'og', 'or', 'lr', 'ig'],
        ],
    ];

    /**
     * Si el motor trae los nombres de ese idioma.
     */
    public static function conoce(string $idioma): bool
    {
        return in_array($idioma, self::IDIOMAS, true);
    }

    /**
     * @param mixed       $valor   una fecha, un texto que PHP entienda como fecha, una
     *                             marca de tiempo o nada (que da un texto vacío)
     * @param string|null $formato las letras de `date()`, o null para el formato por
     *                             defecto del idioma
     * @param string      $idioma  el código del idioma de la página
     *
     * @throws ErrorDeProyecto si el valor no es una fecha
     */
    public static function formatear(mixed $valor, ?string $formato, \DateTimeZone $zona, string $idioma = Idiomas::PREDETERMINADO): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        $fecha = self::fecha($valor, $zona)->setTimezone($zona);

        if ($formato === null && $idioma === 'eu') {
            return self::enEuskera($fecha);
        }

        $formato ??= $idioma === 'es' ? self::FORMATO : self::FORMATO_EN_INGLES;
        $nombres = self::NOMBRES[$idioma] ?? [];
        $resultado = '';

        for ($i = 0, $longitud = strlen($formato); $i < $longitud; $i++) {
            $letra = $formato[$i];

            if ($letra === '\\') {
                $resultado .= $formato[++$i] ?? '';

                continue;
            }

            $resultado .= match (true) {
                isset($nombres[$letra]) && in_array($letra, ['F', 'M'], true) => $nombres[$letra][(int) $fecha->format('n')],
                isset($nombres[$letra]) => $nombres[$letra][(int) $fecha->format('N')],
                default => $fecha->format($letra),
            };
        }

        return $resultado;
    }

    /**
     * «2025eko martxoaren 18a». El año lleva `-ko` o `-eko` según cómo se
     * lee su final: `-eko` tras bat (1), bost (5), hamar (10), hamabost (15),
     * sus compuestos (hogeita bat, hogeita hamar…) y los cientos (ehun);
     * `-ko` tras los demás y tras mila (2000).
     */
    private static function enEuskera(\DateTimeImmutable $fecha): string
    {
        $ano = (int) $fecha->format('Y');
        $final = abs($ano) % 100;

        $eko = $final === 0
            ? abs($ano) % 1000 !== 0
            : in_array($final % 20, [1, 5, 10, 15], true);

        return sprintf(
            '%d%s %sren %da',
            $ano,
            $eko ? 'eko' : 'ko',
            self::NOMBRES['eu']['F'][(int) $fecha->format('n')],
            (int) $fecha->format('j'),
        );
    }

    private static function fecha(mixed $valor, \DateTimeZone $zona): \DateTimeImmutable
    {
        if ($valor instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($valor);
        }

        if (is_int($valor)) {
            return new \DateTimeImmutable("@{$valor}");
        }

        if (is_string($valor)) {
            try {
                return new \DateTimeImmutable($valor, $zona);
            } catch (\Exception) {
                // Se avisa abajo, igual que con cualquier otro valor.
            }
        }

        $escrito = is_scalar($valor) ? (string) $valor : get_debug_type($valor);

        throw new ErrorDeProyecto("fecha: «{$escrito}» no es una fecha");
    }
}
