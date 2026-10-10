<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * La sección `encabezados` de `sitio.yml` (formato §2): qué encabezados del
 * Markdown reciben un `id` y si llevan un enlace permanente al final (§8).
 *
 * Sin la sección, ninguno recibe `id`. Con ella, y sin `niveles`, los del 2 al 6.
 */
final readonly class Encabezados
{
    /** Los niveles que reciben `id` si la sección no dice otros: el 1 suele ser el título de la página. */
    public const array NIVELES_POR_DEFECTO = [2, 3, 4, 5, 6];

    /** La clase del enlace permanente si el sitio no dice otra. */
    public const string CLASE_POR_DEFECTO = 'ancla';

    private const array CAMPOS = ['niveles', 'enlace', 'clase', 'etiqueta'];

    /**
     * @param list<int>             $niveles   los que reciben `id`, de menor a mayor; vacío si no hay sección
     *                                         o si ella no quiere ninguno
     * @param string|null           $enlace    el texto del enlace permanente; null si no hay enlace
     * @param string                $clase     la clase de ese enlace, que define el sitio
     * @param array<string, string> $etiquetas el nombre accesible del enlace en cada idioma del sitio, con
     *                                         `{texto}` donde va el del encabezado; un idioma que no está aquí
     *                                         no lleva `aria-label`
     */
    public function __construct(
        public array $niveles = [],
        public ?string $enlace = null,
        public string $clase = self::CLASE_POR_DEFECTO,
        public array $etiquetas = [],
    ) {
    }

    /**
     * Si algún encabezado puede recibir `id`.
     */
    public function activo(): bool
    {
        return $this->niveles !== [];
    }

    public function recibeId(int $nivel): bool
    {
        return in_array($nivel, $this->niveles, true);
    }

    /**
     * El nombre accesible del enlace permanente en las páginas de un idioma,
     * o null si no lleva.
     */
    public function etiquetaDe(string $idioma): ?string
    {
        return $this->etiquetas[$idioma] ?? null;
    }

    /**
     * Lee la sección tal como sale del YAML. Lo que no vale se avisa, con la
     * línea de `encabezados`, y se ignora: nunca detiene la compilación.
     *
     * @param int|null $linea la de `encabezados` en `sitio.yml`
     */
    public static function leer(mixed $valor, ?int $linea, Idiomas $idiomas, Avisos $avisos): self
    {
        if ($valor === null) {
            return new self();
        }

        $fichero = Proyecto::SITIO;

        if (!is_array($valor) || ($valor !== [] && array_is_list($valor))) {
            $avisos->registrar('«encabezados» tiene que ser un mapa con «niveles», «enlace», «clase» y «etiqueta»; se ignora', $fichero, $linea);

            return new self();
        }

        foreach (array_keys($valor) as $campo) {
            if (!in_array($campo, self::CAMPOS, true)) {
                $avisos->registrar("«encabezados» no admite «{$campo}»; se ignora", $fichero, $linea);
            }
        }

        return new self(
            self::leerNiveles($valor['niveles'] ?? null, $linea, $avisos),
            self::leerEnlace($valor['enlace'] ?? null, $linea, $avisos),
            self::leerClase($valor['clase'] ?? null, $linea, $avisos),
            (new TextosPorIdioma('encabezados', 'etiqueta', 'la', 'los enlaces permanentes'))->leer($valor['etiqueta'] ?? null, $linea, $idiomas, $avisos),
        );
    }

    /**
     * @return list<int>
     */
    private static function leerNiveles(mixed $valor, ?int $linea, Avisos $avisos): array
    {
        if ($valor === null) {
            return self::NIVELES_POR_DEFECTO;
        }

        $valido = is_array($valor) && array_is_list($valor);

        foreach ($valido ? $valor : [] as $nivel) {
            $valido = $valido && is_int($nivel) && $nivel >= 1 && $nivel <= 6;
        }

        if (!$valido) {
            $avisos->registrar(
                '«encabezados»: «niveles» tiene que ser una lista de números del 1 al 6; se usan del 2 al 6',
                Proyecto::SITIO,
                $linea,
            );

            return self::NIVELES_POR_DEFECTO;
        }

        $niveles = array_values(array_unique($valor));
        sort($niveles);

        return $niveles;
    }

    private static function leerEnlace(mixed $valor, ?int $linea, Avisos $avisos): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (!is_string($valor)) {
            $avisos->registrar('«encabezados»: «enlace» tiene que ser un texto; los encabezados no llevan enlace permanente', Proyecto::SITIO, $linea);

            return null;
        }

        return trim($valor) === '' ? null : trim($valor);
    }

    private static function leerClase(mixed $valor, ?int $linea, Avisos $avisos): string
    {
        if ($valor === null) {
            return self::CLASE_POR_DEFECTO;
        }

        $clase = Campos::claseDeCss($valor);

        if ($clase !== null) {
            return $clase;
        }

        $avisos->registrar(
            '«encabezados»: «clase» tiene que ser el nombre de una clase de CSS, o varios separados por espacios; se usa '
            . self::CLASE_POR_DEFECTO,
            Proyecto::SITIO,
            $linea,
        );

        return self::CLASE_POR_DEFECTO;
    }
}
