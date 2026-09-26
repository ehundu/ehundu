<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Normaliza los campos de un front matter o de un `_datos.yml`: pasa los
 * alias ingleses a su nombre en español y comprueba el valor de los campos
 * reservados (formato §4).
 *
 * Un valor que no vale produce un aviso y el campo se descarta, como si no
 * estuviera. Los campos desconocidos pasan sin tocar. Un valor vacío (`null`)
 * también pasa sin aviso: es un campo que se ha dejado en blanco.
 *
 * @internal
 */
final class Campos
{
    /** Nombres ingleses aceptados para facilitar la migración. */
    public const array ALIAS = [
        'title' => 'titulo',
        'subtitle' => 'subtitulo',
        'description' => 'descripcion',
        'date' => 'fecha',
        'tags' => 'etiquetas',
        'layout' => 'plantilla',
        'permalink' => 'url',
    ];

    private const array TEXTOS = ['titulo', 'subtitulo', 'descripcion', 'imagen', 'imagenAlt', 'icono'];
    private const array FECHAS = ['fecha', 'publicar'];
    private const array SI_NO = ['borrador', 'listada'];
    private const array LISTAS = ['etiquetas', 'css', 'js'];

    /**
     * @param array<array-key, mixed> $campos       los campos tal como salen del YAML
     * @param string                  $yaml         el texto del que salen, para situar los avisos
     * @param int                     $primeraLinea línea del fichero en la que empieza ese texto
     * @param string                  $fichero      ruta relativa a la raíz del proyecto
     * @param \DateTimeZone|null      $zona         la del sitio, en la que se leen las fechas; UTC si falta
     *
     * @return array<array-key, mixed>
     */
    public static function normalizar(
        array $campos,
        string $yaml,
        int $primeraLinea,
        string $fichero,
        Avisos $avisos,
        ?\DateTimeZone $zona = null,
    ): array {
        $zona ??= new \DateTimeZone('UTC');
        $lineas = Yaml::lineasDeClaves($yaml, $primeraLinea);
        $textoDeLinea = explode("\n", $yaml);

        $normalizados = [];
        $escrito = [];

        foreach ($campos as $clave => $valor) {
            $nombre = is_string($clave) ? (self::ALIAS[$clave] ?? $clave) : $clave;

            if ($nombre !== $clave && array_key_exists($nombre, $campos)) {
                $avisos->registrar("«{$clave}» y «{$nombre}» son el mismo campo; se usa «{$nombre}»", $fichero, $lineas[$clave] ?? null);

                continue;
            }

            $normalizados[$nombre] = $valor;
            $escrito[$nombre] = (string) $clave;
        }

        foreach ($normalizados as $nombre => $valor) {
            if ($valor === null) {
                continue;
            }

            $linea = $lineas[$escrito[$nombre]] ?? null;
            $original = $linea === null ? '' : $textoDeLinea[$linea - $primeraLinea];

            try {
                $normalizados[$nombre] = self::comprobar((string) $nombre, $valor, $original, $zona);
            } catch (\UnexpectedValueException $problema) {
                $avisos->registrar(str_replace('%s', "«{$escrito[$nombre]}»", $problema->getMessage()), $fichero, $linea);
                unset($normalizados[$nombre]);

                continue;
            }

            if ($nombre === 'etiquetas' && in_array(Colecciones::TODO, $normalizados['etiquetas'], true)) {
                $avisos->registrar(
                    '«todo» es el nombre de la colección con todas las páginas; no se puede usar como etiqueta y se quita',
                    $fichero,
                    $linea,
                );
                $normalizados['etiquetas'] = array_values(array_diff($normalizados['etiquetas'], [Colecciones::TODO]));
            }
        }

        return $normalizados;
    }

    /**
     * @param string $original la línea del YAML en la que está el campo, si se conoce
     *
     * @throws \UnexpectedValueException con un mensaje en el que `%s` es el nombre del campo
     */
    private static function comprobar(string $nombre, mixed $valor, string $original, \DateTimeZone $zona): mixed
    {
        return match (true) {
            in_array($nombre, self::TEXTOS, true) => self::texto($valor),
            in_array($nombre, self::FECHAS, true) => self::fecha($valor, $original, $zona),
            in_array($nombre, self::SI_NO, true) => self::siNo($valor),
            $nombre === 'orden' => self::numero($valor),
            in_array($nombre, self::LISTAS, true) => self::listaDeTextos($valor),
            $nombre === 'plantilla' => self::plantilla($valor),
            $nombre === 'url' => self::url($valor),
            default => $valor,
        };
    }

    private static function texto(mixed $valor): string
    {
        if (is_string($valor)) {
            return $valor;
        }

        if (is_int($valor) || is_float($valor)) {
            return (string) $valor;
        }

        throw new \UnexpectedValueException('%s tiene que ser un texto');
    }

    /**
     * Una fecha es ese día en la zona horaria del sitio. YAML lee las fechas
     * sin zona como UTC; aquí se toma lo escrito y se sitúa en la del sitio.
     */
    private static function fecha(mixed $valor, string $original, \DateTimeZone $zona): \DateTimeImmutable
    {
        // YAML convierte sin avisar una fecha imposible en otra que sí existe
        // (el 31 de febrero en el 3 de marzo), así que se mira lo escrito.
        if (preg_match('/:\s*["\']?(\d{4})-(\d{1,2})-(\d{1,2})/', $original, $partes) === 1
            && !checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1])) {
            throw new \UnexpectedValueException("%s no es una fecha posible: {$partes[1]}-{$partes[2]}-{$partes[3]}");
        }

        if ($valor instanceof \DateTimeImmutable) {
            return $valor->getTimezone()->getName() === 'UTC'
                ? new \DateTimeImmutable($valor->format('Y-m-d H:i:s'), $zona)
                : $valor;
        }

        if (is_string($valor) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $partes) === 1) {
            if (!checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1])) {
                throw new \UnexpectedValueException("%s no es una fecha posible: {$valor}");
            }

            return new \DateTimeImmutable("{$valor} 00:00:00", $zona);
        }

        throw new \UnexpectedValueException('%s tiene que ser una fecha AAAA-MM-DD');
    }

    private static function numero(mixed $valor): int|float
    {
        if (is_int($valor) || is_float($valor)) {
            return $valor;
        }

        if (is_string($valor) && is_numeric($valor)) {
            return $valor + 0;
        }

        throw new \UnexpectedValueException('%s tiene que ser un número');
    }

    private static function siNo(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        return match (is_string($valor) ? mb_strtolower(trim($valor)) : null) {
            'sí', 'si' => true,
            'no' => false,
            default => throw new \UnexpectedValueException('%s tiene que ser sí o no'),
        };
    }

    /**
     * Un texto suelto cuenta como una lista de un elemento.
     *
     * @return list<string>
     */
    private static function listaDeTextos(mixed $valor): array
    {
        if (!is_array($valor)) {
            $valor = [$valor];
        }

        if (!array_is_list($valor)) {
            throw new \UnexpectedValueException('%s tiene que ser un texto o una lista de textos, por ejemplo [blog]');
        }

        $etiquetas = [];

        foreach ($valor as $etiqueta) {
            if (!is_string($etiqueta) && !is_int($etiqueta) && !is_float($etiqueta)) {
                throw new \UnexpectedValueException('%s tiene que ser un texto o una lista de textos, por ejemplo [blog]');
            }

            $etiquetas[] = (string) $etiqueta;
        }

        return array_values(array_unique($etiquetas));
    }

    /**
     * `false` o un texto vacío: la página sale sin layout.
     */
    private static function plantilla(mixed $valor): string|false
    {
        if ($valor === false || $valor === '') {
            return false;
        }

        try {
            return self::texto($valor);
        } catch (\UnexpectedValueException) {
            throw new \UnexpectedValueException('%s tiene que ser el nombre de una plantilla o false');
        }
    }

    private static function url(mixed $valor): string|false
    {
        if (is_string($valor) || $valor === false) {
            return $valor;
        }

        throw new \UnexpectedValueException('%s tiene que ser un texto o false');
    }
}
