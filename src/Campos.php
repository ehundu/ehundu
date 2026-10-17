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

    /**
     * Los nombres que el motor escribe siempre en `pagina` (formato §7.2, decisión 133): taparían un campo propio
     * con el mismo nombre, así que se avisa y el campo se ignora. `url`, `pdf` e `idioma` están reservados aparte, y
     * `grupo`, `paginas` y `paginacion` solo los escribe el motor en las páginas que genera `agrupar` o `paginar`.
     */
    public const array DEL_MOTOR = ['ruta', 'contenido', 'markdown', 'encabezados', 'traducciones'];

    private const array TEXTOS = ['titulo', 'subtitulo', 'descripcion', 'imagen', 'imagenAlt', 'icono'];
    private const array FECHAS = ['fecha', 'publicar'];
    private const array SI_NO = ['borrador', 'listada', 'pdf', 'publicarEnSuFecha'];
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
            if ($nombre === 'idioma') {
                $avisos->registrar('«idioma» sale del nombre del fichero (formato §15.2); este campo se ignora', $fichero, $lineas[$escrito[$nombre]] ?? null);
                unset($normalizados[$nombre]);

                continue;
            }

            if ($valor !== null && in_array($nombre, self::DEL_MOTOR, true)) {
                $avisos->registrar(
                    "«{$escrito[$nombre]}» es un nombre que el motor escribe siempre en la página (formato §7.2) y taparía este campo; se ignora",
                    $fichero,
                    $lineas[$escrito[$nombre]] ?? null,
                );
                unset($normalizados[$nombre]);

                continue;
            }

            if ($valor === null) {
                continue;
            }

            $linea = $lineas[$escrito[$nombre]] ?? null;
            $original = $linea === null ? '' : $textoDeLinea[$linea - $primeraLinea];

            if ($nombre === 'agrupar') {
                $agrupar = self::agrupar($valor, $escrito[$nombre], $fichero, $linea, $avisos);

                if ($agrupar === null) {
                    unset($normalizados[$nombre]);
                } else {
                    $normalizados[$nombre] = $agrupar;
                }

                continue;
            }

            if ($nombre === 'paginar') {
                $paginar = self::paginar($valor, $escrito[$nombre], $fichero, $linea, $avisos);

                if ($paginar === null) {
                    unset($normalizados[$nombre]);
                } else {
                    $normalizados[$nombre] = $paginar;
                }

                continue;
            }

            $conHora = false;

            try {
                $normalizados[$nombre] = self::comprobar((string) $nombre, $valor, $original, $zona, $conHora);
            } catch (\UnexpectedValueException $problema) {
                $avisos->registrar(str_replace('%s', "«{$escrito[$nombre]}»", $problema->getMessage()), $fichero, $linea);
                unset($normalizados[$nombre]);

                continue;
            }

            if ($conHora) {
                $avisos->registrar("«{$escrito[$nombre]}» es un día, AAAA-MM-DD: la hora no cuenta y se toma solo el día", $fichero, $linea);
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
     * @param bool $conHora se pone a verdadero si es una fecha y lo escrito llevaba hora o zona
     *
     * @throws \UnexpectedValueException con un mensaje en el que `%s` es el nombre del campo
     */
    private static function comprobar(string $nombre, mixed $valor, string $original, \DateTimeZone $zona, bool &$conHora): mixed
    {
        return match (true) {
            in_array($nombre, self::TEXTOS, true) => self::texto($valor),
            in_array($nombre, self::FECHAS, true) => self::fecha($valor, $original, $zona, $conHora),
            in_array($nombre, self::SI_NO, true) => self::siNo($valor),
            $nombre === 'orden' => self::numero($valor),
            in_array($nombre, self::LISTAS, true) => self::listaDeTextos($valor),
            $nombre === 'plantilla' => self::plantilla($valor),
            $nombre === 'url' => self::url($valor),
            default => $valor,
        };
    }

    /**
     * `agrupar` (formato §6.3): un mapa con `coleccion` y `campo`, y `excluir`
     * si se quiere. Lo que falta o no vale se avisa aquí, con su línea, y se
     * deja fuera; la página sigue siendo una plantilla de páginas que, sin
     * `coleccion` o sin `campo`, no genera ninguna. Solo si el valor ni
     * siquiera es un mapa se descarta el campo y la página es una más.
     *
     * @return array{coleccion?: string, campo?: string, excluir: list<string>}|null
     */
    private static function agrupar(mixed $valor, string $escrito, string $fichero, ?int $linea, Avisos $avisos): ?array
    {
        if (!is_array($valor) || array_is_list($valor)) {
            $avisos->registrar("«{$escrito}» tiene que ser un mapa con «coleccion» y «campo»; se ignora", $fichero, $linea);

            return null;
        }

        $agrupar = ['excluir' => []];
        $que = [
            'coleccion' => 'la colección de la que salen las páginas',
            'campo' => 'el campo por el que se agrupan',
        ];

        foreach ($que as $clave => $descripcion) {
            $dato = $valor[$clave] ?? null;
            $dato = is_int($dato) || is_float($dato) ? (string) $dato : $dato;

            if (is_string($dato) && trim($dato) !== '') {
                $agrupar[$clave] = $dato;
            } else {
                $avisos->registrar("«{$escrito}» necesita «{$clave}», {$descripcion}; no se genera ninguna página", $fichero, $linea);
            }
        }

        if (array_key_exists('excluir', $valor) && $valor['excluir'] !== null) {
            try {
                $agrupar['excluir'] = self::listaDeTextos($valor['excluir']);
            } catch (\UnexpectedValueException) {
                $avisos->registrar("«{$escrito}»: «excluir» tiene que ser un texto o una lista de textos; se ignora", $fichero, $linea);
            }
        }

        foreach (array_keys($valor) as $clave) {
            if (!in_array($clave, ['coleccion', 'campo', 'excluir'], true)) {
                $avisos->registrar("«{$escrito}» no admite «{$clave}»; se ignora", $fichero, $linea);
            }
        }

        return $agrupar;
    }

    /**
     * `paginar` (formato §6.4): un mapa con `coleccion` y `porPagina`, y `orden`
     * y `url` si se quieren. Lo que falta o no vale se avisa aquí, con su
     * línea, y se deja fuera; sin `coleccion` o sin `porPagina` la página no
     * parte nada (`Paginador`). Solo si el valor ni siquiera es un mapa se
     * descarta el campo y la página es una más.
     *
     * @return array{coleccion?: string, porPagina?: int, orden?: string, url?: string}|null
     */
    private static function paginar(mixed $valor, string $escrito, string $fichero, ?int $linea, Avisos $avisos): ?array
    {
        if (!is_array($valor) || array_is_list($valor)) {
            $avisos->registrar("«{$escrito}» tiene que ser un mapa con «coleccion» y «porPagina»; se ignora", $fichero, $linea);

            return null;
        }

        $paginar = [];
        $coleccion = $valor['coleccion'] ?? null;
        $coleccion = is_int($coleccion) || is_float($coleccion) ? (string) $coleccion : $coleccion;

        if (is_string($coleccion) && trim($coleccion) !== '') {
            $paginar['coleccion'] = $coleccion;
        } else {
            $avisos->registrar("«{$escrito}» necesita «coleccion», la colección de la que salen las páginas; no se parte nada", $fichero, $linea);
        }

        if (!array_key_exists('porPagina', $valor) || $valor['porPagina'] === null) {
            $avisos->registrar("«{$escrito}» necesita «porPagina», cuántas páginas lleva cada una; no se parte nada", $fichero, $linea);
        } elseif (is_int($valor['porPagina']) && $valor['porPagina'] > 0) {
            $paginar['porPagina'] = $valor['porPagina'];
        } else {
            $avisos->registrar("«{$escrito}»: «porPagina» tiene que ser un número entero mayor que cero; no se parte nada", $fichero, $linea);
        }

        foreach (['orden' => 'el criterio de orden()', 'url' => 'la dirección de las páginas a partir de la segunda, con {{ numero }}'] as $clave => $descripcion) {
            if (!array_key_exists($clave, $valor) || $valor[$clave] === null) {
                continue;
            }

            if (is_string($valor[$clave]) && trim($valor[$clave]) !== '') {
                $paginar[$clave] = trim($valor[$clave]);
            } else {
                $avisos->registrar("«{$escrito}»: «{$clave}» tiene que ser un texto, {$descripcion}; se ignora", $fichero, $linea);
            }
        }

        foreach (array_keys($valor) as $clave) {
            if (!in_array($clave, ['coleccion', 'porPagina', 'orden', 'url'], true)) {
                $avisos->registrar("«{$escrito}» no admite «{$clave}»; se ignora", $fichero, $linea);
            }
        }

        return $paginar;
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
     * Una fecha es ese día en la zona horaria del sitio, desde su medianoche
     * (formato §4, decisión 129). YAML lee las fechas sin zona como UTC; aquí se
     * toma el día escrito y se sitúa en la del sitio. Si lo escrito lleva hora o
     * zona, la hora no cuenta: se toma solo el día y `$conHora` lo dice, para que
     * quien llama avise.
     *
     * @param bool $conHora se pone a verdadero si lo escrito llevaba una hora o una zona
     */
    private static function fecha(mixed $valor, string $original, \DateTimeZone $zona, bool &$conHora = false): \DateTimeImmutable
    {
        // YAML convierte sin avisar una fecha imposible en otra que sí existe
        // (el 31 de febrero en el 3 de marzo), así que se mira lo escrito.
        if (preg_match('/:\s*["\']?(\d{4})-(\d{1,2})-(\d{1,2})/', $original, $partes) === 1
            && !checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1])) {
            throw new \UnexpectedValueException("%s no es una fecha posible: {$partes[1]}-{$partes[2]}-{$partes[3]}");
        }

        if ($valor instanceof \DateTimeImmutable) {
            // Un día sin zona lo lee YAML como su medianoche en UTC; con otra hora, o con zona, llevaba más que un día
            $conHora = $valor->format('H:i:s') !== '00:00:00' || $valor->getTimezone()->getName() !== 'UTC';

            return new \DateTimeImmutable($valor->format('Y-m-d') . ' 00:00:00', $zona);
        }

        // Un día, y detrás, si se quiere, una hora que YAML no ha leído como fecha (sin segundos: `09:00`) o va entre comillas
        if (is_string($valor) && preg_match('/^(\d{4})-(\d{2})-(\d{2})((?:\s+|T)\d{1,2}:\d{2}(?::\d{2}(?:[.,]\d+)?)?\s*(?:Z|[+-]\d{1,2}(?::?\d{2})?)?)?$/', trim($valor), $partes) === 1) {
            if (!checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1])) {
                throw new \UnexpectedValueException("%s no es una fecha posible: {$partes[1]}-{$partes[2]}-{$partes[3]}");
            }

            $conHora = ($partes[4] ?? '') !== '';

            return new \DateTimeImmutable("{$partes[1]}-{$partes[2]}-{$partes[3]} 00:00:00", $zona);
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

    public static function siNo(mixed $valor): bool
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
     * El nombre de una clase de CSS, o varios separados por espacios, sin los
     * espacios de los extremos; null si lo que se da no vale.
     */
    public static function claseDeCss(mixed $valor): ?string
    {
        if (is_string($valor) && preg_match('/^[A-Za-z_][A-Za-z0-9_-]*(?: [A-Za-z_][A-Za-z0-9_-]*)*$/', trim($valor)) === 1) {
            return trim($valor);
        }

        return null;
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
