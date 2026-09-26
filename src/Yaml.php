<?php

declare(strict_types=1);

namespace Ehundu;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

/**
 * Lectura de YAML con errores en español y situados en la línea del fichero.
 *
 * Las fechas sin comillas se leen como `DateTimeImmutable` en UTC. Las
 * etiquetas de PHP (`!php/object`, `!php/const`) no se admiten: el motor no
 * construye objetos ni lee constantes a partir del proyecto.
 *
 * @internal
 */
final class Yaml
{
    private const int OPCIONES = SymfonyYaml::PARSE_DATETIME | SymfonyYaml::PARSE_EXCEPTION_ON_INVALID_TYPE;

    /**
     * @param string $fichero      ruta relativa a la raíz del proyecto, para los errores
     * @param int    $primeraLinea línea del fichero en la que empieza el texto
     * @param bool   $secreto      el texto lleva secretos: si no es válido, el error dice el fichero, la
     *                             línea y qué pasa, pero nunca enseña el texto ni arrastra el error de
     *                             Symfony, que lo lleva dentro
     *
     * @throws ErrorDeProyecto si el YAML no es válido
     */
    public static function leer(string $texto, string $fichero, int $primeraLinea = 1, bool $secreto = false): mixed
    {
        try {
            return SymfonyYaml::parse($texto, self::OPCIONES);
        } catch (ParseException $error) {
            $linea = $error->getParsedLine();

            throw new ErrorDeProyecto(
                self::explicar($error, $secreto),
                $fichero,
                $linea > 0 ? $linea + $primeraLinea - 1 : null,
                $secreto ? null : $error,
            );
        }
    }

    /**
     * Como `leer()`, pero exige una serie de campos `nombre: valor`. Un texto
     * vacío son cero campos.
     *
     * @return array<array-key, mixed>
     *
     * @throws ErrorDeProyecto si el YAML no es válido o no son campos
     */
    public static function leerCampos(string $texto, string $fichero, int $primeraLinea = 1, bool $secreto = false): array
    {
        $valor = self::leer($texto, $fichero, $primeraLinea, $secreto);

        if ($valor === null) {
            return [];
        }

        if (!is_array($valor) || ($valor !== [] && array_is_list($valor))) {
            throw new ErrorDeProyecto('Tiene que ser una serie de campos «nombre: valor»', $fichero, $primeraLinea);
        }

        return $valor;
    }

    /**
     * La línea del fichero en la que aparece cada clave de primer nivel. YAML
     * no da esa información y los avisos la necesitan.
     *
     * @return array<string, int>
     */
    public static function lineasDeClaves(string $texto, int $primeraLinea = 1): array
    {
        $lineas = [];

        foreach (explode("\n", $texto) as $indice => $linea) {
            if (preg_match('/^(["\']?)([^\s#"\'-][^:]*?)\1\s*:(\s|$)/u', $linea, $partes) === 1) {
                $lineas[$partes[2]] ??= $primeraLinea + $indice;
            }
        }

        return $lineas;
    }

    /**
     * El error en español. Las pistas no copian nada del texto salvo el nombre
     * de una clave; el mensaje de Symfony sí (una etiqueta, una referencia),
     * así que nunca se reproduce.
     */
    private static function explicar(ParseException $error, bool $secreto): string
    {
        $mensaje = $error->getMessage();
        $pista = null;

        if (str_contains($mensaje, 'colon cannot be used in an unquoted mapping value')) {
            $pista = 'hay dos puntos en un valor sin comillas; pon el valor entre comillas';
        } elseif (preg_match('/Duplicate key "([^"]*)"/', $mensaje, $partes) === 1) {
            $pista = "la clave «{$partes[1]}» está repetida";
        } elseif (str_contains($mensaje, 'tabs as indentation')) {
            $pista = 'la sangría lleva tabuladores; usa espacios';
        } elseif (str_contains($mensaje, 'Malformed inline YAML string')) {
            $pista = 'hay unas comillas o un corchete sin cerrar';
        } elseif (str_contains($mensaje, 'Missing value for tag') || str_contains($mensaje, 'Tags support is not enabled')) {
            $pista = 'un valor empieza por «!», que en YAML marca un tipo; pon el valor entre comillas';
        } elseif (preg_match('/^Reference ".*" does not exist/', $mensaje) === 1) {
            $pista = 'un valor empieza por «*», que en YAML es una referencia; pon el valor entre comillas';
        } elseif (preg_match('/The reserved indicator "(.)" cannot start a plain scalar/', $mensaje, $partes) === 1) {
            $pista = $secreto
                ? 'un valor empieza por un carácter que YAML reserva; pon el valor entre comillas'
                : "un valor empieza por «{$partes[1]}», que YAML reserva; pon el valor entre comillas";
        }

        $explicacion = $pista === null ? 'El YAML no es válido' : "El YAML no es válido: {$pista}";

        if ($secreto) {
            return "{$explicacion}. La línea no se enseña porque lleva secretos.";
        }

        $cerca = trim($error->getSnippet());

        return $cerca === '' ? "{$explicacion}." : "{$explicacion}. Cerca de «{$cerca}».";
    }
}
