<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * El filtro `fecha` (formato §7.3): las mismas letras que el filtro `date` de
 * Twig y la función `date()` de PHP, con los nombres de meses y días en
 * español, y siempre en la zona horaria del sitio.
 */
final class Fecha
{
    /** «18 de marzo de 2025». */
    public const string FORMATO = 'j \d\e F \d\e Y';

    private const array MESES = [
        1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
    ];

    private const array MESES_CORTOS = [
        1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic',
    ];

    private const array DIAS = [1 => 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];

    private const array DIAS_CORTOS = [1 => 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'];

    /**
     * @param mixed $valor una fecha, un texto que PHP entienda como fecha, una
     *                     marca de tiempo o nada (que da un texto vacío)
     *
     * @throws ErrorDeProyecto si el valor no es una fecha
     */
    public static function formatear(mixed $valor, string $formato, \DateTimeZone $zona): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        $fecha = self::fecha($valor, $zona)->setTimezone($zona);
        $resultado = '';

        for ($i = 0, $longitud = strlen($formato); $i < $longitud; $i++) {
            $letra = $formato[$i];

            if ($letra === '\\') {
                $resultado .= $formato[++$i] ?? '';

                continue;
            }

            $resultado .= match ($letra) {
                'F' => self::MESES[(int) $fecha->format('n')],
                'M' => self::MESES_CORTOS[(int) $fecha->format('n')],
                'l' => self::DIAS[(int) $fecha->format('N')],
                'D' => self::DIAS_CORTOS[(int) $fecha->format('N')],
                default => $fecha->format($letra),
            };
        }

        return $resultado;
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
