<?php

declare(strict_types=1);

namespace Ehundu\Plantillas;

/**
 * La etiqueta que marca una página como hecha con Ehundu (formato §2, clave
 * `generador`): `<meta name="generator" content="Ehundu">` justo antes del
 * primer `</head>`.
 *
 * Sin versión a propósito: con ella, cada actualización del motor cambiaría
 * todas las páginas de todos los sitios (decisión 127).
 *
 * @internal
 */
final class Generador
{
    public const string ETIQUETA = '<meta name="generator" content="Ehundu">';

    /**
     * El HTML con la etiqueta añadida. Queda igual si no tiene `</head>` (un
     * fragmento, un XML, un texto) o si ya trae una etiqueta `generator`:
     * manda la plantilla.
     */
    public static function marcar(string $html): string
    {
        if (preg_match('~</head\s*>~i', $html, $cierre, PREG_OFFSET_CAPTURE) !== 1) {
            return $html;
        }

        $posicion = $cierre[0][1];
        $cabecera = substr($html, 0, $posicion);

        if (preg_match('~<meta\b[^>]*\bname\s*=\s*(["\']?)generator\1[\s/>]~i', $cabecera) === 1) {
            return $html;
        }

        $inicioDeLinea = strrpos($cabecera, "\n");
        $inicioDeLinea = $inicioDeLinea === false ? 0 : $inicioDeLinea + 1;
        $antes = substr($cabecera, $inicioDeLinea);

        // </head> pegado a otra cosa en su línea (HTML minificado): la etiqueta va pegada a él
        if (trim($antes) !== '') {
            return substr($html, 0, $posicion) . self::ETIQUETA . substr($html, $posicion);
        }

        $salto = $inicioDeLinea >= 2 && $html[$inicioDeLinea - 2] === "\r" ? "\r\n" : "\n";

        return substr($html, 0, $inicioDeLinea) . $antes . '  ' . self::ETIQUETA . $salto . substr($html, $inicioDeLinea);
    }
}
